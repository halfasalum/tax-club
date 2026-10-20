<?php

namespace App\Support;

use App\Models\EditorialEvent;
use App\Models\User;
use App\Notifications\ContentReviewed;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The review step for events, opportunities and broadcasts (EVT-REQ02,
 * OPP-REQ02, NOT-REQ03):
 *
 *   draft ─submit→ in_review ─approve→ approved
 *                      ├─return→ returned ─(edit, submit)→ …
 *                      └─reject→ rejected
 *
 * Approvers may not approve their own submissions (four-eyes) unless the
 * rule is switched off. Every step is kept in editorial_events.
 */
final class Review
{
    public const DRAFT = 'draft';

    public const IN_REVIEW = 'in_review';

    public const RETURNED = 'returned';

    public const REJECTED = 'rejected';

    public const APPROVED = 'approved';

    public const EDITABLE = [self::DRAFT, self::RETURNED, self::REJECTED];

    /**
     * @param  list<string>  $problems
     */
    public static function submit(Model $item, User $author, array $problems, string $statusColumn = 'editorial_status'): void
    {
        if (! in_array($item->{$statusColumn}, self::EDITABLE, true)) {
            throw new InvalidArgumentException(__('There is nothing to submit.'));
        }

        if ($problems) {
            throw new InvalidArgumentException(implode(' ', $problems));
        }

        $item->forceFill([
            $statusColumn => self::IN_REVIEW,
            'submitted_by' => $author->user_id,
            'submitted_at' => now(),
            'review_note' => null,
        ])->save();

        self::event($item, 'submitted', null, $author);
    }

    public static function decide(Model $item, User $reviewer, string $decision, ?string $note, string $statusColumn = 'editorial_status'): void
    {
        if ($item->{$statusColumn} !== self::IN_REVIEW) {
            throw new InvalidArgumentException(__('Nothing is waiting for review.'));
        }

        if ($decision !== self::APPROVED && blank($note)) {
            throw new InvalidArgumentException(__('Tell the author what to change.'));
        }

        if ($decision === self::APPROVED && ContentWorkflow::fourEyes() && $item->submitted_by === $reviewer->user_id) {
            throw new InvalidArgumentException(__('Someone else must review content you submitted.'));
        }

        $item->forceFill([
            $statusColumn => $decision,
            'reviewed_by' => $reviewer->user_id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ])->save();

        self::event($item, $decision, $note, $reviewer);

        $submitter = $item->submitter;

        if ($submitter && ! $submitter->is($reviewer)) {
            Notify::send($submitter, new ContentReviewed($item->title, $decision, $note, $item->reviewUrl()));
        }
    }

    /**
     * Back to draft after changes to an item that was in review or approved.
     */
    public static function backToDraft(Model $item, User $by, string $statusColumn = 'editorial_status'): void
    {
        if (in_array($item->{$statusColumn}, [self::IN_REVIEW, self::APPROVED], true)) {
            $item->forceFill([$statusColumn => self::DRAFT])->save();
            self::event($item, 'back_to_draft', __('Edited after it was submitted'), $by);
        }
    }

    public static function requireReason(?string $reason): string
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            throw new InvalidArgumentException(__('Give a reason.'));
        }

        return $reason;
    }

    /**
     * Next code like EV-0007 for a model with a "code" column.
     *
     * @param  class-string<Model>  $model
     */
    public static function nextCode(string $model, string $prefix): string
    {
        $last = $model::where('code', 'like', $prefix.'-%')->orderByDesc('code')->value('code');

        return sprintf('%s-%04d', $prefix, $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1);
    }

    public static function event(Model $item, string $action, ?string $note, ?User $user): void
    {
        EditorialEvent::create([
            'subject_type' => $item->reviewSubjectType(),
            'subject_id' => $item->getKey(),
            'action' => $action,
            'note' => $note,
            'user_id' => $user?->user_id,
        ]);
    }
}
