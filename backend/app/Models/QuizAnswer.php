<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAnswer extends Model
{
    use HasUuids;

    protected $table = 'quiz_answers';
    protected $primaryKey = 'answer_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'question_id',
        'answer_text',
        'is_correct',
        'order_index',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'order_index' => 'integer',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id', 'question_id');
    }
}