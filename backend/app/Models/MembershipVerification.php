<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One membership check and the answer given (MEM-REQ03).
 */
class MembershipVerification extends Model
{
    public const UPDATED_AT = null;

    public const METHOD_QR = 'qr';

    public const METHOD_NUMBER = 'number';

    protected $fillable = [
        'member_id',
        'verified_by',
        'method',
        'result',
        'full_details',
        'ip',
    ];

    protected $casts = [
        'full_details' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id', 'user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by', 'user_id');
    }
}
