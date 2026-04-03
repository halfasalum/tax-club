<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProgress extends Model
{
    protected $table = 'user_progress';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'content_id',
        'status',
        'progress_percent',
        'started_at',
        'completed_at',
        'last_accessed_at',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'started_at'       => 'datetime',
        'completed_at'     => 'datetime',
        'last_accessed_at' => 'datetime',
    ];

    // ─── Helpers ───────────────────────────────────────────────────

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'content_id', 'content_id');
    }
}