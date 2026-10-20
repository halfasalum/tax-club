<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A mentor application and, once approved, the mentor's profile
 * (MNT-REQ01, MNT-REQ06).
 */
class MentorProfile extends Model
{
    public const SUBMITTED = 'submitted';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const SUSPENDED = 'suspended';

    public const AREAS = ['tax_basics', 'careers_in_tax', 'accounting', 'business_and_entrepreneurship', 'customs_and_trade', 'law', 'ict', 'study_skills', 'leadership'];

    protected $fillable = ['user_id', 'expertise', 'bio', 'availability', 'max_mentees', 'mentors_minors'];

    protected $casts = [
        'expertise' => 'array',
        'mentors_minors' => 'boolean',
        'declaration_at' => 'datetime',
        'background_checked_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::APPROVED);
    }

    /**
     * Cleared to mentor members under 18 (decision D-11).
     */
    public function canMentorMinors(): bool
    {
        return $this->status === self::APPROVED && $this->mentors_minors && $this->background_check === 'cleared';
    }

    public function activeMentorships(): int
    {
        return Mentorship::where('mentor_id', $this->user_id)->where('status', Mentorship::ACTIVE)->count();
    }

    public function hasCapacity(): bool
    {
        return $this->activeMentorships() < $this->max_mentees;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
