<?php

namespace App\Livewire\Admin\Mentorship;

use App\Models\MentorProfile;
use App\Models\Mentorship;
use App\Models\MentorshipRequest;
use App\Support\MentorshipDesk;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Mentor applications, matching and monitoring (MNT-REQ01, 03-06).
 * Patrons see only their own members' requests, to confirm consent.
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    #[Url]
    public string $view = 'requests';

    public ?int $matching = null;

    /** @var array<int, string> */
    public array $notes = [];

    public ?string $statusMessage = null;

    public function mount(): void
    {
        abort_unless($this->coordinator() || $this->reviewer() || auth()->user()->hasControl('view_members'), 403);

        if (! $this->coordinator() && $this->reviewer()) {
            $this->view = 'applications';
        }
    }

    public function decide(int $profileId, bool $approve): void
    {
        abort_unless($this->reviewer(), 403);
        $this->attempt(fn () => MentorshipDesk::decideApplication(MentorProfile::findOrFail($profileId), auth()->user(), $approve, $this->notes[$profileId] ?? null), $approve ? __('Mentor approved.') : __('Application declined.'));
    }

    public function backgroundCheck(int $profileId, bool $cleared): void
    {
        abort_unless($this->reviewer(), 403);
        $this->attempt(fn () => MentorshipDesk::recordBackgroundCheck(MentorProfile::findOrFail($profileId), $cleared, auth()->user(), (string) ($this->notes[$profileId] ?? '')), __('Background check recorded.'));
    }

    public function confirmConsent(int $requestId): void
    {
        $this->attempt(fn () => MentorshipDesk::confirmConsent(MentorshipRequest::findOrFail($requestId), auth()->user()), __('Consent confirmed.'));
    }

    public function match(int $requestId, int $profileId): void
    {
        abort_unless($this->coordinator(), 403);
        $this->attempt(fn () => MentorshipDesk::match(MentorshipRequest::findOrFail($requestId), MentorProfile::findOrFail($profileId), auth()->user()), __('Matched. Both were told.'));
        $this->matching = null;
    }

    public function decline(int $requestId): void
    {
        abort_unless($this->coordinator(), 403);
        $this->attempt(fn () => MentorshipDesk::declineRequest(MentorshipRequest::findOrFail($requestId), auth()->user(), (string) ($this->notes[$requestId] ?? '')), __('Request declined.'));
    }

    private function coordinator(): bool
    {
        return auth()->user()->hasControl('manage_mentorships');
    }

    private function reviewer(): bool
    {
        return auth()->user()->hasControl('review_mentors');
    }

    private function attempt(callable $action, string $success): void
    {
        $this->resetErrorBag();

        try {
            $action();
        } catch (InvalidArgumentException $exception) {
            $this->addError('action', $exception->getMessage());

            return;
        }

        $this->statusMessage = $success;
    }

    public function render(): mixed
    {
        $user = auth()->user();
        $scope = $this->coordinator() ? null : ($user->institutionScope() ?? []);

        $requests = MentorshipRequest::where('status', MentorshipRequest::OPEN)
            ->with('mentee:user_id,full_name,membership_id,institution_id,age_band', 'mentee.institution:institution_id,name,region')
            ->when($scope !== null, fn ($query) => $query->whereHas('mentee', fn ($query) => $query->whereIn('institution_id', $scope)))
            ->oldest()->get();

        return view('livewire.admin.mentorship.index', [
            'requests' => $requests,
            'applications' => $this->reviewer() ? MentorProfile::whereIn('status', [MentorProfile::SUBMITTED])->orWhere('background_check', 'pending')->with('user:user_id,full_name,user_type,membership_id')->oldest('updated_at')->get() : collect(),
            'active' => $this->coordinator() ? Mentorship::where('status', Mentorship::ACTIVE)->with(['mentor:user_id,full_name', 'mentee:user_id,full_name,age_band', 'supervisor:user_id,full_name'])->withCount('sessions')->orderBy('last_activity_at')->get() : collect(),
            'suggestions' => $this->matching ? MentorshipDesk::suggestions(MentorshipRequest::findOrFail($this->matching)) : collect(),
            'areas' => MentorshipDesk::areaLabels(),
            'can' => ['coordinate' => $this->coordinator(), 'review' => $this->reviewer()],
            'inactiveBefore' => now()->subDays(MentorshipDesk::INACTIVE_DAYS),
        ])->title(__('Mentorship'));
    }
}
