<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    use HasUuids;

    protected $table = 'quiz_questions';
    protected $primaryKey = 'question_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'quiz_id',
        'question_text',
        'question_type',
        'points',
        'order_index',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'points' => 'integer',
        'order_index' => 'integer',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id', 'quiz_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class, 'question_id', 'question_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(QuizUserResponse::class, 'question_id', 'question_id');
    }

    public function getCorrectAnswer()
    {
        return $this->answers()->where('is_correct', true)->first();
    }
}