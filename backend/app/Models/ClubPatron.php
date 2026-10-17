<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A patron's assignment to a Tax Club, kept after it ends (INS-REQ05).
 */
class ClubPatron extends Model
{
    use Auditable;

    protected $fillable = [
        'tax_club_id',
        'user_id',
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
     * Assignments in effect today. `ends_on` is the first day the patron no
     * longer serves, so ending an assignment today takes effect immediately.
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereDate('starts_on', '<=', now()->toDateString())
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>', now()->toDateString()));
    }

    public function isCurrent(): bool
    {
        return $this->starts_on->lte(now()) && ($this->ends_on === null || $this->ends_on->gt(now()->startOfDay()));
    }

    public function taxClub(): BelongsTo
    {
        return $this->belongsTo(TaxClub::class, 'tax_club_id', 'tax_club_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
