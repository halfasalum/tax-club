<?php

namespace App\Livewire\Admin\Broadcasts;

use App\Models\Broadcast;
use App\Support\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Broadcast and targeted notifications (NOT-REQ03).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $view = 'all';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function broadcasts(): LengthAwarePaginator
    {
        return Broadcast::query()
            ->when($this->view === 'review', fn ($query) => $query->where('status', Review::IN_REVIEW))
            ->when($this->view === 'drafts', fn ($query) => $query->whereIn('status', Review::EDITABLE))
            ->when($this->view === 'scheduled', fn ($query) => $query->whereIn('status', [Review::APPROVED, Broadcast::SCHEDULED]))
            ->when($this->view === 'sent', fn ($query) => $query->whereIn('status', [Broadcast::SENDING, Broadcast::SENT, Broadcast::CANCELLED]))
            ->latest('updated_at')
            ->paginate(20);
    }

    public function render(): mixed
    {
        $user = auth()->user();

        return view('livewire.admin.broadcasts.index', [
            'waiting' => Broadcast::where('status', Review::IN_REVIEW)->count(),
            'canCreate' => $user->hasControl('create_broadcast'),
            'canRemind' => $user->hasControl('manage_reminders'),
        ])->title(__('Broadcasts'));
    }
}
