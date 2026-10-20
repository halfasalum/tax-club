<?php

namespace App\Support;

use App\Models\Content;
use App\Models\Event;
use App\Models\Feedback;
use App\Models\FeedbackEvent;
use App\Models\Opportunity;
use App\Models\Quiz;
use App\Models\User;
use App\Notifications\FeedbackAssigned;
use App\Notifications\FeedbackUpdated;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * Feedback cases (FBK-REQ01-04):
 *
 *   submitted ─classify/assign→ in_review ─respond→ responded ─close→ closed
 *
 * Members get a reference number, follow the status and read the
 * response. Officers classify, prioritise, assign and keep internal notes.
 * Safety concerns are routed to safeguarding officers with high priority.
 */
final class FeedbackDesk
{
    /** Submissions allowed per member per hour. */
    public const PER_HOUR = 5;

    /** Where each category goes first. */
    public const ROUTING = [
        'suggestion' => 'general',
        'problem' => 'ict',
        'content' => 'content',
        'event' => 'events',
        'account' => 'membership',
        'concern' => Feedback::SAFEGUARDING,
        'other' => 'general',
    ];

    /**
     * @param  array{category: string, subject: string, message: string, related_type?: ?string, related_id?: ?string, channel?: string}  $values
     */
    public static function submit(User $member, array $values): Feedback
    {
        $key = 'feedback:'.$member->user_id;

        if (RateLimiter::tooManyAttempts($key, self::PER_HOUR)) {
            throw new InvalidArgumentException(__('You have sent a lot of feedback in the last hour. Please try again later.'));
        }

        if (! in_array($values['category'] ?? null, Feedback::CATEGORIES, true)) {
            throw new InvalidArgumentException(__('Choose what your feedback is about.'));
        }

        RateLimiter::hit($key, 3600);
        $concern = $values['category'] === 'concern';

        $feedback = DB::transaction(function () use ($member, $values, $concern) {
            $feedback = Feedback::create([
                'reference' => self::nextReference(),
                'user_id' => $member->user_id,
                'category' => $values['category'],
                'subject' => trim($values['subject']),
                'message' => trim($values['message']),
                'related_type' => $values['related_type'] ?? null,
                'related_id' => $values['related_id'] ?? null,
                'channel' => $values['channel'] ?? 'web',
                'status' => Feedback::SUBMITTED,
                'priority' => $concern ? 'high' : 'normal',
                'area' => self::ROUTING[$values['category']],
            ]);
            self::event($feedback, 'submitted', null, false, $member);

            return $feedback;
        });

        if ($concern) {
            self::alertSafeguarding($feedback);
        }

        return $feedback;
    }

    /**
     * @param  array{category?: string, priority?: string, area?: string}  $values
     */
    public static function classify(Feedback $feedback, array $values, User $officer): void
    {
        $changes = array_filter([
            'category' => in_array($values['category'] ?? null, Feedback::CATEGORIES, true) ? $values['category'] : null,
            'priority' => in_array($values['priority'] ?? null, Feedback::PRIORITIES, true) ? $values['priority'] : null,
            'area' => in_array($values['area'] ?? null, Feedback::AREAS, true) ? $values['area'] : null,
        ]);
        $feedback->fill($changes);
        $dirty = $feedback->getDirty();

        if ($dirty === []) {
            return;
        }

        if ($feedback->getOriginal('area') === Feedback::SAFEGUARDING && ($dirty['area'] ?? null) && ! $officer->hasControl('handle_safeguarding_concerns')) {
            throw new InvalidArgumentException(__('Only safeguarding officers can move a safeguarding concern.'));
        }

        $feedback->status = $feedback->status === Feedback::SUBMITTED ? Feedback::IN_REVIEW : $feedback->status;
        $feedback->save();
        self::event($feedback, 'classified', collect($dirty)->map(fn ($value, $key) => "{$key}: {$value}")->implode(', '), true, $officer);
    }

    public static function assign(Feedback $feedback, ?User $assignee, User $officer): void
    {
        if ($assignee && ! $assignee->canAccessAdmin()) {
            throw new InvalidArgumentException(__('Feedback can only be assigned to officers.'));
        }

        if ($assignee && $feedback->isSafeguarding() && ! $assignee->hasControl('handle_safeguarding_concerns')) {
            throw new InvalidArgumentException(__('Safeguarding concerns can only be assigned to safeguarding officers.'));
        }

        $feedback->forceFill([
            'assigned_to' => $assignee?->user_id,
            'status' => $feedback->status === Feedback::SUBMITTED ? Feedback::IN_REVIEW : $feedback->status,
        ])->save();
        self::event($feedback, $assignee ? 'assigned' : 'unassigned', $assignee?->full_name, true, $officer);

        if ($assignee && ! $assignee->is($officer)) {
            Notify::send($assignee, new FeedbackAssigned($feedback->reference, $feedback->subject, $feedback->feedback_id));
        }
    }

    public static function note(Feedback $feedback, string $note, User $officer): void
    {
        self::event($feedback, 'note', Review::requireReason($note), true, $officer);
    }

    /**
     * Reply to the member, optionally closing the case as resolved.
     */
    public static function respond(Feedback $feedback, string $response, User $officer, bool $close = false): void
    {
        $response = trim($response);

        if ($response === '') {
            throw new InvalidArgumentException(__('Write the response for the member.'));
        }

        if (! $feedback->isOpen()) {
            throw new InvalidArgumentException(__('This feedback is closed.'));
        }

        $feedback->forceFill([
            'response' => $response,
            'responded_at' => now(),
            'status' => $close ? Feedback::CLOSED : Feedback::RESPONDED,
            'resolved_at' => $close ? now() : $feedback->resolved_at,
            'closed_at' => $close ? now() : null,
            'assigned_to' => $feedback->assigned_to ?? $officer->user_id,
        ])->save();

        self::event($feedback, 'responded', $response, false, $officer);

        if ($close) {
            self::event($feedback, 'closed', null, false, $officer);
        }

        Notify::send($feedback->user, new FeedbackUpdated($feedback->reference, self::statusLabels()[$feedback->status]));
    }

    public static function close(Feedback $feedback, User $officer, ?string $note = null): void
    {
        if (! $feedback->isOpen()) {
            return;
        }

        if (blank($feedback->response) && blank($note)) {
            throw new InvalidArgumentException(__('Respond to the member, or say why it is closed without a response.'));
        }

        $feedback->forceFill(['status' => Feedback::CLOSED, 'closed_at' => now(), 'resolved_at' => $feedback->resolved_at ?? now()])->save();
        self::event($feedback, 'closed', trim((string) $note) ?: null, false, $officer);
        Notify::send($feedback->user, new FeedbackUpdated($feedback->reference, self::statusLabels()[Feedback::CLOSED]));
    }

    public static function reopen(Feedback $feedback, User $officer, string $reason): void
    {
        $feedback->forceFill(['status' => Feedback::IN_REVIEW, 'closed_at' => null])->save();
        self::event($feedback, 'reopened', Review::requireReason($reason), true, $officer);
    }

    /**
     * The member adds more information to their own open feedback.
     */
    public static function addMemberMessage(Feedback $feedback, User $member, string $message): void
    {
        if (! $feedback->user->is($member)) {
            throw new InvalidArgumentException(__('This is not your feedback.'));
        }

        if (! $feedback->isOpen()) {
            throw new InvalidArgumentException(__('This feedback is closed. Send new feedback if you need more help.'));
        }

        $message = trim($message);

        if ($message === '') {
            throw new InvalidArgumentException(__('Write your message.'));
        }

        if ($feedback->status === Feedback::RESPONDED) {
            $feedback->forceFill(['status' => Feedback::IN_REVIEW])->save();
        }

        self::event($feedback, 'member_message', $message, false, $member);
    }

    /**
     * What the member may see of the case history.
     *
     * @return Collection<int, FeedbackEvent>
     */
    public static function publicHistory(Feedback $feedback): Collection
    {
        return $feedback->events()->where('internal', false)->get();
    }

    /**
     * A label for what the feedback is about, e.g. an event title.
     */
    public static function relatedLabel(Feedback $feedback): ?string
    {
        return match ($feedback->related_type) {
            'event' => Event::find($feedback->related_id)?->title,
            'lesson' => Content::find($feedback->related_id)?->title,
            'quiz' => Quiz::find($feedback->related_id)?->title,
            'opportunity' => Opportunity::find($feedback->related_id)?->title,
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return [
            'suggestion' => __('Suggestion or idea'),
            'problem' => __('Problem with the app or website'),
            'content' => __('Mistake in a lesson or quiz'),
            'event' => __('An event'),
            'account' => __('My account or membership'),
            'concern' => __('Safety concern'),
            'other' => __('Something else'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            Feedback::SUBMITTED => __('Received'),
            Feedback::IN_REVIEW => __('Being looked at'),
            Feedback::RESPONDED => __('Answered'),
            Feedback::CLOSED => __('Closed'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function areaLabels(): array
    {
        return [
            'ict' => __('ICT support'),
            'content' => __('Learning content'),
            'events' => __('Events'),
            'membership' => __('Membership'),
            'opportunities' => __('Opportunities'),
            'safeguarding' => __('Safeguarding'),
            'general' => __('Programme team'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function priorityLabels(): array
    {
        return ['low' => __('Low'), 'normal' => __('Normal'), 'high' => __('High'), 'urgent' => __('Urgent')];
    }

    private static function alertSafeguarding(Feedback $feedback): void
    {
        User::whereHas('roles.controls', fn ($query) => $query->where('slug', 'handle_safeguarding_concerns'))
            ->where('account_status', User::STATUS_ACTIVE)
            ->limit(20)
            ->get()
            ->each(fn (User $officer) => Notify::send($officer, new FeedbackAssigned($feedback->reference, $feedback->subject, $feedback->feedback_id)));
    }

    private static function nextReference(): string
    {
        $prefix = 'FB-'.now()->format('Y').'-';
        $last = Feedback::where('reference', 'like', $prefix.'%')->orderByDesc('reference')->value('reference');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, strlen($prefix))) + 1 : 1), 5, '0', STR_PAD_LEFT);
    }

    private static function event(Feedback $feedback, string $action, ?string $note, bool $internal, ?User $user): void
    {
        FeedbackEvent::create([
            'feedback_id' => $feedback->feedback_id,
            'action' => $action,
            'note' => $note,
            'internal' => $internal,
            'user_id' => $user?->user_id,
        ]);
    }
}
