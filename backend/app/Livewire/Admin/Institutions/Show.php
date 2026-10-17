<?php

namespace App\Livewire\Admin\Institutions;

use App\Models\AuditLog;
use App\Models\ClubPatron;
use App\Models\Institution;
use App\Models\TaxClub;
use App\Models\User;
use App\Support\InstitutionRegistry;
use App\Support\MemberRegistration;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * One institution: status, Tax Club, patrons, members and history
 * (INS-REQ02–05, INS-REQ08).
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public Institution $institution;

    public string $statusReason = '';

    public bool $changingStatus = false;

    public string $clubName = '';

    public string $clubEstablishedOn = '';

    public string $clubStatusReason = '';

    public bool $changingClubStatus = false;

    public string $patronPhone = '';

    public string $patronName = '';

    public string $patronEmail = '';

    public string $patronStartsOn = '';

    public ?int $endingAssignmentId = null;

    public string $endReason = '';

    public ?string $statusMessage = null;

    /** @var array{name: string, phone: string, password: string}|null */
    public ?array $newPatronCredentials = null;

    public function mount(Institution $institution): void
    {
        $this->institution = $institution;
        $this->clubName = $institution->name.' Tax Club';
        $this->patronStartsOn = now()->toDateString();
    }

    public function changeStatus(): void
    {
        $this->authorizeControl('change_institution_status');
        $target = $this->institution->isActive() ? Institution::STATUS_INACTIVE : Institution::STATUS_ACTIVE;

        if ($target === Institution::STATUS_INACTIVE) {
            $this->validate(['statusReason' => ['required', 'string', 'min:5', 'max:500']], [], ['statusReason' => __('reason')]);
        }

        if (! $this->attempt(fn () => InstitutionRegistry::changeStatus($this->institution, $target, $this->statusReason ?: null, auth()->user()))) {
            return;
        }

        $this->reset('statusReason', 'changingStatus');
        $this->statusMessage = $target === Institution::STATUS_ACTIVE
            ? __('Institution activated. Students can select it when registering.')
            : __('Institution deactivated. Existing members are kept; new students cannot select it.');
    }

    public function establishClub(): void
    {
        $this->authorizeControl('manage_tax_clubs');

        $this->validate([
            'clubName' => ['required', 'string', 'min:3', 'max:200'],
            'clubEstablishedOn' => ['nullable', 'date', 'before_or_equal:today'],
        ], [], ['clubName' => __('club name'), 'clubEstablishedOn' => __('date established')]);

        if ($this->attempt(fn () => InstitutionRegistry::establishClub($this->institution, [
            'name' => $this->clubName,
            'established_on' => $this->clubEstablishedOn ?: null,
        ], auth()->user()))) {
            $this->statusMessage = __('Tax Club established. You can now assign a patron.');
        }
    }

    public function changeClubStatus(string $clubId): void
    {
        $this->authorizeControl('manage_tax_clubs');
        $club = $this->institution->taxClubs()->findOrFail($clubId);
        $target = $club->status === TaxClub::STATUS_ACTIVE ? TaxClub::STATUS_INACTIVE : TaxClub::STATUS_ACTIVE;

        if ($target === TaxClub::STATUS_INACTIVE) {
            $this->validate(['clubStatusReason' => ['required', 'string', 'min:5', 'max:500']], [], ['clubStatusReason' => __('reason')]);
        }

        if ($this->attempt(fn () => InstitutionRegistry::changeClubStatus($club, $target, $this->clubStatusReason ?: null))) {
            $this->reset('clubStatusReason', 'changingClubStatus');
            $this->statusMessage = $target === TaxClub::STATUS_ACTIVE ? __('Tax Club reactivated.') : __('Tax Club deactivated.');
        }
    }

    public function assignPatron(string $clubId): void
    {
        $this->authorizeControl('assign_patron');
        $club = $this->institution->taxClubs()->findOrFail($clubId);
        $this->patronPhone = MemberRegistration::compactPhone($this->patronPhone);

        $this->validate([
            'patronPhone' => ['required', 'string', 'regex:/^(\+?255|0)[67]\d{8}$/'],
            'patronName' => ['nullable', 'string', 'min:3', 'max:150'],
            'patronEmail' => ['nullable', 'email', 'max:150'],
            'patronStartsOn' => ['required', 'date'],
        ], [
            'patronPhone.regex' => __('Enter a Tanzanian mobile number, for example 0712 345 678.'),
        ], [
            'patronPhone' => __('phone number'), 'patronName' => __('full name'), 'patronEmail' => __('email'), 'patronStartsOn' => __('start date'),
        ]);

        $result = $this->attempt(fn () => InstitutionRegistry::assignPatron($club, [
            'phone' => $this->patronPhone,
            'full_name' => $this->patronName,
            'email' => $this->patronEmail,
            'starts_on' => $this->patronStartsOn,
        ], auth()->user()), 'patron');

        if (! $result) {
            return;
        }

        [$assignment, $temporaryPassword] = $result;
        $patron = $assignment->user;

        $this->newPatronCredentials = $temporaryPassword
            ? ['name' => $patron->full_name, 'phone' => $patron->phone, 'password' => $temporaryPassword]
            : null;
        $this->statusMessage = __(':name is now a patron of this Tax Club.', ['name' => $patron->full_name]);
        $this->reset('patronPhone', 'patronName', 'patronEmail');
        $this->patronStartsOn = now()->toDateString();
    }

    public function startEndingAssignment(int $assignmentId): void
    {
        $this->endingAssignmentId = $assignmentId;
        $this->endReason = '';
        $this->resetErrorBag();
    }

    public function endAssignment(): void
    {
        $this->authorizeControl('assign_patron');
        $this->validate(['endReason' => ['required', 'string', 'min:5', 'max:500']], [], ['endReason' => __('reason')]);

        $assignment = ClubPatron::whereIn('tax_club_id', $this->institution->taxClubs()->pluck('tax_club_id'))->findOrFail($this->endingAssignmentId);

        if ($this->attempt(fn () => InstitutionRegistry::endPatronAssignment($assignment, $this->endReason, auth()->user()))) {
            $this->reset('endingAssignmentId', 'endReason');
            $this->statusMessage = __('Patron assignment ended. Their past actions stay on record.');
        }
    }

    public function dismissCredentials(): void
    {
        $this->newPatronCredentials = null;
    }

    /**
     * Run a registry action, turning business-rule failures into a form error.
     */
    private function attempt(callable $action, string $errorKey = 'action'): mixed
    {
        try {
            return $action() ?? true;
        } catch (InvalidArgumentException $exception) {
            $this->addError($errorKey, $exception->getMessage());

            return false;
        }
    }

    private function authorizeControl(string $control): void
    {
        abort_unless(auth()->user()->hasControl($control), 403);
    }

    public function render(): mixed
    {
        $this->institution->refresh();
        $user = auth()->user();
        $clubs = $this->institution->taxClubs()
            ->with(['patronAssignments.user:user_id,full_name,phone,email'])
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->latest()
            ->get();

        return view('livewire.admin.institutions.show', [
            'clubs' => $clubs,
            'history' => $this->institution->statusHistory()->with('officer:user_id,full_name')->limit(10)->get(),
            'changes' => AuditLog::query()
                ->where('auditable_type', 'institution')
                ->where('auditable_id', $this->institution->institution_id)
                ->where('action', 'institution.updated')
                ->with('actor:user_id,full_name')
                ->latest('id')
                ->limit(10)
                ->get(),
            'memberCounts' => [
                'active' => $this->institution->users()->where('account_status', User::STATUS_ACTIVE)->where('user_type', '!=', 'staff')->count(),
                'pending' => $this->institution->users()->where('account_status', User::STATUS_PENDING_VERIFICATION)->count(),
                'alumni' => $this->institution->users()->where('user_type', 'alumni')->where('account_status', User::STATUS_ACTIVE)->count(),
            ],
            'can' => [
                'edit' => $user->hasControl('edit_institution'),
                'status' => $user->hasControl('change_institution_status'),
                'clubs' => $user->hasControl('manage_tax_clubs'),
                'patrons' => $user->hasControl('assign_patron'),
                'audit' => $user->hasControl('view_audit_log'),
            ],
        ])->title($this->institution->name);
    }
}
