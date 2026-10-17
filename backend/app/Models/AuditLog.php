<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One audited action. Records can be added but never changed or removed.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'action',
        'auditable_type',
        'auditable_id',
        'before',
        'after',
        'reason',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit records cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Audit records cannot be deleted.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id', 'user_id');
    }

    /**
     * Field names whose values changed, for display.
     *
     * @return list<string>
     */
    public function changedFields(): array
    {
        return array_values(array_unique([...array_keys($this->before ?? []), ...array_keys($this->after ?? [])]));
    }
}
