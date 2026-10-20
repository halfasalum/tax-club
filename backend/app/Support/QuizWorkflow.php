<?php

namespace App\Support;

use App\Models\EditorialEvent;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Notifications\ContentReviewed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Quiz review and publication (GAM-REQ01-04).
 *
 * Questions can be changed only while the quiz is a draft (or returned or
 * rejected) and nobody has taken it yet, so results stay fair. To change
 * a quiz members have already taken, duplicate it.
 */
final class QuizWorkflow
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public static function create(array $settings, User $author): Quiz
    {
        $quiz = Quiz::create([
            ...$settings,
            'code' => self::nextCode(),
            'editorial_status' => Quiz::DRAFT,
            'publication_status' => Quiz::NOT_PUBLISHED,
            'is_active' => false,
            'created_by' => $author->user_id,
        ]);

        self::event($quiz, 'created', null, $author);

        return $quiz;
    }

    public static function canEdit(Quiz $quiz): bool
    {
        return in_array($quiz->editorial_status, Quiz::EDITABLE, true) && ! self::hasAttempts($quiz);
    }

    public static function hasAttempts(Quiz $quiz): bool
    {
        return $quiz->attempts()->exists();
    }

    /**
     * Take an approved or published quiz back to draft for changes. Only
     * possible before anyone has taken it.
     */
    public static function reopen(Quiz $quiz, User $by): void
    {
        if (self::hasAttempts($quiz)) {
            throw new InvalidArgumentException(__('Members have already taken this quiz. Duplicate it to make changes.'));
        }

        $quiz->forceFill([
            'editorial_status' => Quiz::DRAFT,
            'publication_status' => $quiz->publication_status === Quiz::NOT_PUBLISHED ? Quiz::NOT_PUBLISHED : Quiz::CLOSED,
            'is_active' => false,
        ])->save();

        self::event($quiz, 'back_to_draft', null, $by);
    }

    /**
     * @return list<string>
     */
    public static function readinessErrors(Quiz $quiz): array
    {
        $quiz->load('questions.answers');
        $questions = $quiz->questions->where('is_active', true);
        $errors = [];

        if ($questions->isEmpty()) {
            $errors[] = __('Add at least one question.');
        }

        foreach ($questions as $question) {
            if ($problem = $question->problem()) {
                $errors[] = $problem;
            }
        }

        if ($quiz->pass_mark < 1 || $quiz->pass_mark > 100) {
            $errors[] = __('Set a pass mark between 1 and 100%.');
        }

        return $errors;
    }

    public static function submit(Quiz $quiz, User $author): void
    {
        if (! in_array($quiz->editorial_status, Quiz::EDITABLE, true)) {
            throw new InvalidArgumentException(__('There is nothing to submit.'));
        }

        if ($errors = self::readinessErrors($quiz)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $quiz->forceFill([
            'editorial_status' => Quiz::IN_REVIEW,
            'submitted_by' => $author->user_id,
            'submitted_at' => now(),
            'review_note' => null,
            'total_marks' => $quiz->questions->where('is_active', true)->sum('points'),
        ])->save();

        self::event($quiz, 'submitted', null, $author);
    }

    public static function approve(Quiz $quiz, User $reviewer, ?string $note = null): void
    {
        self::decide($quiz, $reviewer, Quiz::APPROVED, $note);
    }

    public static function returnForChanges(Quiz $quiz, User $reviewer, string $note): void
    {
        self::decide($quiz, $reviewer, Quiz::RETURNED, $note);
    }

    public static function reject(Quiz $quiz, User $reviewer, string $note): void
    {
        self::decide($quiz, $reviewer, Quiz::REJECTED, $note);
    }

    /**
     * Publish now or from $opensAt, optionally until $closesAt (GAM-REQ04).
     */
    public static function publish(Quiz $quiz, User $publisher, ?Carbon $opensAt = null, ?Carbon $closesAt = null): void
    {
        if ($quiz->editorial_status !== Quiz::APPROVED) {
            throw new InvalidArgumentException(__('Only approved quizzes can be published.'));
        }

        $opensAt = $opensAt && $opensAt->isFuture() ? $opensAt : null;

        if ($closesAt && $closesAt->lte($opensAt ?? now())) {
            throw new InvalidArgumentException(__('The closing date must be after the opening date.'));
        }

        $quiz->forceFill([
            'publication_status' => $opensAt ? Quiz::SCHEDULED : Quiz::PUBLISHED,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'published_by' => $publisher->user_id,
            'published_at' => $quiz->published_at ?? now(),
            'is_active' => true,
        ])->save();

        self::event($quiz, $opensAt ? 'scheduled' : 'published', $opensAt?->toDayDateTimeString(), $publisher);
    }

    public static function close(Quiz $quiz, User $by, ?string $reason = null): void
    {
        $quiz->forceFill(['publication_status' => Quiz::CLOSED, 'closes_at' => now(), 'is_active' => false])->save();
        self::event($quiz, 'closed', $reason, $by);
    }

    public static function archive(Quiz $quiz, User $by, string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException(__('Give a reason.'));
        }

        $quiz->forceFill(['publication_status' => Quiz::ARCHIVED, 'is_active' => false])->save();
        self::event($quiz, 'archived', $reason, $by);
    }

    /**
     * Copy a quiz with its questions as a new draft.
     */
    public static function duplicate(Quiz $quiz, User $author): Quiz
    {
        return DB::transaction(function () use ($quiz, $author) {
            $copy = self::create([
                ...$quiz->only(['content_id', 'category_id', 'description', 'instructions', 'audience', 'mode', 'pass_mark', 'time_limit_minutes', 'max_attempts', 'retained_result', 'show_answers', 'shuffle_questions']),
                'title' => __(':title (new version)', ['title' => $quiz->title]),
            ], $author);

            foreach ($quiz->questions()->with('answers')->get() as $question) {
                $newQuestion = QuizQuestion::create([...$question->only(['question_text', 'explanation', 'question_type', 'points', 'order_index', 'is_active']), 'quiz_id' => $copy->quiz_id]);
                foreach ($question->answers as $answer) {
                    QuizAnswer::create([...$answer->only(['answer_text', 'is_correct', 'order_index']), 'question_id' => $newQuestion->question_id]);
                }
            }

            self::event($copy, 'duplicated', $quiz->code, $author);

            return $copy;
        });
    }

    private static function decide(Quiz $quiz, User $reviewer, string $status, ?string $note): void
    {
        if ($quiz->editorial_status !== Quiz::IN_REVIEW) {
            throw new InvalidArgumentException(__('Nothing is waiting for review.'));
        }

        if ($status !== Quiz::APPROVED && blank($note)) {
            throw new InvalidArgumentException(__('Tell the author what to change.'));
        }

        if ($status === Quiz::APPROVED && ContentWorkflow::fourEyes() && $quiz->submitted_by === $reviewer->user_id) {
            throw new InvalidArgumentException(__('Someone else must review content you submitted.'));
        }

        $quiz->forceFill(['editorial_status' => $status, 'reviewed_by' => $reviewer->user_id, 'reviewed_at' => now(), 'review_note' => $note])->save();
        self::event($quiz, $status, $note, $reviewer);

        if ($quiz->submitter && ! $quiz->submitter->is($reviewer)) {
            Notify::send($quiz->submitter, new ContentReviewed($quiz->title, $status, $note, route('admin.quizzes.show', $quiz)));
        }
    }

    private static function nextCode(): string
    {
        $last = Quiz::where('code', 'like', 'QZ-%')->orderByDesc('code')->value('code');

        return sprintf('QZ-%04d', $last ? ((int) substr($last, 3)) + 1 : 1);
    }

    public static function event(Quiz $quiz, string $action, ?string $note, ?User $user): void
    {
        EditorialEvent::create([
            'subject_type' => 'quiz',
            'subject_id' => $quiz->quiz_id,
            'action' => $action,
            'note' => $note,
            'user_id' => $user?->user_id,
        ]);
    }
}
