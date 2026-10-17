<?php

namespace App\Livewire\Member\Learn;

use App\Models\ContentCategory;
use App\Models\LearningModule;
use App\Models\UserProgress;
use App\Support\LearningProgress;
use App\Support\LearningSearch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The member's learning hub: modules, search and filters (LRN-REQ05-07).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $type = '';

    #[Url]
    public string $level = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'category', 'type', 'level');
        $this->resetPage();
    }

    #[Computed]
    public function lessons(): LengthAwarePaginator
    {
        return LearningSearch::query(auth()->user(), [
            'search' => $this->search,
            'category' => $this->category,
            'type' => $this->type,
            'level' => $this->level,
        ])->with('category')->paginate(12);
    }

    public function render(): mixed
    {
        $member = auth()->user();
        $progress = LearningProgress::forMember($member);
        $modules = LearningModule::published()->availableTo($member)->with(['lessons', 'category'])->orderBy('sort_order')->get();

        return view('livewire.member.learn.index', [
            'progress' => $progress,
            'modules' => $modules->map(fn (LearningModule $module) => ['module' => $module, 'status' => LearningProgress::moduleStatus($module, $progress)])
                ->filter(fn (array $item) => $item['status']['required'] > 0),
            'resume' => UserProgress::where('user_id', $member->user_id)->where('status', 'in_progress')
                ->whereHas('content', fn ($query) => $query->live())->with('content')->latest('last_accessed_at')->first(),
            'categories' => ContentCategory::active()->get(),
            'filtering' => $this->search.$this->category.$this->type.$this->level !== '',
            'completedCount' => $progress->where('status', 'completed')->count(),
        ])->title(__('Learn'));
    }
}
