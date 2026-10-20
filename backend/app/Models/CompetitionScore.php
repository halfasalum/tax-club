<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One judge's score for a submission (CMP-REQ05).
 */
class CompetitionScore extends Model
{
    protected $fillable = ['entry_id', 'judge_id', 'score', 'comment'];

    protected $casts = ['score' => 'float'];

    public function judge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'judge_id', 'user_id');
    }
}
