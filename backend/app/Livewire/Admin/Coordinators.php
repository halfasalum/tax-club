<?php

namespace App\Livewire\Admin;

use App\Models\CoordinatorAssignment;
use App\Support\Coordinators as CoordinatorService;
use App\Support\MemberRegistration;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Assign TRA Tax Club coordinators to regions (INS-REQ06).
 */
#[Layout('components.layouts.app')]
class Coordinators extends Component
{
    public string $phone = '';

    public string $fullName = '';

    public string $email = '';

    public string $region = '';

    public string $startsOn = '';

    public ?int $endingId = null;

    public string $endReason = '';

    public ?string $statusMessage = null;

    public ?string $temporaryPassword = null;

    public function mount(): void
    {
        $this->startsOn = now()->toDateString();
    }

    /**
     * @return Collection<string, Collection<int, CoordinatorAssignment>>
     */
    #[Computed]
    public function assignmentsByRegion(): Collection
    {
        return CoordinatorAssignment::current()
            ->with(['user:user_id,full_name,phone,email', 'assignedBy:user_id,full_name'])
            ->orderBy('region')
            ->get()
            ->groupBy('region');
    }

    public function assign(): void
    {
        $this->phone = MemberRegistration::compactPhone($this->phone);
        $this->validate([
            'phone' => ['required', 'regex:'.MemberRegistration::PHONE_PATTERN],
            'fullName' => 'nullable|string|min:3|max:150',
            'email' => 'nullable|email|max:150',
            'region' => 'required|in:'.implode(',', config('tanzania.regions')),
            'startsOn' => 'required|date',
        ], ['phone.regex' => __('Enter a Tanzanian mobile number, for example 0712 345 678.')], ['phone' => __('phone'), 'fullName' => __('full name'), 'region' => __('region'), 'startsOn' => __('start date')]);

        try {
            [$assignment, $password] = CoordinatorService::assign([
                'phone' => $this->phone,
                'full_name' => $this->fullName,
                'email' => $this->email,
                'region' => $this->region,
                'starts_on' => $this->startsOn,
            ], auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('phone', $exception->getMessage());

            return;
        }

        $this->temporaryPassword = $password;
        $this->statusMessage = $password
            ? __('New coordinator account created for :name. Share the one-time password below; it was also sent by SMS.', ['name' => $assignment->user->full_name])
            : __(':name now coordinates :region.', ['name' => $assignment->user->full_name, 'region' => $assignment->region]);
        $this->reset('phone', 'fullName', 'email', 'region');
        unset($this->assignmentsByRegion);
    }

    public function startEnding(int $assignmentId): void
    {
        $this->endingId = $assignmentId;
        $this->endReason = '';
        $this->resetErrorBag();
    }

    public function endAssignment(): void
    {
        $this->validate(['endReason' => 'required|string|min:5|max:500'], [], ['endReason' => __('reason')]);

        CoordinatorService::end(CoordinatorAssignment::findOrFail($this->endingId), $this->endReason, auth()->user());
        $this->reset('endingId', 'endReason', 'temporaryPassword');
        $this->statusMessage = __('Assignment ended. The history is kept.');
        unset($this->assignmentsByRegion);
    }

    public function render(): mixed
    {
        return view('livewire.admin.coordinators', [
            'regions' => array_values(config('tanzania.regions')),
        ])->title(__('Coordinators'));
    }
}
