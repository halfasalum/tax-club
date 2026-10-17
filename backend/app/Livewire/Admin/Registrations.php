<?php

namespace App\Livewire\Admin;

use App\Models\Institution;
use App\Models\User;
use App\Support\MemberRegistration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Verification queue for submitted registrations (REG-REQ02).
 */
#[Layout('components.layouts.app')]
class Registrations extends Component
{
    use WithPagination;

    #[Url]
    public string $status = User::STATUS_PENDING_VERIFICATION;

    #[Url]
    public string $search = '';

    #[Url]
    public string $institution = '';

    #[Url]
    public string $category = '';

    public ?string $rejectingUserId = null;

    public string $rejectionReason = '';

    public ?string $statusMessage = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'search', 'institution', 'category'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function registrations(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return User::query()
            ->with('institution:institution_id,name,region')
            ->where('account_status', $this->status)
            ->whereNotNull('registration_reference')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('full_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('registration_reference', 'like', "%{$search}%");
            }))
            ->when($this->institution !== '', fn ($query) => $query->where('institution_id', $this->institution))
            ->when($this->scope() !== null, fn ($query) => $query->whereIn('institution_id', $this->scope()))
            ->when($this->category !== '', fn ($query) => $query->where('user_type', $this->category))
            ->orderBy('created_at')
            ->paginate(20);
    }

    /**
     * @return Collection<int, Institution>
     */
    #[Computed]
    public function institutions(): Collection
    {
        return Institution::query()
            ->when($this->scope() !== null, fn ($query) => $query->whereIn('institution_id', $this->scope()))
            ->orderBy('name')
            ->get(['institution_id', 'name']);
    }

    /**
     * Institutions this officer may act on; null means all (patrons are limited
     * to the institutions of the clubs they look after).
     *
     * @return list<string>|null
     */
    private function scope(): ?array
    {
        return once(fn () => auth()->user()->verifiableInstitutionIds());
    }

    private function findInScope(string $userId): User
    {
        return User::query()
            ->when($this->scope() !== null, fn ($query) => $query->whereIn('institution_id', $this->scope()))
            ->findOrFail($userId);
    }

    public function approve(string $userId): void
    {
        $this->authorizeVerification();

        try {
            MemberRegistration::approve($this->findInScope($userId), auth()->user());
        } catch (InvalidArgumentException) {
            $this->addError('decision', __('This registration was already decided by another officer.'));
            unset($this->registrations);

            return;
        }

        $this->statusMessage = __('Registration approved. The member can now sign in.');
        unset($this->registrations);
    }

    public function startRejecting(string $userId): void
    {
        $this->rejectingUserId = $userId;
        $this->rejectionReason = '';
        $this->resetErrorBag();
    }

    public function cancelRejecting(): void
    {
        $this->reset('rejectingUserId', 'rejectionReason');
    }

    public function reject(): void
    {
        $this->authorizeVerification();

        $this->validate(['rejectionReason' => ['required', 'string', 'min:5', 'max:500']], [], ['rejectionReason' => __('reason')]);

        try {
            MemberRegistration::reject($this->findInScope($this->rejectingUserId), auth()->user(), $this->rejectionReason);
        } catch (InvalidArgumentException) {
            $this->addError('decision', __('This registration was already decided by another officer.'));
        }

        $this->reset('rejectingUserId', 'rejectionReason');
        $this->statusMessage = __('Registration rejected.');
        unset($this->registrations);
    }

    private function authorizeVerification(): void
    {
        abort_unless(auth()->user()?->hasControl('verify_registration'), 403);
    }

    public function render(): mixed
    {
        return view('livewire.admin.registrations', [
            'canVerify' => auth()->user()->hasControl('verify_registration'),
            'scopedInstitutions' => $this->scope(),
        ])->title(__('Registrations'));
    }
}
