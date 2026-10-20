<?php

namespace App\Livewire\Member\Community;

use App\Models\CommunityPost;
use App\Models\CommunitySpace;
use App\Support\Community;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Posts in one space, newest activity first, and the form to write one
 * (COM-REQ06).
 */
#[Layout('components.layouts.app')]
class Space extends Component
{
    use WithPagination;

    public CommunitySpace $space;

    public string $title = '';

    public string $body = '';

    public ?string $statusMessage = null;

    public function mount(CommunitySpace $space): void
    {
        abort_unless($space->isVisibleTo(auth()->user()), 404);
        $this->space = $space;
    }

    public function publish(): void
    {
        $this->validate(['title' => 'nullable|string|max:200', 'body' => 'required|string|max:5000']);

        try {
            $post = Community::post($this->space, auth()->user(), $this->title, $this->body);
        } catch (InvalidArgumentException $exception) {
            $this->addError('body', $exception->getMessage());

            return;
        }

        $this->reset('title', 'body');
        $this->statusMessage = $post->status === CommunityPost::HELD
            ? __('Thanks! Your post will appear after a moderator checks it.')
            : __('Posted.');
        $this->resetPage();
        unset($this->posts);
    }

    #[Computed]
    public function posts(): LengthAwarePaginator
    {
        $userId = auth()->id();

        return $this->space->posts()
            ->topLevel()
            ->where(fn ($query) => $query->where('status', CommunityPost::PUBLISHED)->orWhere(fn ($query) => $query->where('user_id', $userId)->where('status', CommunityPost::HELD)))
            ->with('author:user_id,full_name,user_type,institution_id', 'author.institution:institution_id,name')
            ->orderByDesc('last_activity_at')
            ->paginate(15);
    }

    public function render(): mixed
    {
        return view('livewire.member.community.space', [
            'problem' => Community::postingProblem($this->space, auth()->user()),
        ])->title($this->space->name);
    }
}
