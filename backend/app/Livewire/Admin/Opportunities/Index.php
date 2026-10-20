<?php

namespace App\Livewire\Admin\Opportunities;

use App\Models\Opportunity;
use App\Support\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Opportunities in every state (OPP-REQ01-03).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $view = 'open';

    #[Url]
    public string $search = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function opportunities(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Opportunity::query()
            ->withCount(['savedBy as saves_count'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('title', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('organization', 'like', "%{$search}%")))
            ->when($this->view === 'open', fn ($query) => $query->open())
            ->when($this->view === 'review', fn ($query) => $query->where('editorial_status', Review::IN_REVIEW))
            ->when($this->view === 'drafts', fn ($query) => $query->whereIn('editorial_status', Review::EDITABLE)->where('publication_status', Opportunity::NOT_PUBLISHED))
            ->when($this->view === 'ready', fn ($query) => $query->where('editorial_status', Review::APPROVED)->whereIn('publication_status', [Opportunity::NOT_PUBLISHED, Opportunity::SCHEDULED]))
            ->when($this->view === 'closed', fn ($query) => $query->whereIn('publication_status', [Opportunity::CLOSED, Opportunity::WITHDRAWN, Opportunity::ARCHIVED]))
            ->latest('updated_at')
            ->paginate(20);
    }

    public function render(): mixed
    {
        $user = auth()->user();

        return view('livewire.admin.opportunities.index', [
            'waiting' => Opportunity::where('editorial_status', Review::IN_REVIEW)->count(),
            'canCreate' => $user->hasControl('add_opportunity'),
            'canReport' => $user->hasControl('view_opportunity_reports'),
        ])->title(__('Opportunities'));
    }
}
