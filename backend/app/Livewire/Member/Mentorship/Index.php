<?php

namespace App\Livewire\Member\Mentorship;

use App\Models\MentorProfile;
use App\Models\Mentorship;
use App\Models\MentorshipRequest;
use App\Support\MentorshipDesk;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Ask for a mentor, apply to be one, and see your mentorships
 * (MNT-REQ01, MNT-REQ02).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    /** @var list<string> */
    public array $interests = [];

    public string $goals = '';

    public string $mode = 'either';

    public bool $guardian_consent = false;

    /** @var list<string> */
    public array $expertise = [];

    public string $bio = '';

    public string $availability = '';

    public int $max_mentees = 2;

    public bool $mentors_minors = false;

    public bool $declaration = false;

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $profile = MentorProfile::where('user_id', auth()->id())->first();

        if ($profile) {
            $this->expertise = $profile->expertise;
            $this->bio = $profile->bio;
            $this->availability = (string) $profile->availability;
            $this->max_mentees = $profile->max_mentees;
            $this->mentors_minors = $profile->mentors_minors;
        }
    }

    public function requestMentor(): void
    {
        $this->validate(['interests' => 'required|array|min:1', 'goals' => 'required|string|min:20|max:2000', 'mode' => 'required|in:online,in_person,either']);
        $this->attempt(fn () => MentorshipDesk::request(auth()->user(), ['interests' => $this->interests, 'goals' => $this->goals, 'mode' => $this->mode, 'guardian_consent' => $this->guardian_consent]),
            __('Request sent. A coordinator will match you with a mentor.'));
        $this->reset('interests', 'goals', 'mode', 'guardian_consent');
    }

    public function cancelRequest(int $id): void
    {
        $this->attempt(fn () => MentorshipDesk::cancelRequest(MentorshipRequest::findOrFail($id), auth()->user()), __('Request cancelled.'));
    }

    public function apply(): void
    {
        $this->validate(['expertise' => 'required|array|min:1', 'bio' => 'required|string|min:40|max:2000', 'availability' => 'nullable|string|max:300', 'max_mentees' => 'required|integer|min:1|max:5']);
        $this->attempt(fn () => MentorshipDesk::apply(auth()->user(), $this->only(['expertise', 'bio', 'availability', 'max_mentees', 'mentors_minors', 'declaration'])),
            __('Thank you! TRA will review your application.'));
    }

    private function attempt(callable $action, string $success): void
    {
        $this->resetErrorBag();

        try {
            $action();
        } catch (InvalidArgumentException $exception) {
            $this->addError('form', $exception->getMessage());

            return;
        }

        $this->statusMessage = $success;
    }

    public function render(): mixed
    {
        $user = auth()->user();

        return view('livewire.member.mentorship.index', [
            'canMentor' => MentorshipDesk::canApplyAsMentor($user),
            'profile' => MentorProfile::where('user_id', $user->user_id)->first(),
            'openRequest' => MentorshipRequest::where('mentee_id', $user->user_id)->where('status', MentorshipRequest::OPEN)->first(),
            'mentorships' => Mentorship::where(fn ($query) => $query->where('mentor_id', $user->user_id)->orWhere('mentee_id', $user->user_id))->with(['mentor:user_id,full_name', 'mentee:user_id,full_name'])->latest('started_at')->get(),
            'areas' => MentorshipDesk::areaLabels(),
        ])->title(__('Mentorship'));
    }
}
