<?php

namespace App\Support;

use App\Events\LessonCompleted;
use App\Events\ModuleCompleted;
use App\Models\Content;
use App\Models\LearningModule;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A member's progress through lessons and modules (LRN-REQ06). Progress
 * remembers which version of a lesson was completed.
 */
final class LearningProgress
{
    /**
     * Record that a member opened a lesson (for "continue where you left off").
     */
    public static function open(User $member, Content $lesson): UserProgress
    {
        $progress = UserProgress::firstOrNew(['user_id' => $member->user_id, 'content_id' => $lesson->content_id]);

        if (! $progress->exists || $progress->status === 'not_started') {
            $progress->fill(['status' => 'in_progress', 'progress_percent' => max(10, (int) $progress->progress_percent), 'started_at' => now()]);
        }

        $progress->fill(['last_accessed_at' => now()])->save();

        return $progress;
    }

    /**
     * Mark a lesson complete. Returns true the first time.
     */
    public static function complete(User $member, Content $lesson): bool
    {
        $firstTime = DB::transaction(function () use ($member, $lesson) {
            $progress = UserProgress::lockForUpdate()->firstOrNew(['user_id' => $member->user_id, 'content_id' => $lesson->content_id]);
            $firstTime = $progress->status !== 'completed';

            $progress->fill([
                'status' => 'completed',
                'progress_percent' => 100,
                'content_version' => $lesson->current_version,
                'started_at' => $progress->started_at ?? now(),
                'completed_at' => $firstTime ? now() : $progress->completed_at,
                'last_accessed_at' => now(),
            ])->save();

            return $firstTime;
        });

        if ($firstTime) {
            LessonCompleted::dispatch($member, $lesson);
            self::checkModules($member, $lesson);
        }

        return $firstTime;
    }

    /**
     * Completed lesson ids for a member.
     *
     * @return Collection<string, UserProgress>
     */
    public static function forMember(User $member): Collection
    {
        return UserProgress::where('user_id', $member->user_id)->get()->keyBy('content_id');
    }

    /**
     * @return array{required: int, done: int, percent: int, complete: bool}
     */
    public static function moduleStatus(LearningModule $module, Collection $progress): array
    {
        $required = $module->lessons->filter(fn (Content $lesson) => $lesson->pivot->is_required && $lesson->isLive());
        $done = $required->filter(fn (Content $lesson) => $progress->get($lesson->content_id)?->status === 'completed')->count();

        return [
            'required' => $required->count(),
            'done' => $done,
            'percent' => $required->count() ? (int) round($done / $required->count() * 100) : 0,
            'complete' => $required->count() > 0 && $done === $required->count(),
        ];
    }

    private static function checkModules(User $member, Content $lesson): void
    {
        $progress = self::forMember($member);

        LearningModule::published()
            ->whereHas('lessons', fn ($query) => $query->where('content.content_id', $lesson->content_id))
            ->with('lessons')
            ->get()
            ->each(function (LearningModule $module) use ($member, $progress) {
                if (! self::moduleStatus($module, $progress)['complete']) {
                    return;
                }

                $inserted = DB::table('module_completions')->insertOrIgnore([
                    'user_id' => $member->user_id,
                    'module_id' => $module->id,
                    'completed_at' => now(),
                ]);

                if ($inserted) {
                    ModuleCompleted::dispatch($member, $module);
                }
            });
    }
}
