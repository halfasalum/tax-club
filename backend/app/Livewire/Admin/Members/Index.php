<?php

namespace App\Livewire\Admin\Members;

use App\Models\Institution;
use App\Models\User;
use App\Support\MemberAccounts;
use App\Support\MembershipCard;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Members within the officer's scope (MEM-REQ04, REG-REQ06, REG-REQ07).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = User::STATUS_ACTIVE;

    #[Url]
    public string $category = '';

    #[Url]
    public string $institution = '';

    #[Url]
    public bool $alumniRequests = false;

    /** @var list<string> */
    public array $selected = [];

    public ?string $statusMessage = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'category', 'institution', 'alumniRequests'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'category', 'institution', 'alumniRequests', 'selected');
        $this->status = User::STATUS_ACTIVE;
        $this->resetPage();
    }

    #[Computed]
    public function members(): LengthAwarePaginator
    {
        $search = trim($this->search);
        $digits = preg_replace('/\D/', '', $search);

        return User::query()
            ->with('institution:institution_id,name,region')
            ->where('user_type', '!=', 'staff')
            ->where('account_status', '!=', User::STATUS_PENDING_VERIFICATION)
            ->when($this->scope() !== null, fn ($query) => $query->whereIn('institution_id', $this->scope()))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search, $digits) {
                $query->where('full_name', 'like', "%{$search}%")
                    ->orWhere('membership_id', 'like', '%'.MembershipCard::normalizeNumber($search).'%')
                    ->orWhere('registration_reference', 'like', "%{$search}%");
                if (strlen($digits) >= 4) {
                    $query->orWhere('phone', 'like', '%'.ltrim($digits, '0').'%');
                }
            }))
            ->when($this->status !== '', fn ($query) => $query->where('account_status', $this->status))
            ->when($this->category !== '', fn ($query) => $query->where('user_type', $this->category))
            ->when($this->institution !== '', fn ($query) => $query->where('institution_id', $this->institution))
            ->when($this->alumniRequests, fn ($query) => $query->whereNotNull('alumni_requested_at'))
            ->orderBy('full_name')
            ->paginate(25);
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

    #[Computed]
    public function alumniRequestCount(): int
    {
        return User::whereNotNull('alumni_requested_at')
            ->where('account_status', User::STATUS_ACTIVE)
            ->when($this->scope() !== null, fn ($query) => $query->whereIn('institution_id', $this->scope()))
            ->count();
    }

    public function selectPage(): void
    {
        $this->selected = $this->members->getCollection()
            ->filter(fn (User $member) => MemberAccounts::canBecomeAlumni($member))
            ->pluck('user_id')
            ->all();
    }

    /**
     * Move the selected members to alumni, e.g. a graduating class.
     */
    public function moveSelectedToAlumni(): void
    {
        abort_unless(auth()->user()->hasControl('manage_alumni'), 403);

        if ($this->selected === []) {
            $this->addError('selected', __('Select at least one member.'));

            return;
        }

        $report = MemberAccounts::makeAlumniInBulk($this->selected, auth()->user(), __('Moved with their class'));
        $this->selected = [];
        unset($this->members, $this->alumniRequestCount);

        $this->statusMessage = $report['skipped'] > 0
            ? __(':moved moved to alumni. :skipped skipped because they are not active students.', $report)
            : __(':moved moved to alumni.', $report);
    }

    /**
     * @return list<string>|null
     */
    private function scope(): ?array
    {
        return once(fn () => auth()->user()->institutionScope());
    }

    public function render(): mixed
    {
        return view('livewire.admin.members.index', [
            'canManageAlumni' => auth()->user()->hasControl('manage_alumni'),
        ])->title(__('Members'));
    }
}
