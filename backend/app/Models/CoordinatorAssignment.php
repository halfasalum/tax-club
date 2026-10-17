<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A TRA coordinator's responsibility for a region (INS-REQ06).
 */
class CoordinatorAssignment extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id',
        'region',
        'starts_on',
        'ends_on',
        'end_reason',
        'assigned_by',
        'ended_by',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    /**
     * Assignments in effect today; `ends_on` is the first day off duty.
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereDate('starts_on', '<=', now()->toDateString())
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>', now()->toDateString()));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by', 'user_id');
    }
}
