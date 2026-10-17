<?php

namespace App\Livewire\Member;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * In-app notification inbox (NOT-REQ06).
 */
#[Layout('components.layouts.app')]
class Inbox extends Component
{
    use WithPagination;

    public bool $unreadOnly = false;

    public function updatedUnreadOnly(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function notifications(): LengthAwarePaginator
    {
        return auth()->user()->notifications()
            ->when($this->unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->paginate(20);
    }

    public function open(string $id): mixed
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        if ($url = $notification->data['url'] ?? null) {
            return $this->redirect($url);
        }

        unset($this->notifications);

        return null;
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        unset($this->notifications);
    }

    public function render(): mixed
    {
        return view('livewire.member.inbox', [
            'unread' => auth()->user()->unreadNotifications()->count(),
        ])->title(__('Notifications'));
    }
}
