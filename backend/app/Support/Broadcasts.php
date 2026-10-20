<?php

namespace App\Support;

use App\Models\Broadcast;
use App\Models\User;
use App\Notifications\BroadcastMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Targeted and broadcast notifications (NOT-REQ03), and scheduled
 * announcements (NOT-REQ04):
 *
 *   draft ─submit→ in_review ─approve→ approved ─send→ sending → sent
 *                                         └─schedule→ scheduled → sending → sent
 *
 * A second officer approves every broadcast before it goes out, because
 * it may reach thousands of young members and costs SMS credit.
 */
final class Broadcasts
{
    /** Longest message allowed (three SMS parts). */
    public const MAX_LENGTH = 459;

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function create(array $fields, User $author): Broadcast
    {
        $broadcast = new Broadcast([...self::only($fields), 'code' => Review::nextCode(Broadcast::class, 'BC'), 'created_by' => $author->user_id]);
        $broadcast->forceFill(['status' => Review::DRAFT])->save();
        Review::event($broadcast, 'created', null, $author);

        return $broadcast;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function update(Broadcast $broadcast, array $fields, User $by): void
    {
        if (! in_array($broadcast->status, [...Review::EDITABLE, Review::IN_REVIEW, Review::APPROVED], true)) {
            throw new InvalidArgumentException(__('This broadcast can no longer be changed.'));
        }

        $broadcast->fill(self::only($fields));

        if ($broadcast->isDirty()) {
            $broadcast->save();
            Review::backToDraft($broadcast, $by, 'status');
        }
    }

    /**
     * @return list<string>
     */
    public static function readinessErrors(Broadcast $broadcast): array
    {
        $errors = [];

        if (blank($broadcast->title)) {
            $errors[] = __('Add a title for your own reference.');
        }

        if (blank($broadcast->message_sw)) {
            $errors[] = __('Write the message in Kiswahili.');
        }

        if (mb_strlen((string) $broadcast->message_sw) > self::MAX_LENGTH || mb_strlen((string) $broadcast->message_en) > self::MAX_LENGTH) {
            $errors[] = __('Keep the message under :count characters.', ['count' => self::MAX_LENGTH]);
        }

        if (! $broadcast->send_sms && ! $broadcast->send_inbox) {
            $errors[] = __('Choose SMS, the inbox or both.');
        }

        if (self::recipientCount($broadcast) === 0) {
            $errors[] = __('No active members match this audience.');
        }

        return $errors;
    }

    public static function recipientCount(Broadcast $broadcast): int
    {
        return Audience::members(User::query(), $broadcast->audience)->count();
    }

    public static function submit(Broadcast $broadcast, User $author): void
    {
        Review::submit($broadcast, $author, self::readinessErrors($broadcast), 'status');
    }

    public static function approve(Broadcast $broadcast, User $reviewer, ?string $note = null): void
    {
        Review::decide($broadcast, $reviewer, Review::APPROVED, $note, 'status');
    }

    public static function returnForChanges(Broadcast $broadcast, User $reviewer, string $note): void
    {
        Review::decide($broadcast, $reviewer, Review::RETURNED, $note, 'status');
    }

    public static function reject(Broadcast $broadcast, User $reviewer, string $note): void
    {
        Review::decide($broadcast, $reviewer, Review::REJECTED, $note, 'status');
    }

    /**
     * Send now, or at $sendAt.
     */
    public static function send(Broadcast $broadcast, User $by, ?Carbon $sendAt = null): void
    {
        if (! in_array($broadcast->status, [Review::APPROVED, Broadcast::SCHEDULED], true)) {
            throw new InvalidArgumentException(__('Only approved broadcasts can be sent.'));
        }

        if ($sendAt && $sendAt->isFuture()) {
            $broadcast->forceFill(['status' => Broadcast::SCHEDULED, 'send_at' => $sendAt])->save();
            Review::event($broadcast, 'scheduled', $sendAt->toDayDateTimeString(), $by);

            return;
        }

        self::deliver($broadcast, $by);
    }

    public static function cancel(Broadcast $broadcast, User $by, string $reason): void
    {
        if (in_array($broadcast->status, [Broadcast::SENDING, Broadcast::SENT, Broadcast::CANCELLED], true)) {
            throw new InvalidArgumentException(__('This broadcast was already sent or cancelled.'));
        }

        $broadcast->forceFill(['status' => Broadcast::CANCELLED, 'status_reason' => Review::requireReason($reason)])->save();
        Review::event($broadcast, 'cancelled', $reason, $by);
    }

    /**
     * Queue one notification per member, in chunks.
     */
    public static function deliver(Broadcast $broadcast, ?User $by = null): int
    {
        $claimed = Broadcast::whereKey($broadcast->broadcast_id)->whereIn('status', [Review::APPROVED, Broadcast::SCHEDULED])->update(['status' => Broadcast::SENDING]);

        if (! $claimed) {
            return 0;
        }

        $count = 0;
        $notification = new BroadcastMessage($broadcast->broadcast_id, $broadcast->message_sw, $broadcast->message_en, $broadcast->link_url, $broadcast->send_sms, $broadcast->send_inbox);

        Audience::members(User::query(), $broadcast->audience)->chunkById(500, function ($members) use ($notification, &$count) {
            Notification::send($members, $notification);
            $count += $members->count();
        }, 'user_id');

        $broadcast->forceFill(['status' => Broadcast::SENT, 'sent_at' => now(), 'recipients_count' => $count])->save();
        Review::event($broadcast, 'sent', trans_choice(':count member|:count members', $count, ['count' => $count]), $by);

        return $count;
    }

    /**
     * Send broadcasts whose time has come. Run by the scheduler.
     */
    public static function applySchedule(): int
    {
        $sent = 0;

        Broadcast::where('status', Broadcast::SCHEDULED)->where('send_at', '<=', now())->each(function (Broadcast $broadcast) use (&$sent) {
            self::deliver($broadcast);
            $sent++;
        });

        return $sent;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private static function only(array $fields): array
    {
        $values = array_intersect_key($fields, array_flip(['title', 'message_sw', 'message_en', 'link_url', 'send_sms', 'send_inbox', 'audience']));

        if (array_key_exists('audience', $values)) {
            $values['audience'] = Audience::isEveryone($values['audience']) ? null : Audience::normalize($values['audience']);
        }

        foreach (['message_en', 'link_url'] as $nullable) {
            if (array_key_exists($nullable, $values) && blank($values[$nullable])) {
                $values[$nullable] = null;
            }
        }

        return $values;
    }
}
