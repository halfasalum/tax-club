<?php

namespace App\Listeners;

use App\Events\LessonCompleted;
use App\Events\ModuleCompleted;
use App\Events\QuizAttemptSubmitted;
use App\Support\Certificates;
use App\Support\Gamification;
use Illuminate\Events\Dispatcher;

/**
 * Turns learning activity into points, badges and module certificates
 * (GAM-REQ08/09, CER-REQ01).
 */
class AwardLearningPoints
{
    public function lesson(LessonCompleted $event): void
    {
        Gamification::lessonCompleted($event->member, $event->lesson);
    }

    public function module(ModuleCompleted $event): void
    {
        Gamification::moduleCompleted($event->member, $event->module);
        Certificates::forModule($event->member, $event->module);
    }

    public function quiz(QuizAttemptSubmitted $event): void
    {
        Gamification::quizSubmitted($event->member, $event->attempt);
        Gamification::evaluateBadges($event->member);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            LessonCompleted::class => 'lesson',
            ModuleCompleted::class => 'module',
            QuizAttemptSubmitted::class => 'quiz',
        ];
    }
}
