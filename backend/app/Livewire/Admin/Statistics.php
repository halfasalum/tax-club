<?php

namespace App\Livewire\Admin;

use App\Support\MembershipStatistics;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Membership statistics by region and institution (INS-REQ08).
 */
#[Layout('components.layouts.app')]
class Statistics extends Component
{
    use WithPagination;

    #[Url]
    public string $region = '';

    #[Url]
    public string $type = '';

    #[Url]
    public string $sort = 'active_members';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function regions(): Collection
    {
        return MembershipStatistics::perRegion($this->scope(), $this->type);
    }

    #[Computed]
    public function institutions(): LengthAwarePaginator
    {
        $sort = array_key_exists($this->sort, MembershipStatistics::measures()) ? $this->sort : 'active_members';

        return MembershipStatistics::perInstitution($this->scope(), $this->region, $this->type)
            ->orderByDesc($sort)
            ->orderBy('institutions.name')
            ->paginate(25);
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
        $regions = $this->region === '' ? $this->regions : $this->regions->where('region', $this->region);

        return view('livewire.admin.statistics', [
            'totals' => MembershipStatistics::totals($regions),
            'labels' => MembershipStatistics::labels(),
            'exportUrl' => route('admin.statistics.export', array_filter(['region' => $this->region, 'type' => $this->type])),
        ])->title(__('Statistics'));
    }
}
