<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member asking for a mentor (MNT-REQ02).
 */
class MentorshipRequest extends Model
{
    public const OPEN = 'open';

    public const MATCHED = 'matched';

    public const CANCELLED = 'cancelled';

    public const DECLINED = 'declined';

    protected $fillable = ['mentee_id', 'interests', 'goals', 'mode', 'guardian_consent'];

    protected $casts = [
        'interests' => 'array',
        'guardian_consent' => 'boolean',
        'consent_confirmed_at' => 'datetime',
    ];

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentee_id', 'user_id');
    }
}
