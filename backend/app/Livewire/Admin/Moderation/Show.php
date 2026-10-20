<?php

namespace App\Livewire\Admin\Moderation;

use App\Models\ModerationAction;
use App\Models\ModerationCase;
use App\Models\User;
use App\Models\UserRestriction;
use App\Support\Reportables;
use App\Support\SafetyDesk;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Investigate a case and act on the content and the account
 * (SAF-REQ02-05, COM-REQ08).
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public ModerationCase $case;

    public string $reason = '';

    public string $note = '';

    public string $assignee = '';

    public string $restrictionType = 'community';

    public ?int $restrictionDays = 7;

    public string $outcome = 'actioned';

    public ?string $statusMessage = null;

    public function mount(ModerationCase $case): void
    {
        abort_unless(ModerationCase::visibleTo(auth()->user())->whereKey($case->case_id)->exists(), 404);
        $this->case = $case;
        $this->assignee = (string) $case->assigned_to;
    }

    public function assign(): void
    {
        $this->attempt(fn () => SafetyDesk::assign($this->case, $this->assignee ? User::findOrFail($this->assignee) : null, auth()->user()), __('Saved.'));
    }

    public function takeIt(): void
    {
        $this->attempt(fn () => SafetyDesk::assign($this->case, auth()->user(), auth()->user()), __('Assigned to you.'));
    }

    public function addNote(): void
    {
        $this->attempt(fn () => SafetyDesk::note($this->case, $this->note, auth()->user()), __('Note added.'));
    }

    public function hide(bool $remove = false): void
    {
        $this->attempt(fn () => SafetyDesk::hideContent($this->case, auth()->user(), $this->reason, $remove), $remove ? __('Removed.') : __('Hidden from members.'));
    }

    public function restore(): void
    {
        $this->attempt(fn () => SafetyDesk::restoreContent($this->case, auth()->user(), $this->reason), __('Visible again.'));
    }

    public function warn(): void
    {
        $this->attempt(fn () => SafetyDesk::warn($this->owner(), $this->reason, auth()->user(), $this->case), __('Warning sent.'));
    }

    public function restrict(): void
    {
        $this->validate(['restrictionType' => 'required|in:'.implode(',', UserRestriction::TYPES), 'restrictionDays' => 'nullable|integer|min:1|max:365']);
        $this->attempt(fn () => SafetyDesk::restrict($this->owner(), $this->restrictionType, $this->restrictionDays ?: null, $this->reason, auth()->user(), $this->case), __('Restriction applied.'));
    }

    public function suspend(): void
    {
        $this->attempt(fn () => SafetyDesk::suspend($this->owner(), $this->reason, auth()->user(), $this->case), __('Account suspended.'));
    }

    public function escalate(): void
    {
        $this->attempt(fn () => SafetyDesk::escalate($this->case, auth()->user(), $this->reason), __('Escalated to safeguarding officers.'));
    }

    public function close(): void
    {
        $this->attempt(fn () => SafetyDesk::close($this->case, $this->outcome, auth()->user(), $this->note), __('Case closed. Reporters were thanked.'));
    }

    public function reopen(): void
    {
        $this->attempt(fn () => SafetyDesk::reopen($this->case, auth()->user(), $this->reason), __('Reopened.'));
    }

    private function owner(): User
    {
        $owner = $this->case->subjectUser ?? Reportables::describe($this->case->subject_type, $this->case->subject_id)['owner'];

        if (! $owner) {
            throw new InvalidArgumentException(__('There is no account to act on.'));
        }

        return $owner;
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

        $this->reset('reason', 'note');
        $this->case->refresh();
        $this->statusMessage = $success;
    }

    public function render(): mixed
    {
        $user = auth()->user();
        $subject = Reportables::describe($this->case->subject_type, $this->case->subject_id);
        $owner = $subject['owner'];

        return view('livewire.admin.moderation.show', [
            'subject' => $subject,
            'owner' => $owner?->loadMissing('institution:institution_id,name,region'),
            'ownerHistory' => $owner ? [
                'cases' => ModerationCase::where('subject_user_id', $owner->user_id)->where('case_id', '!=', $this->case->case_id)->count(),
                'warnings' => ModerationAction::where('target_user_id', $owner->user_id)->where('action', 'warned')->count(),
                'restrictions' => UserRestriction::active()->where('user_id', $owner->user_id)->get(),
            ] : null,
            'reports' => $this->case->reports()->with('reporter:user_id,full_name,age_band')->get(),
            'history' => $this->case->actions()->with('performer:user_id,full_name')->get(),
            'officers' => User::where('account_status', User::STATUS_ACTIVE)
                ->whereHas('roles.controls', fn ($query) => $query->where('slug', $this->case->safeguarding ? 'handle_safeguarding_concerns' : 'moderate_community'))
                ->orderBy('full_name')->limit(100)->get(['user_id', 'full_name']),
            'canRestrict' => $user->hasControl('restrict_users'),
        ])->title($this->case->reference);
    }
}
