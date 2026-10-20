<?php

namespace App\Livewire\Member\Community;

use App\Models\CommunityPost;
use App\Support\Community;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A post with its comments (COM-REQ06).
 */
#[Layout('components.layouts.app')]
class Post extends Component
{
    public CommunityPost $post;

    public string $comment = '';

    public bool $editing = false;

    public string $editTitle = '';

    public string $editBody = '';

    public ?string $statusMessage = null;

    public function mount(CommunityPost $post): void
    {
        $user = auth()->user();
        abort_unless($post->parent_id === null && $post->space->isVisibleTo($user), 404);
        abort_unless($post->status === CommunityPost::PUBLISHED || ($post->user_id === $user->user_id && $post->status === CommunityPost::HELD) || $post->space->isModerator($user), 404);
        $this->post = $post;
    }

    public function addComment(): void
    {
        $this->validate(['comment' => 'required|string|max:3000']);

        try {
            $comment = Community::comment($this->post, auth()->user(), $this->comment);
        } catch (InvalidArgumentException $exception) {
            $this->addError('comment', $exception->getMessage());

            return;
        }

        $this->comment = '';
        $this->statusMessage = $comment->status === CommunityPost::HELD ? __('Thanks! Your comment will appear after a moderator checks it.') : null;
    }

    public function startEdit(): void
    {
        $this->editing = true;
        $this->editTitle = (string) $this->post->title;
        $this->editBody = $this->post->body;
    }

    public function saveEdit(): void
    {
        $this->validate(['editTitle' => 'nullable|string|max:200', 'editBody' => 'required|string|max:5000']);

        try {
            Community::edit($this->post, auth()->user(), $this->editTitle, $this->editBody);
        } catch (InvalidArgumentException $exception) {
            $this->addError('editBody', $exception->getMessage());

            return;
        }

        $this->editing = false;
    }

    public function deletePost(string $postId): mixed
    {
        $target = CommunityPost::where('post_id', $postId)->where(fn ($query) => $query->where('post_id', $this->post->post_id)->orWhere('parent_id', $this->post->post_id))->firstOrFail();

        try {
            Community::delete($target, auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('comment', $exception->getMessage());

            return null;
        }

        return $target->is($this->post) ? $this->redirectRoute('member.community.space', $this->post->space, navigate: true) : null;
    }

    public function render(): mixed
    {
        $this->post->refresh();
        $user = auth()->user();

        return view('livewire.member.community.post', [
            'comments' => $this->post->comments()
                ->where(fn ($query) => $query->where('status', CommunityPost::PUBLISHED)->orWhere(fn ($query) => $query->where('user_id', $user->user_id)->where('status', CommunityPost::HELD)))
                ->with('author:user_id,full_name,user_type,institution_id', 'author.institution:institution_id,name')->get(),
            'isModerator' => $this->post->space->isModerator($user),
            'problem' => Community::postingProblem($this->post->space, $user),
        ])->title($this->post->title ?: __('Post'));
    }
}
