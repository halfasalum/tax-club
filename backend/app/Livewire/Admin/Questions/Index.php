<?php

namespace App\Livewire\Admin\Questions;

use App\Models\Question;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Ask TRA queue (COM-REQ02-04).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $view = 'new';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        if (! auth()->user()->hasControl('triage_questions') && auth()->user()->hasControl('answer_questions')) {
            $this->view = $this->view === 'new' ? 'mine' : $this->view;
        }
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function questions(): LengthAwarePaginator
    {
        $user = auth()->user();
        $search = trim($this->search);

        return Question::query()
            ->with(['asker:user_id,full_name,user_type', 'expert:user_id,full_name', 'category'])
            ->when($this->view === 'new', fn ($query) => $query->where('status', Question::SUBMITTED))
            ->when($this->view === 'mine', fn ($query) => $query->where('assigned_to', $user->user_id)->whereIn('status', [Question::ASSIGNED, Question::RETURNED]))
            ->when($this->view === 'assigned', fn ($query) => $query->whereIn('status', [Question::ASSIGNED, Question::RETURNED]))
            ->when($this->view === 'review', fn ($query) => $query->where('status', Question::IN_REVIEW))
            ->when($this->view === 'answered', fn ($query) => $query->where('status', Question::ANSWERED))
            ->when($this->view === 'published', fn ($query) => $query->whereNotNull('published_at'))
            ->when($this->view === 'declined', fn ($query) => $query->where('status', Question::DECLINED))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('reference', 'like', "%{$search}%")->orWhere('subject', 'like', "%{$search}%")))
            ->oldest()
            ->paginate(25);
    }

    public function render(): mixed
    {
        return view('livewire.admin.questions.index', [
            'counts' => [
                'new' => Question::where('status', Question::SUBMITTED)->count(),
                'mine' => Question::where('assigned_to', auth()->id())->whereIn('status', [Question::ASSIGNED, Question::RETURNED])->count(),
                'review' => Question::where('status', Question::IN_REVIEW)->count(),
            ],
        ])->title(__('Ask TRA'));
    }
}
