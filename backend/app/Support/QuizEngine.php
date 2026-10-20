<?php

namespace App\Support;

use App\Events\QuizAttemptSubmitted;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\QuizUserResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Taking a quiz (GAM-REQ05-07). The server decides when an attempt starts
 * and ends; answers are saved as the member goes; time limits and attempt
 * limits are enforced here, not in the browser or app.
 */
final class QuizEngine
{
    /** Seconds allowed after the deadline for slow connections. */
    public const GRACE_SECONDS = 30;

    /**
     * @return array{can_start: bool, reason: ?string, attempts_used: int, attempts_left: ?int, in_progress: ?QuizAttempt}
     */
    public static function availability(User $member, Quiz $quiz): array
    {
        $inProgress = self::openAttempt($member, $quiz);
        $used = $quiz->attempts()->where('user_id', $member->user_id)->where('status', QuizAttempt::SUBMITTED)->count();
        $left = $quiz->max_attempts ? max(0, $quiz->max_attempts - $used) : null;

        $reason = match (true) {
            $inProgress !== null => null,
            ! $quiz->isAvailableTo($member) => $quiz->hasEnded() ? __('This quiz has closed.') : __('This quiz is not open to you.'),
            $left === 0 => __('You have used all your attempts.'),
            default => null,
        };

        return [
            'can_start' => $reason === null,
            'reason' => $reason,
            'attempts_used' => $used,
            'attempts_left' => $left,
            'in_progress' => $inProgress,
        ];
    }

    /**
     * Start a new attempt, or resume the one already running.
     */
    public static function start(User $member, Quiz $quiz): QuizAttempt
    {
        return DB::transaction(function () use ($member, $quiz) {
            User::whereKey($member->user_id)->lockForUpdate()->first();

            $status = self::availability($member, $quiz);

            if ($status['in_progress']) {
                return $status['in_progress'];
            }

            if (! $status['can_start']) {
                throw new InvalidArgumentException($status['reason']);
            }

            $questions = $quiz->questions()->where('is_active', true)->get();
            $order = $questions->pluck('question_id');
            $expiresAt = $quiz->time_limit_minutes ? now()->addMinutes($quiz->time_limit_minutes) : null;

            if ($quiz->closes_at && ($expiresAt === null || $quiz->closes_at->lt($expiresAt))) {
                $expiresAt = $quiz->closes_at->copy();
            }

            return QuizAttempt::create([
                'user_id' => $member->user_id,
                'quiz_id' => $quiz->quiz_id,
                'status' => QuizAttempt::IN_PROGRESS,
                'attempt_number' => $status['attempts_used'] + 1,
                'question_order' => ($quiz->shuffle_questions ? $order->shuffle() : $order)->values()->all(),
                'score' => 0,
                'max_score' => $questions->sum('points'),
                'total_points_earned' => 0,
                'is_best_attempt' => false,
                'started_at' => now(),
                'attempted_at' => now(),
                'expires_at' => $expiresAt,
            ]);
        });
    }

    /**
     * Save the member's choice for one question (autosave).
     *
     * @param  list<string>  $answerIds
     */
    public static function saveAnswer(QuizAttempt $attempt, string $questionId, array $answerIds): void
    {
        self::ensureRunning($attempt);

        if (! in_array($questionId, $attempt->question_order ?? [], true)) {
            throw new InvalidArgumentException(__('This question is not part of your attempt.'));
        }

        $question = QuizQuestion::with('answers')->findOrFail($questionId);
        $valid = $question->answers->pluck('answer_id')->intersect($answerIds)->values()->all();

        if ($question->question_type !== QuizQuestion::MULTIPLE_SELECT) {
            $valid = array_slice($valid, 0, 1);
        }

        QuizUserResponse::updateOrCreate(
            ['attempt_id' => $attempt->attempt_id, 'question_id' => $questionId],
            ['selected_answer_ids' => $valid, 'answer_id' => $valid[0] ?? null, 'is_correct' => false, 'points_earned' => 0],
        );
    }

    /**
     * Score and close the attempt. Late attempts are closed with the
     * answers saved before the deadline.
     */
    public static function submit(QuizAttempt $attempt, bool $automatic = false): QuizAttempt
    {
        $submitted = DB::transaction(function () use ($attempt, $automatic) {
            $attempt = QuizAttempt::lockForUpdate()->findOrFail($attempt->attempt_id);

            if ($attempt->status === QuizAttempt::SUBMITTED) {
                return null;
            }

            $quiz = $attempt->quiz()->with('questions.answers')->first();
            $responses = QuizUserResponse::where('attempt_id', $attempt->attempt_id)->get()->keyBy('question_id');
            $score = 0;

            foreach ($quiz->questions->whereIn('question_id', $attempt->question_order ?? []) as $question) {
                $response = $responses->get($question->question_id);
                $chosen = collect($response?->selected_answer_ids ?? [])->sort()->values()->all();
                $correct = $question->answers->where('is_correct', true)->pluck('answer_id')->sort()->values()->all();
                $isCorrect = $chosen !== [] && $chosen === $correct;
                $earned = $isCorrect ? $question->points : 0;
                $score += $earned;

                QuizUserResponse::updateOrCreate(
                    ['attempt_id' => $attempt->attempt_id, 'question_id' => $question->question_id],
                    ['selected_answer_ids' => $chosen, 'answer_id' => $chosen[0] ?? null, 'is_correct' => $isCorrect, 'points_earned' => $earned],
                );
            }

            $percent = $attempt->max_score > 0 ? round($score / $attempt->max_score * 100, 2) : 0;
            $endedAt = $attempt->expires_at && now()->gt($attempt->expires_at) ? $attempt->expires_at : now();

            $attempt->forceFill([
                'status' => QuizAttempt::SUBMITTED,
                'score' => $score,
                'total_points_earned' => $score,
                'percent' => $percent,
                'passed' => $percent >= $quiz->pass_mark,
                'submitted_at' => now(),
                'auto_submitted' => $automatic || self::isOverdue($attempt),
                'time_spent_seconds' => (int) $attempt->started_at->diffInSeconds($endedAt),
            ])->save();

            self::markResultOfRecord($attempt, $quiz);

            return $attempt;
        });

        if ($submitted) {
            QuizAttemptSubmitted::dispatch($submitted->user, $submitted->fresh());

            return $submitted->fresh();
        }

        return $attempt->fresh();
    }

    /**
     * Close attempts whose time ran out (run by the scheduler, and lazily).
     */
    public static function closeOverdue(): int
    {
        $closed = 0;

        QuizAttempt::where('status', QuizAttempt::IN_PROGRESS)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now()->subSeconds(self::GRACE_SECONDS))
            ->each(function (QuizAttempt $attempt) use (&$closed) {
                self::submit($attempt, automatic: true);
                $closed++;
            });

        return $closed;
    }

    public static function isOverdue(QuizAttempt $attempt): bool
    {
        return $attempt->expires_at !== null && now()->gt($attempt->expires_at->copy()->addSeconds(self::GRACE_SECONDS));
    }

    /**
     * Whether the member may see correct answers and explanations.
     */
    public static function canSeeAnswers(QuizAttempt $attempt): bool
    {
        $quiz = $attempt->quiz;

        return $attempt->status === QuizAttempt::SUBMITTED && match ($quiz->show_answers) {
            Quiz::ANSWERS_AFTER_CLOSE => $quiz->hasEnded(),
            Quiz::ANSWERS_NEVER => false,
            default => true,
        };
    }

    /**
     * The attempt that counts for this member and quiz (GAM-REQ07).
     */
    public static function resultOfRecord(User $member, Quiz $quiz): ?QuizAttempt
    {
        return $quiz->attempts()->where('user_id', $member->user_id)->where('is_best_attempt', true)->first();
    }

    private static function openAttempt(User $member, Quiz $quiz): ?QuizAttempt
    {
        $attempt = $quiz->attempts()->where('user_id', $member->user_id)->where('status', QuizAttempt::IN_PROGRESS)->latest('started_at')->first();

        if ($attempt && self::isOverdue($attempt)) {
            self::submit($attempt, automatic: true);

            return null;
        }

        return $attempt;
    }

    private static function ensureRunning(QuizAttempt $attempt): void
    {
        if ($attempt->status !== QuizAttempt::IN_PROGRESS) {
            throw new InvalidArgumentException(__('This attempt has already been submitted.'));
        }

        if (self::isOverdue($attempt)) {
            self::submit($attempt, automatic: true);

            throw new InvalidArgumentException(__('Time is up. Your answers were submitted.'));
        }
    }

    private static function markResultOfRecord(QuizAttempt $attempt, Quiz $quiz): void
    {
        $attempts = $quiz->attempts()->where('user_id', $attempt->user_id)->where('status', QuizAttempt::SUBMITTED);

        $record = $quiz->retained_result === Quiz::RETAIN_LATEST
            ? (clone $attempts)->orderByDesc('submitted_at')->orderByDesc('attempt_number')->first()
            : (clone $attempts)->orderByDesc('score')->orderBy('submitted_at')->first();

        (clone $attempts)->update(['is_best_attempt' => false]);
        $record?->forceFill(['is_best_attempt' => true])->save();
    }
}
