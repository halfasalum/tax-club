<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step in a member's lifecycle (MEM-REQ04).
 */
class MemberStatusChange extends Model
{
    public const FIELD_STATUS = 'account_status';

    public const FIELD_CATEGORY = 'user_type';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'field',
        'from_value',
        'to_value',
        'reason',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by', 'user_id');
    }
}
