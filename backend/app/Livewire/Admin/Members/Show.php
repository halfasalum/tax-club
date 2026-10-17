<?php

namespace App\Livewire\Admin\Members;

use App\Models\MembershipVerification;
use App\Models\User;
use App\Support\MemberAccounts;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * One member: details, lifecycle history and status actions (MEM-REQ04,
 * REG-REQ06, REG-REQ07).
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public User $member;

    public string $newStatus = '';

    public string $statusReason = '';

    public string $alumniReason = '';

    public ?string $statusMessage = null;

    public function mount(User $member): void
    {
        abort_if($member->user_type === 'staff' || ! auth()->user()->canLookAfter($member), 404);

        $this->member = $member;
    }

    public function startStatusChange(string $status): void
    {
        $this->authorizeControl('change_member_status');
        $this->newStatus = $status;
        $this->statusReason = '';
        $this->resetErrorBag();
    }

    public function cancelStatusChange(): void
    {
        $this->reset('newStatus', 'statusReason');
    }

    public function changeStatus(): void
    {
        $this->authorizeControl('change_member_status');
        $this->validate(['statusReason' => 'required|string|min:5|max:500'], [], ['statusReason' => __('reason')]);

        try {
            MemberAccounts::changeStatus($this->member, $this->newStatus, $this->statusReason, auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('statusReason', $exception->getMessage());

            return;
        }

        $this->statusMessage = match ($this->newStatus) {
            User::STATUS_ACTIVE => __('Account reactivated. The member has been told by SMS.'),
            User::STATUS_SUSPENDED => __('Account suspended. The member was signed out and told by SMS.'),
            default => __('Account deactivated. The member was signed out and told by SMS.'),
        };
        $this->reset('newStatus', 'statusReason');
    }

    public function makeAlumni(): void
    {
        $this->authorizeControl('manage_alumni');

        try {
            MemberAccounts::makeAlumni($this->member, auth()->user(), $this->member->alumni_requested_at ? __('Requested by the member') : null);
        } catch (InvalidArgumentException $exception) {
            $this->addError('alumni', $exception->getMessage());

            return;
        }

        $this->statusMessage = __(':name is now alumni.', ['name' => $this->member->full_name]);
    }

    public function declineAlumni(): void
    {
        $this->authorizeControl('manage_alumni');
        $this->validate(['alumniReason' => 'required|string|min:5|max:500'], [], ['alumniReason' => __('reason')]);

        MemberAccounts::declineAlumni($this->member, auth()->user(), $this->alumniReason);
        $this->reset('alumniReason');
        $this->statusMessage = __('Alumni request declined.');
    }

    private function authorizeControl(string $control): void
    {
        abort_unless(auth()->user()->hasControl($control), 403);
    }

    public function render(): mixed
    {
        $this->member->refresh()->load('institution');
        $user = auth()->user();

        return view('livewire.admin.members.show', [
            'history' => $this->member->statusHistory()->with('officer:user_id,full_name')->limit(20)->get(),
            'checks' => MembershipVerification::where('member_id', $this->member->user_id)->count(),
            'lastCheck' => MembershipVerification::where('member_id', $this->member->user_id)->latest('id')->value('created_at'),
            'can' => [
                'status' => $user->hasControl('change_member_status'),
                'alumni' => $user->hasControl('manage_alumni'),
                'audit' => $user->hasControl('view_audit_log'),
            ],
        ])->title($this->member->full_name);
    }
}
