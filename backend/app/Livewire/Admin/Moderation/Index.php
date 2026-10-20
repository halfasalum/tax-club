<?php

namespace App\Livewire\Admin\Moderation;

use App\Models\ModerationCase;
use App\Support\Reportables;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The moderation queue (SAF-REQ02, COM-REQ08).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $view = 'open';

    #[Url]
    public string $category = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function cases(): LengthAwarePaginator
    {
        $officer = auth()->user();

        return ModerationCase::visibleTo($officer)
            ->with(['subjectUser:user_id,full_name,age_band', 'assignee:user_id,full_name'])
            ->when($this->view === 'open', fn ($query) => $query->where('status', '!=', ModerationCase::CLOSED))
            ->when($this->view === 'safeguarding', fn ($query) => $query->where('safeguarding', true)->where('status', '!=', ModerationCase::CLOSED))
            ->when($this->view === 'held', fn ($query) => $query->where('source', 'filter')->where('status', '!=', ModerationCase::CLOSED))
            ->when($this->view === 'mine', fn ($query) => $query->where('assigned_to', $officer->user_id)->where('status', '!=', ModerationCase::CLOSED))
            ->when($this->view === 'closed', fn ($query) => $query->where('status', ModerationCase::CLOSED))
            ->when($this->category !== '', fn ($query) => $query->where('category', $this->category))
            ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")
            ->oldest()
            ->paginate(25);
    }

    public function render(): mixed
    {
        $officer = auth()->user();

        return view('livewire.admin.moderation.index', [
            'counts' => [
                'open' => ModerationCase::visibleTo($officer)->where('status', '!=', ModerationCase::CLOSED)->count(),
                'safeguarding' => ModerationCase::visibleTo($officer)->where('safeguarding', true)->where('status', '!=', ModerationCase::CLOSED)->count(),
                'held' => ModerationCase::visibleTo($officer)->where('source', 'filter')->where('status', '!=', ModerationCase::CLOSED)->count(),
            ],
            'canSafeguard' => $officer->hasControl('handle_safeguarding_concerns'),
            'describe' => fn (ModerationCase $case) => Reportables::describe($case->subject_type, $case->subject_id),
        ])->title(__('Moderation'));
    }
}
