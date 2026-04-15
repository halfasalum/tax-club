<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'total_points_earned',
        'is_best_attempt',
        'time_spent_seconds',
        'attempted_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'max_score' => 'integer',
        'total_points_earned' => 'integer',
        'is_best_attempt' => 'boolean',
        'time_spent_seconds' => 'integer',
        'attempted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id', 'quiz_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(QuizUserResponse::class, 'attempt_id', 'attempt_id');
    }

    public function getScorePercentageAttribute(): float
    {
        if ($this->max_score === 0) return 0;
        return round(($this->score / $this->max_score) * 100, 2);
    }

    public function isPassed(): bool
    {
        return $this->score_percentage >= 50;
    }
}