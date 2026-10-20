<?php

namespace App\Http\Controllers\API\V1;

use App\Models\CommunityPost;
use App\Models\CommunitySpace;
use App\Support\Community;
use App\Support\SafetyDesk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Moderated community for the mobile app (COM-REQ05-09, SAF-REQ01).
 * Replaces the old open forum endpoints.
 */
class CommunityController extends BaseController
{
    public function spaces(Request $request): JsonResponse
    {
        return $this->success(Community::spacesFor($request->user())->map(fn (CommunitySpace $space) => [
            'slug' => $space->slug, 'name' => $space->name, 'description' => $space->description, 'type' => $space->type,
            'status' => $space->status, 'posts_count' => $space->posts_count,
        ]));
    }

    public function posts(Request $request, string $slug): JsonResponse
    {
        $space = CommunitySpace::where('slug', $slug)->first();

        if (! $space || ! $space->isVisibleTo($request->user())) {
            return $this->error('Space not found.', 404);
        }

        $posts = $space->posts()->topLevel()->published()->with('author:user_id,full_name')->orderByDesc('last_activity_at')->paginate(20);
        $posts->getCollection()->transform(fn (CommunityPost $post) => $this->present($post));

        return $this->paginated($posts);
    }

    public function show(Request $request, string $postId): JsonResponse
    {
        $post = CommunityPost::published()->topLevel()->find($postId);

        if (! $post || ! $post->space->isVisibleTo($request->user())) {
            return $this->error('Post not found.', 404);
        }

        return $this->success([
            ...$this->present($post),
            'comments' => $post->comments()->published()->with('author:user_id,full_name')->get()->map(fn (CommunityPost $comment) => $this->present($comment)),
        ]);
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate(['title' => 'nullable|string|max:200', 'body' => 'required|string|max:5000']);
        $space = CommunitySpace::where('slug', $slug)->first();

        if (! $space) {
            return $this->error('Space not found.', 404);
        }

        try {
            $post = Community::post($space, $request->user(), $data['title'] ?? null, $data['body']);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success($this->present($post), $post->status === CommunityPost::HELD ? 'Waiting for a moderator.' : 'Posted.', 201);
    }

    public function comment(Request $request, string $postId): JsonResponse
    {
        $data = $request->validate(['body' => 'required|string|max:3000']);
        $post = CommunityPost::find($postId);

        if (! $post || ! $post->space->isVisibleTo($request->user())) {
            return $this->error('Post not found.', 404);
        }

        try {
            $comment = Community::comment($post, $request->user(), $data['body']);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success($this->present($comment), $comment->status === CommunityPost::HELD ? 'Waiting for a moderator.' : 'Posted.', 201);
    }

    public function destroy(Request $request, string $postId): JsonResponse
    {
        $post = CommunityPost::find($postId);

        if (! $post) {
            return $this->error('Post not found.', 404);
        }

        try {
            Community::delete($post, $request->user());
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 403);
        }

        return $this->success(null, 'Deleted.');
    }

    /**
     * POST /api/v1/reports {type, id, category, details?} (SAF-REQ01)
     */
    public function report(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => 'required|string', 'id' => 'required|string', 'category' => 'required|string', 'details' => 'nullable|string|max:2000']);

        try {
            $case = SafetyDesk::report($request->user(), $data['type'], $data['id'], $data['category'], $data['details'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(['reference' => $case->reference], 'Reported. Thank you.', 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CommunityPost $post): array
    {
        return [
            'post_id' => $post->post_id,
            'title' => $post->title,
            'body' => $post->body,
            'status' => $post->status,
            'author' => $post->author?->publicName(),
            'comments_count' => $post->comments_count,
            'created_at' => $post->created_at?->toIso8601String(),
        ];
    }
}
