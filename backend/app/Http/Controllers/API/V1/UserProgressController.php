<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Content;
use App\Models\UserProgress;
use App\Support\LearningProgress;
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
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
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
            'content_id' => 'required|uuid|exists:content,content_id',
            'progress_percent' => 'required|integer|min:0|max:100',
            'status' => 'sometimes|in:not_started,in_progress,completed',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $userId = $request->user()->user_id;
        $contentId = $request->content_id;
        $percent = $request->progress_percent;

        // Auto-derive status from percent if not explicitly provided
        $status = $request->status;
        if (! $status) {
            $status = match (true) {
                $percent === 0 => 'not_started',
                $percent === 100 => 'completed',
                default => 'in_progress',
            };
        }

        $lesson = Content::live()->availableTo($request->user())->find($contentId);

        if (! $lesson) {
            return $this->error('Content not available.', 404);
        }

        // Completion goes through the progress service so modules, points and badges follow.
        if ($status === 'completed') {
            LearningProgress::complete($request->user(), $lesson);
        } else {
            LearningProgress::open($request->user(), $lesson)->fill(['progress_percent' => max($percent, 1)])->save();
        }

        $progress = UserProgress::where('user_id', $userId)->where('content_id', $contentId)->first();

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
            'content' => $content->only(['content_id', 'title', 'content_type']),
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

        $total = UserProgress::where('user_id', $userId)->count();
        $completed = UserProgress::where('user_id', $userId)->where('status', 'completed')->count();
        $inProgress = UserProgress::where('user_id', $userId)->where('status', 'in_progress')->count();

        return $this->success([
            'total_started' => $total,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ]);
    }
}
