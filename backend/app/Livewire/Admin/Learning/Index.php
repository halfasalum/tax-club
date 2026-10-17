<?php

namespace App\Livewire\Admin\Learning;

use App\Models\Content;
use App\Models\ContentCategory;
use App\Models\ContentVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Learning content in every state of review and publication (LRN-REQ01-03).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    public const VIEWS = ['all', 'review', 'drafts', 'live', 'scheduled', 'offline', 'review_due'];

    #[Url]
    public string $view = 'all';

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $type = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $search = trim($this->search);
        $open = fn (Builder $query) => $query->whereIn('status', ContentVersion::OPEN);

        return Content::query()
            ->with(['category', 'versions' => $open])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('title', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhereHas('versions', fn ($query) => $query->whereIn('status', ContentVersion::OPEN)->where('fields->title', 'like', "%{$search}%"))))
            ->when($this->category !== '', fn ($query) => $query->where('category_id', $this->category))
            ->when($this->type !== '', fn ($query) => $query->where('content_type', $this->type))
            ->when($this->view === 'review', fn ($query) => $query->whereHas('versions', fn ($query) => $query->where('status', ContentVersion::IN_REVIEW)))
            ->when($this->view === 'drafts', fn ($query) => $query->whereHas('versions', fn ($query) => $query->whereIn('status', ContentVersion::EDITABLE)))
            ->when($this->view === 'live', fn ($query) => $query->live())
            ->when($this->view === 'scheduled', fn ($query) => $query->where('publication_status', Content::SCHEDULED)->where('publish_at', '>', now()))
            ->when($this->view === 'offline', fn ($query) => $query->whereIn('publication_status', [Content::UNPUBLISHED, Content::ARCHIVED]))
            ->when($this->view === 'review_due', fn ($query) => $query->live()->where('review_due_at', '<=', now()->addMonth()))
            ->latest('updated_at')
            ->paginate(20);
    }

    #[Computed]
    public function waitingForReview(): int
    {
        return ContentVersion::where('status', ContentVersion::IN_REVIEW)->count();
    }

    public function render(): mixed
    {
        return view('livewire.admin.learning.index', [
            'categories' => ContentCategory::active()->get(),
            'canCreate' => auth()->user()->hasControl('upload_content'),
            'canManageCategories' => auth()->user()->hasControl('manage_content_categories'),
        ])->title(__('Learning content'));
    }
}
