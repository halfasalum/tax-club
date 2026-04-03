<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Content;
use App\Models\UserProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserProgressController extends BaseController
{
    /**
     * GET /api/v1/progress
     * Authenticated user's full progress across all content.
     * Requires: auth:sanctum
     */
    public function index(Request $request): JsonResponse
    {
        $progress = UserProgress::with('content:content_id,title,content_type')
            ->where('user_id', $request->user()->user_id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderBy('last_accessed_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return $this->paginated($progress);
    }

    /**
     * POST /api/v1/progress
     * Upsert progress on a content item (create or update).
     * Called by mobile app as the student watches/reads.
     * Requires: auth:sanctum
     */
    public function upsert(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content_id'       => 'required|uuid|exists:content,content_id',
            'progress_percent' => 'required|integer|min:0|max:100',
            'status'           => 'sometimes|in:not_started,in_progress,completed',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $userId    = $request->user()->user_id;
        $contentId = $request->content_id;
        $percent   = $request->progress_percent;

        // Auto-derive status from percent if not explicitly provided
        $status = $request->status;
        if (! $status) {
            $status = match (true) {
                $percent === 0   => 'not_started',
                $percent === 100 => 'completed',
                default          => 'in_progress',
            };
        }

        $progress = UserProgress::where('user_id', $userId)
                                ->where('content_id', $contentId)
                                ->first();

        $now = now();

        if ($progress) {
            $progress->progress_percent = $percent;
            $progress->status           = $status;
            $progress->last_accessed_at = $now;

            if ($status === 'completed' && ! $progress->completed_at) {
                $progress->completed_at = $now;
            }

            $progress->save();
        } else {
            $progress = UserProgress::create([
                'user_id'          => $userId,
                'content_id'       => $contentId,
                'status'           => $status,
                'progress_percent' => $percent,
                'started_at'       => $now,
                'completed_at'     => $status === 'completed' ? $now : null,
                'last_accessed_at' => $now,
            ]);
        }

        return $this->success($progress, 'Progress updated.');
    }

    /**
     * GET /api/v1/progress/{contentId}
     * Get authenticated user's progress on a specific content item.
     * Requires: auth:sanctum
     */
    public function show(Request $request, string $contentId): JsonResponse
    {
        $content = Content::find($contentId);

        if (! $content) {
            return $this->error('Content not found.', 404);
        }

        $progress = UserProgress::where('user_id', $request->user()->user_id)
                                ->where('content_id', $contentId)
                                ->first();

        return $this->success([
            'content'  => $content->only(['content_id', 'title', 'content_type']),
            'progress' => $progress ?? ['status' => 'not_started', 'progress_percent' => 0],
        ]);
    }

    /**
     * GET /api/v1/progress/summary
     * Quick summary stats for the authenticated user's dashboard.
     * Requires: auth:sanctum
     */
    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->user_id;

        $total     = UserProgress::where('user_id', $userId)->count();
        $completed = UserProgress::where('user_id', $userId)->where('status', 'completed')->count();
        $inProgress = UserProgress::where('user_id', $userId)->where('status', 'in_progress')->count();

        return $this->success([
            'total_started'   => $total,
            'completed'       => $completed,
            'in_progress'     => $inProgress,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ]);
    }
}