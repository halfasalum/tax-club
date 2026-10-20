<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Opportunity;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Notifications\EventReminder;
use App\Notifications\OpportunityDeadlineReminder;
use App\Notifications\QuizClosingReminder;
use Illuminate\Support\Facades\DB;

/**
 * Automatic reminders for time-bound activities (NOT-REQ04):
 *
 *  - registered members, before an event starts
 *  - members who saved an opportunity, before its deadline
 *  - members with an unfinished quiz attempt, before the quiz closes
 *
 * Administrators turn each one on or off and set how early it goes out.
 * Each reminder is sent to a member once.
 */
final class Reminders
{
    /**
     * Defaults: [enabled, lead time].
     *
     * @var array<string, array{enabled: bool, lead: int, unit: string}>
     */
    public const DEFAULTS = [
        'event' => ['enabled' => true, 'lead' => 24, 'unit' => 'hours'],
        'opportunity' => ['enabled' => true, 'lead' => 3, 'unit' => 'days'],
        'quiz' => ['enabled' => true, 'lead' => 24, 'unit' => 'hours'],
    ];

    /**
     * @return array<string, array{enabled: bool, lead: int, unit: string}>
     */
    public static function settings(): array
    {
        return collect(self::DEFAULTS)->map(fn (array $default, string $kind) => [
            'enabled' => (bool) AppSetting::get("reminders.{$kind}.enabled", $default['enabled']),
            'lead' => (int) AppSetting::get("reminders.{$kind}.lead", $default['lead']),
            'unit' => $default['unit'],
        ])->all();
    }

    public static function configure(string $kind, bool $enabled, int $lead, User $by): void
    {
        if (! array_key_exists($kind, self::DEFAULTS)) {
            return;
        }

        $before = self::settings()[$kind];
        $lead = max(1, min($kind === 'opportunity' ? 30 : 168, $lead));
        AppSetting::put("reminders.{$kind}.enabled", $enabled, $by);
        AppSetting::put("reminders.{$kind}.lead", $lead, $by);
        Audit::record('reminders.updated', null, [$kind => $before], [$kind => ['enabled' => $enabled, 'lead' => $lead]], actor: $by);
    }

    /**
     * Send what is due. Run hourly by the scheduler.
     *
     * @return array<string, int>
     */
    public static function run(): array
    {
        $settings = self::settings();

        return [
            'event' => $settings['event']['enabled'] ? self::events($settings['event']['lead']) : 0,
            'opportunity' => $settings['opportunity']['enabled'] ? self::opportunities($settings['opportunity']['lead']) : 0,
            'quiz' => $settings['quiz']['enabled'] ? self::quizzes($settings['quiz']['lead']) : 0,
        ];
    }

    private static function events(int $hours): int
    {
        $sent = 0;

        Event::where('publication_status', Event::PUBLISHED)->whereBetween('starts_at', [now(), now()->addHours($hours)])->each(function (Event $event) use (&$sent) {
            $event->registrations()->where('status', EventRegistration::REGISTERED)->with('user')->each(function (EventRegistration $registration) use ($event, &$sent) {
                if (self::claim('event', $event->event_id, $registration->user)) {
                    Notify::send($registration->user, new EventReminder($event->title, EventManager::when($event), EventManager::place($event), $event->slug));
                    $sent++;
                }
            });
        });

        return $sent;
    }

    private static function opportunities(int $days): int
    {
        $sent = 0;

        Opportunity::open()->whereNotNull('deadline')->where('deadline', '<=', now()->addDays($days)->toDateString())->each(function (Opportunity $opportunity) use (&$sent) {
            User::whereIn('user_id', DB::table('opportunity_saves')->where('opportunity_id', $opportunity->opportunity_id)->select('user_id'))
                ->where('account_status', User::STATUS_ACTIVE)
                ->each(function (User $member) use ($opportunity, &$sent) {
                    if (self::claim('opportunity', $opportunity->opportunity_id, $member)) {
                        Notify::send($member, new OpportunityDeadlineReminder($opportunity->title, $opportunity->deadline->translatedFormat('j M Y'), $opportunity->slug));
                        $sent++;
                    }
                });
        });

        return $sent;
    }

    private static function quizzes(int $hours): int
    {
        $sent = 0;

        Quiz::open()->whereNotNull('closes_at')->where('closes_at', '<=', now()->addHours($hours))->each(function (Quiz $quiz) use (&$sent) {
            QuizAttempt::where('quiz_id', $quiz->quiz_id)->where('status', QuizAttempt::IN_PROGRESS)->with('user')->each(function (QuizAttempt $attempt) use ($quiz, &$sent) {
                if ($attempt->user && self::claim('quiz', $quiz->quiz_id, $attempt->user)) {
                    Notify::send($attempt->user, new QuizClosingReminder($quiz->title, $quiz->closes_at->translatedFormat('D j M, H:i'), $quiz->quiz_id));
                    $sent++;
                }
            });
        });

        return $sent;
    }

    /**
     * Record the reminder; false if this member already had it.
     */
    private static function claim(string $kind, string $subjectId, User $member): bool
    {
        return DB::table('reminders_sent')->insertOrIgnore(['kind' => $kind, 'subject_id' => $subjectId, 'user_id' => $member->user_id, 'created_at' => now()]) > 0;
    }
}
