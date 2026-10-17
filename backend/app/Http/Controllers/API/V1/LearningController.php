<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Content;
use App\Models\LearningModule;
use App\Models\UserProgress;
use App\Support\LearningProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Learning modules and lesson progress for the mobile app (LRN-REQ05/06).
 */
class LearningController extends BaseController
{
    /**
     * GET /api/v1/learning — modules with progress and the lesson to resume.
     */
    public function index(Request $request): JsonResponse
    {
        $member = $request->user();
        $progress = LearningProgress::forMember($member);
        $resume = UserProgress::where('user_id', $member->user_id)->where('status', 'in_progress')
            ->whereHas('content', fn ($query) => $query->live())->with('content')->latest('last_accessed_at')->first();

        return $this->success([
            'resume' => $resume ? ['content_id' => $resume->content_id, 'title' => $resume->content->title] : null,
            'completed_lessons' => $progress->where('status', 'completed')->count(),
            'modules' => LearningModule::published()->availableTo($member)->with(['lessons', 'category'])->orderBy('sort_order')->get()
                ->map(fn (LearningModule $module) => $this->moduleSummary($module, $progress))
                ->filter(fn (array $module) => $module['required_lessons'] > 0)
                ->values(),
        ]);
    }

    /**
     * GET /api/v1/learning/modules/{id}
     */
    public function module(Request $request, int $id): JsonResponse
    {
        $member = $request->user();
        $module = LearningModule::published()->availableTo($member)->with(['lessons', 'category'])->find($id);

        if (! $module) {
            return $this->error('Module not found.', 404);
        }

        $progress = LearningProgress::forMember($member);

        return $this->success($this->moduleSummary($module, $progress) + [
            'lessons' => $module->lessons->filter(fn (Content $lesson) => $lesson->isLive() && $lesson->isFor($member))->values()->map(fn (Content $lesson) => [
                'content_id' => $lesson->content_id,
                'title' => $lesson->title,
                'content_type' => $lesson->content_type,
                'estimated_minutes' => $lesson->estimated_minutes,
                'is_required' => (bool) $lesson->pivot->is_required,
                'status' => $progress->get($lesson->content_id)?->status ?? 'not_started',
            ]),
        ]);
    }

    /**
     * POST /api/v1/content/{id}/open
     */
    public function open(Request $request, string $id): JsonResponse
    {
        $lesson = Content::live()->availableTo($request->user())->find($id);

        if (! $lesson) {
            return $this->error('Content not found.', 404);
        }

        return $this->success(LearningProgress::open($request->user(), $lesson));
    }

    /**
     * POST /api/v1/content/{id}/complete
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $lesson = Content::live()->availableTo($request->user())->find($id);

        if (! $lesson) {
            return $this->error('Content not found.', 404);
        }

        $firstTime = LearningProgress::complete($request->user(), $lesson);

        return $this->success([
            'first_time' => $firstTime,
            'progress' => $request->user()->progress()->where('content_id', $id)->first(),
        ], $firstTime ? 'Lesson completed.' : 'Lesson was already completed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function moduleSummary(LearningModule $module, Collection $progress): array
    {
        $status = LearningProgress::moduleStatus($module, $progress);

        return [
            'id' => $module->id,
            'slug' => $module->slug,
            'title' => $module->title,
            'summary' => $module->summary,
            'level' => $module->level,
            'category' => $module->category?->name(),
            'required_lessons' => $status['required'],
            'completed_lessons' => $status['done'],
            'percent' => $status['percent'],
            'is_complete' => $status['complete'],
        ];
    }
}
