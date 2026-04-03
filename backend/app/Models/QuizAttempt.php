<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttempt extends Model
{
    use HasUuids;

    protected $table = 'quiz_attempts';
    protected $primaryKey = 'attempt_id';
    public $timestamps = false;

    const CREATED_AT = 'attempted_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'quiz_id',
        'score',
        'max_score',
        'attempted_at',
    ];

    protected $casts = [
        'score'        => 'integer',
        'max_score'    => 'integer',
        'attempted_at' => 'datetime',
    ];

    // ─── Helpers ───────────────────────────────────────────────────

    /**
     * Get the score as a percentage.
     */
    public function getScorePercentageAttribute(): float
    {
        if ($this->max_score === 0) return 0;
        return round(($this->score / $this->max_score) * 100, 2);
    }

    /**
     * Check if the attempt was passed (>= 50%).
     */
    public function isPassed(): bool
    {
        return $this->score_percentage >= 50;
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id', 'quiz_id');
    }
}