<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Content;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContentController extends BaseController
{
    /**
     * GET /api/v1/content
     * Students see active content. Admins/educators see all.
     * Requires: auth:sanctum
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->hasAnyRole(['admin', 'super_admin', 'educator']);

        $query = Content::with('uploader:user_id,full_name')
            ->when(! $isAdmin, fn($q) => $q->where('is_active', true))
            ->when($request->content_type, fn($q) => $q->where('content_type', $request->content_type))
            ->when($request->search, fn($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->orderBy('published_at', 'desc');

        return $this->paginated($query->paginate($request->per_page ?? 15));
    }

    /**
     * GET /api/v1/content/{id}
     * Requires: auth:sanctum
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $content = Content::with(['uploader:user_id,full_name', 'quiz'])->find($id);

        if (! $content) {
            return $this->error('Content not found.', 404);
        }

        if (! $content->is_active && ! $request->user()->hasAnyRole(['admin', 'super_admin', 'educator'])) {
            return $this->error('Content not available.', 403);
        }

        // Fetch this user's progress on this content
        $progress = $request->user()->progress()->where('content_id', $id)->first();

        return $this->success([
            'content'  => $content,
            'progress' => $progress,
        ]);
    }

    /**
     * POST /api/v1/content
     * Requires: upload_content
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'        => 'required|string|max:300',
            'content_type' => 'required|in:video,article,infographic,quiz',
            'file_url'     => 'nullable|url',
            'body_text'    => 'nullable|string',
            'published_at' => 'nullable|date',
            'is_active'    => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $content = Content::create([
            'title'        => $request->title,
            'content_type' => $request->content_type,
            'file_url'     => $request->file_url,
            'body_text'    => $request->body_text,
            'uploaded_by'  => $request->user()->user_id,
            'published_at' => $request->published_at ?? now(),
            'is_active'    => $request->boolean('is_active', true),
        ]);

        return $this->success($content, 'Content created successfully.', 201);
    }

    /**
     * PUT /api/v1/content/{id}
     * Requires: edit_content
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $content = Content::find($id);

        if (! $content) {
            return $this->error('Content not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'title'        => 'sometimes|string|max:300',
            'content_type' => 'sometimes|in:video,article,infographic,quiz',
            'file_url'     => 'nullable|url',
            'body_text'    => 'nullable|string',
            'published_at' => 'nullable|date',
            'is_active'    => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $content->fill($request->only(['title', 'content_type', 'file_url', 'body_text', 'published_at', 'is_active']))->save();

        return $this->success($content, 'Content updated successfully.');
    }

    /**
     * DELETE /api/v1/content/{id}
     * Requires: delete_content
     */
    public function destroy(string $id): JsonResponse
    {
        $content = Content::find($id);

        if (! $content) {
            return $this->error('Content not found.', 404);
        }

        $content->delete();

        return $this->success(null, 'Content deleted successfully.');
    }

    /**
     * PATCH /api/v1/content/{id}/toggle
     * Toggle active/inactive. Requires: edit_content
     */
    public function toggle(string $id): JsonResponse
    {
        $content = Content::find($id);

        if (! $content) {
            return $this->error('Content not found.', 404);
        }

        $content->is_active = ! $content->is_active;
        $content->save();

        $state = $content->is_active ? 'published' : 'unpublished';

        return $this->success($content, "Content {$state} successfully.");
    }
}