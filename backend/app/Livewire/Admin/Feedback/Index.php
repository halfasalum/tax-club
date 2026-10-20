<?php

namespace App\Livewire\Admin\Feedback;

use App\Models\Feedback;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Feedback queue with filters (FBK-REQ02, FBK-REQ03).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $view = 'open';

    #[Url]
    public string $category = '';

    #[Url]
    public string $priority = '';

    #[Url]
    public string $search = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function feedback(): LengthAwarePaginator
    {
        $officer = auth()->user();
        $search = trim($this->search);

        return Feedback::visibleTo($officer)
            ->with(['user:user_id,full_name,user_type', 'assignee:user_id,full_name'])
            ->when($this->view === 'open', fn ($query) => $query->where('status', '!=', Feedback::CLOSED))
            ->when($this->view === 'new', fn ($query) => $query->where('status', Feedback::SUBMITTED))
            ->when($this->view === 'mine', fn ($query) => $query->where('assigned_to', $officer->user_id)->where('status', '!=', Feedback::CLOSED))
            ->when($this->view === 'closed', fn ($query) => $query->where('status', Feedback::CLOSED))
            ->when($this->category !== '', fn ($query) => $query->where('category', $this->category))
            ->when($this->priority !== '', fn ($query) => $query->where('priority', $this->priority))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('reference', 'like', "%{$search}%")->orWhere('subject', 'like', "%{$search}%")))
            ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")
            ->latest()
            ->paginate(25);
    }

    public function render(): mixed
    {
        $officer = auth()->user();

        return view('livewire.admin.feedback.index', [
            'counts' => [
                'new' => Feedback::visibleTo($officer)->where('status', Feedback::SUBMITTED)->count(),
                'mine' => Feedback::where('assigned_to', $officer->user_id)->where('status', '!=', Feedback::CLOSED)->count(),
            ],
        ])->title(__('Feedback'));
    }
}
