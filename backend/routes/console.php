<?php

use App\Support\ContentWorkflow;
use App\Support\EventManager;
use App\Support\QuizEngine;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('members:refresh-age-bands')->dailyAt('00:15');
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::call(fn () => ContentWorkflow::applySchedule())->name('content:apply-schedule')->everyFiveMinutes()->withoutOverlapping();
Schedule::call(fn () => QuizEngine::closeOverdue())->name('quizzes:close-overdue')->everyMinute()->withoutOverlapping();
Schedule::call(fn () => EventManager::applySchedule())->name('events:apply-schedule')->everyFiveMinutes()->withoutOverlapping();
