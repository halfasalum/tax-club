<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasReview;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A targeted or broadcast notification to a chosen audience (NOT-REQ03).
 */
class Broadcast extends Model
{
    use Auditable, HasReview, HasUuids;

    public const SCHEDULED = 'scheduled';

    public const SENDING = 'sending';

    public const SENT = 'sent';

    public const CANCELLED = 'cancelled';

    /** One SMS holds 160 plain characters; longer messages cost more. */
    public const SMS_PART = 153;

    protected $primaryKey = 'broadcast_id';

    protected $fillable = ['code', 'title', 'message_sw', 'message_en', 'link_url', 'send_sms', 'send_inbox', 'audience', 'created_by'];

    protected $casts = [
        'audience' => 'array',
        'send_sms' => 'boolean',
        'send_inbox' => 'boolean',
        'send_at' => 'datetime',
        'sent_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'recipients_count' => 'integer',
    ];

    /**
     * SMS parts per recipient for the longer of the two languages.
     */
    public function smsParts(): int
    {
        $length = max(mb_strlen('TRA Tax Club: '.$this->message_sw), mb_strlen('TRA Tax Club: '.(string) $this->message_en));

        return $length <= 160 ? 1 : (int) ceil($length / self::SMS_PART);
    }

    public function reviewSubjectType(): string
    {
        return 'broadcast';
    }

    public function reviewUrl(): string
    {
        return route('admin.broadcasts.show', $this);
    }

    public function getRouteKeyName(): string
    {
        return 'broadcast_id';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }
}
