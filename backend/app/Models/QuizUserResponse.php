<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizUserResponse extends Model
{
    use HasUuids;

    protected $table = 'quiz_user_responses';
    protected $primaryKey = 'response_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'attempt_id',
        'question_id',
        'answer_id',
        'answer_text',
        'is_correct',
        'points_earned',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'points_earned' => 'integer',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'attempt_id', 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id', 'question_id');
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(QuizAnswer::class, 'answer_id', 'answer_id');
    }
}