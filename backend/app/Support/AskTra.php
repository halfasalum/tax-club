<?php

namespace App\Support;

use App\Models\Question;
use App\Models\User;
use App\Notifications\ContentReviewed;
use App\Notifications\QuestionAnswered;
use App\Notifications\QuestionAssigned;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Ask TRA (COM-REQ01-04):
 *
 *   submitted ─assign→ assigned ─answer→ in_review ─approve→ answered
 *                 │                         └─return→ returned ─answer→ …
 *                 └─decline (not a tax question, duplicate…)→ declined
 *
 * The member always gets the answer privately. When they allowed it and
 * the reviewer agrees, an anonymised version is published to the
 * knowledge base (decision D-14).
 */
final class AskTra
{
    /** Questions allowed per member per day. */
    public const PER_DAY = 5;

    /**
     * @param  array{subject: string, body: string, category_id?: ?int, allow_publish?: bool, language?: string}  $values
     */
    public static function ask(User $member, array $values): Question
    {
        if ($restriction = SafetyDesk::activeRestriction($member, 'questions')) {
            throw new InvalidArgumentException(__('You cannot ask questions right now because of a community rules decision.'));
        }

        $key = 'ask-tra:'.$member->user_id;

        if (RateLimiter::tooManyAttempts($key, self::PER_DAY)) {
            throw new InvalidArgumentException(__('You can ask :count questions a day. Please try again tomorrow.', ['count' => self::PER_DAY]));
        }

        RateLimiter::hit($key, 86400);

        $question = Question::create([
            'reference' => self::nextReference(),
            'user_id' => $member->user_id,
            'category_id' => $values['category_id'] ?? null,
            'subject' => trim($values['subject']),
            'body' => trim(strip_tags($values['body'])),
            'language' => in_array($values['language'] ?? null, ['sw', 'en'], true) ? $values['language'] : app()->getLocale(),
            'allow_publish' => (bool) ($values['allow_publish'] ?? true),
            'status' => Question::SUBMITTED,
        ]);

        // A question with contact details or abusive words is still sent,
        // but a moderator is told (SAF-REQ05).
        if ($problem = ContentFilter::problem($question->subject.' '.$question->body)) {
            SafetyDesk::flag('question', $question->question_id, $problem === 'contact_sharing' ? 'contact_sharing' : 'other', ContentFilter::label($problem));
        }

        return $question;
    }

    public static function categorise(Question $question, ?int $categoryId, User $by): void
    {
        $question->forceFill(['category_id' => $categoryId])->save();
    }

    public static function assign(Question $question, User $expert, User $by): void
    {
        if (! $expert->hasControl('answer_questions')) {
            throw new InvalidArgumentException(__('Choose an officer who answers questions.'));
        }

        if (! in_array($question->status, [Question::SUBMITTED, Question::ASSIGNED, Question::RETURNED], true)) {
            throw new InvalidArgumentException(__('This question can no longer be reassigned.'));
        }

        $question->forceFill(['assigned_to' => $expert->user_id, 'assigned_at' => now(), 'status' => $question->status === Question::RETURNED ? Question::RETURNED : Question::ASSIGNED])->save();

        if (! $expert->is($by)) {
            Notify::send($expert, new QuestionAssigned($question->reference, $question->subject, $question->question_id));
        }
    }

    /**
     * Not a tax question, a duplicate or out of scope: tell the member why.
     */
    public static function decline(Question $question, string $reason, User $by): void
    {
        if (! in_array($question->status, Question::OPEN, true)) {
            throw new InvalidArgumentException(__('This question is already closed.'));
        }

        $question->forceFill(['status' => Question::DECLINED, 'decline_reason' => Review::requireReason($reason), 'delivered_at' => now(), 'reviewed_by' => $by->user_id, 'reviewed_at' => now()])->save();
        Notify::send($question->asker, new QuestionAnswered($question->reference, declined: true));
    }

    /**
     * The expert writes (and may send for review) the answer.
     */
    public static function saveAnswer(Question $question, string $answer, User $expert, bool $submit = false): void
    {
        if ($question->assigned_to !== $expert->user_id) {
            throw new InvalidArgumentException(__('This question is assigned to someone else.'));
        }

        if (! in_array($question->status, [Question::ASSIGNED, Question::RETURNED], true)) {
            throw new InvalidArgumentException(__('The answer is already with the reviewer.'));
        }

        $answer = trim($answer);

        if ($submit && mb_strlen($answer) < 20) {
            throw new InvalidArgumentException(__('Write a complete answer before sending it for review.'));
        }

        $question->forceFill([
            'answer' => $answer,
            'answered_by' => $expert->user_id,
            'answered_at' => now(),
            'status' => $submit ? Question::IN_REVIEW : $question->status,
        ])->save();
    }

    /**
     * Approve the answer: the member gets it, and it is published to the
     * knowledge base when allowed.
     */
    public static function approve(Question $question, User $reviewer, bool $publish = false, ?string $publicQuestion = null, ?string $publicAnswer = null): void
    {
        if ($question->status !== Question::IN_REVIEW) {
            throw new InvalidArgumentException(__('Nothing is waiting for review.'));
        }

        if (ContentWorkflow::fourEyes() && $question->answered_by === $reviewer->user_id) {
            throw new InvalidArgumentException(__('Someone else must review an answer you wrote.'));
        }

        $question->forceFill([
            'status' => Question::ANSWERED,
            'reviewed_by' => $reviewer->user_id,
            'reviewed_at' => now(),
            'review_note' => null,
            'delivered_at' => now(),
        ])->save();

        Notify::send($question->asker, new QuestionAnswered($question->reference));

        if ($publish && $question->allow_publish) {
            self::publish($question, $reviewer, (string) $publicQuestion, (string) $publicAnswer);
        }
    }

    public static function returnAnswer(Question $question, User $reviewer, string $note): void
    {
        if ($question->status !== Question::IN_REVIEW) {
            throw new InvalidArgumentException(__('Nothing is waiting for review.'));
        }

        $question->forceFill(['status' => Question::RETURNED, 'review_note' => Review::requireReason($note), 'reviewed_by' => $reviewer->user_id, 'reviewed_at' => now()])->save();

        if ($question->expert && ! $question->expert->is($reviewer)) {
            Notify::send($question->expert, new ContentReviewed($question->subject, 'returned', $note, route('admin.questions.show', $question)));
        }
    }

    /**
     * Put an anonymised version in the knowledge base.
     */
    public static function publish(Question $question, User $by, string $publicQuestion, string $publicAnswer): void
    {
        if ($question->status !== Question::ANSWERED) {
            throw new InvalidArgumentException(__('Only answered questions can be published.'));
        }

        if (! $question->allow_publish) {
            throw new InvalidArgumentException(__('The member asked us not to publish this question.'));
        }

        $publicQuestion = trim($publicQuestion) ?: $question->subject;
        $publicAnswer = trim($publicAnswer) ?: (string) $question->answer;

        if (ContentFilter::problem($publicQuestion.' '.$publicAnswer) === 'contact_sharing') {
            throw new InvalidArgumentException(__('Remove names, phone numbers and other personal details before publishing.'));
        }

        $question->forceFill([
            'public_question' => Str::limit($publicQuestion, 290, ''),
            'public_answer' => $publicAnswer,
            'slug' => $question->slug ?? (Str::slug(Str::limit($publicQuestion, 70, '')) ?: 'question').'-'.Str::lower(Str::random(5)),
            'published_at' => now(),
            'published_by' => $by->user_id,
        ])->save();

        Audit::record('question.published', $question, after: ['slug' => $question->slug], actor: $by);
    }

    public static function unpublish(Question $question, User $by, string $reason): void
    {
        $question->forceFill(['published_at' => null])->save();
        Audit::record('question.unpublished', $question, reason: Review::requireReason($reason), actor: $by);
    }

    /**
     * Published questions and answers, newest first.
     */
    public static function knowledgeBase(string $search = '', ?int $categoryId = null, ?string $language = null, int $perPage = 15): LengthAwarePaginator
    {
        $search = trim($search);

        return Question::published()
            ->with('category')
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when(in_array($language, ['sw', 'en'], true), fn ($query) => $query->where('language', $language))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('public_question', 'like', "%{$search}%")->orWhere('public_answer', 'like', "%{$search}%")))
            ->latest('published_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            Question::SUBMITTED => __('Received'),
            Question::ASSIGNED => __('With a TRA expert'),
            Question::RETURNED => __('With a TRA expert'),
            Question::IN_REVIEW => __('Answer being checked'),
            Question::ANSWERED => __('Answered'),
            Question::DECLINED => __('Closed without an answer'),
        ];
    }

    private static function nextReference(): string
    {
        $prefix = 'AQ-'.now()->format('Y').'-';
        $last = Question::where('reference', 'like', $prefix.'%')->orderByDesc('reference')->value('reference');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, strlen($prefix))) + 1 : 1), 5, '0', STR_PAD_LEFT);
    }
}
