<?php

namespace App\Livewire\Admin\Institutions;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Search and filter institutions and their Tax Clubs (INS-REQ07, INS-REQ08).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $type = '';

    #[Url]
    public string $region = '';

    #[Url]
    public string $status = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type', 'region', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'type', 'region', 'status');
        $this->resetPage();
    }

    #[Computed]
    public function institutions(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Institution::query()
            ->with('activeTaxClub:tax_club_id,institution_id,name,status')
            ->withCount([
                'users as members_count' => fn ($query) => $query->where('account_status', User::STATUS_ACTIVE)->where('user_type', '!=', 'staff'),
                'users as pending_count' => fn ($query) => $query->where('account_status', User::STATUS_PENDING_VERIFICATION),
            ])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('district', 'like', "%{$search}%")))
            ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
            ->when($this->region !== '', fn ($query) => $query->where('region', $this->region))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when(($scope = auth()->user()->institutionScope()) !== null, fn ($query) => $query->whereIn('institution_id', $scope))
            ->orderBy('name')
            ->paginate(20);
    }

    public function render(): mixed
    {
        return view('livewire.admin.institutions.index', [
            'regions' => array_values(config('tanzania.regions')),
            'hasFilters' => $this->search !== '' || $this->type !== '' || $this->region !== '' || $this->status !== '',
        ])->title(__('Institutions'));
    }
}
