<?php

namespace App\Livewire\Admin\Events;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Support\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Events and campaigns in every state (EVT-REQ01-03, EVT-REQ08).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $view = 'upcoming';

    #[Url]
    public string $search = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function events(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Event::query()
            ->withCount([
                'registrations as registered_count' => fn ($query) => $query->holdingPlace(),
                'registrations as attended_count' => fn ($query) => $query->where('status', EventRegistration::ATTENDED),
                'registrations as waitlisted_count' => fn ($query) => $query->where('status', EventRegistration::WAITLISTED),
            ])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('title', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('location', 'like', "%{$search}%")))
            ->when($this->view === 'upcoming', fn ($query) => $query->upcoming())
            ->when($this->view === 'review', fn ($query) => $query->where('editorial_status', Review::IN_REVIEW))
            ->when($this->view === 'drafts', fn ($query) => $query->whereIn('editorial_status', Review::EDITABLE)->where('publication_status', Event::NOT_PUBLISHED))
            ->when($this->view === 'ready', fn ($query) => $query->where('editorial_status', Review::APPROVED)->whereIn('publication_status', [Event::NOT_PUBLISHED, Event::SCHEDULED]))
            ->when($this->view === 'past', fn ($query) => $query->whereIn('publication_status', [Event::PUBLISHED, Event::CANCELLED, Event::POSTPONED])->where('starts_at', '<', now()))
            ->when($this->view === 'closed', fn ($query) => $query->whereIn('publication_status', [Event::CLOSED, Event::ARCHIVED]))
            ->orderBy($this->view === 'upcoming' ? 'starts_at' : 'updated_at', $this->view === 'upcoming' ? 'asc' : 'desc')
            ->paginate(20);
    }

    public function render(): mixed
    {
        return view('livewire.admin.events.index', [
            'waiting' => Event::where('editorial_status', Review::IN_REVIEW)->count(),
            'canCreate' => auth()->user()->hasControl('add_event'),
        ])->title(__('Events'));
    }
}
