<?php

namespace App\Livewire\Admin\Quizzes;

use App\Models\Quiz;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Quizzes in every state (GAM-REQ01-04).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $view = 'all';

    #[Url]
    public string $search = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function quizzes(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Quiz::query()
            ->with('category')
            ->withCount(['questions', 'attempts'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('title', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->when($this->view === 'review', fn ($query) => $query->where('editorial_status', Quiz::IN_REVIEW))
            ->when($this->view === 'drafts', fn ($query) => $query->whereIn('editorial_status', Quiz::EDITABLE))
            ->when($this->view === 'open', fn ($query) => $query->open())
            ->when($this->view === 'scheduled', fn ($query) => $query->where('publication_status', Quiz::SCHEDULED)->where('opens_at', '>', now()))
            ->when($this->view === 'closed', fn ($query) => $query->where(fn ($query) => $query->whereIn('publication_status', [Quiz::CLOSED, Quiz::ARCHIVED])->orWhere('closes_at', '<=', now())))
            ->latest('updated_at')
            ->paginate(20);
    }

    public function render(): mixed
    {
        return view('livewire.admin.quizzes.index', [
            'waiting' => Quiz::where('editorial_status', Quiz::IN_REVIEW)->count(),
            'canCreate' => auth()->user()->hasControl('create_quiz'),
        ])->title(__('Quizzes'));
    }
}
