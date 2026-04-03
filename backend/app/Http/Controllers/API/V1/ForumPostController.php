<?php

namespace App\Http\Controllers\API\V1;

use App\Models\ForumPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ForumPostController extends BaseController
{
    /**
     * GET /api/v1/forum
     * Returns top-level posts (threads) with reply count.
     * Requires: auth:sanctum
     */
    public function index(Request $request): JsonResponse
    {
        $posts = ForumPost::with('author:user_id,full_name,membership_id')
            ->withCount('replies')
            ->topLevel()
            ->where('is_active', true)
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%")
                  ->orWhere('body', 'like', "%{$request->search}%");
            }))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return $this->paginated($posts);
    }

    /**
     * GET /api/v1/forum/{id}
     * Single post with nested replies (2 levels deep).
     * Requires: auth:sanctum
     */
    public function show(string $id): JsonResponse
    {
        $post = ForumPost::with([
            'author:user_id,full_name,membership_id',
            'replies.author:user_id,full_name,membership_id',
            'replies.replies.author:user_id,full_name,membership_id',
        ])->find($id);

        if (! $post || ! $post->is_active) {
            return $this->error('Post not found.', 404);
        }

        return $this->success($post);
    }

    /**
     * POST /api/v1/forum
     * Create a new top-level post. Requires: create_forum_post
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:300',
            'body'  => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $post = ForumPost::create([
            'user_id' => $request->user()->user_id,
            'title'   => $request->title,
            'body'    => $request->body,
        ]);

        return $this->success($post->load('author:user_id,full_name'), 'Post created successfully.', 201);
    }

    /**
     * POST /api/v1/forum/{id}/reply
     * Reply to a post or another reply. Requires: auth:sanctum
     */
    public function reply(Request $request, string $id): JsonResponse
    {
        $parent = ForumPost::find($id);

        if (! $parent || ! $parent->is_active) {
            return $this->error('Post not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'body' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $reply = ForumPost::create([
            'user_id'        => $request->user()->user_id,
            'parent_post_id' => $parent->post_id,
            'body'           => $request->body,
        ]);

        return $this->success($reply->load('author:user_id,full_name'), 'Reply posted successfully.', 201);
    }

    /**
     * PUT /api/v1/forum/{id}
     * Edit own post. Users can only edit their own; admins can edit any.
     * Requires: auth:sanctum
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $post = ForumPost::find($id);

        if (! $post) {
            return $this->error('Post not found.', 404);
        }

        $user = $request->user();

        // Only the author or an admin may edit
        if ($post->user_id !== $user->user_id && ! $user->hasAnyRole(['admin', 'super_admin'])) {
            return $this->error('You are not authorised to edit this post.', 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:300',
            'body'  => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $post->fill($request->only(['title', 'body']))->save();

        return $this->success($post, 'Post updated successfully.');
    }

    /**
     * DELETE /api/v1/forum/{id}
     * Soft-delete (set is_active = false). Requires: delete_forum_post or own post.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $post = ForumPost::find($id);

        if (! $post) {
            return $this->error('Post not found.', 404);
        }

        $user = $request->user();

        if ($post->user_id !== $user->user_id && ! $user->hasAnyRole(['admin', 'super_admin'])) {
            return $this->error('You are not authorised to delete this post.', 403);
        }

        $post->is_active = false;
        $post->save();

        return $this->success(null, 'Post removed successfully.');
    }
}