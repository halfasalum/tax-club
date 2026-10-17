<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    use HasUuids;

    public const SINGLE_CHOICE = 'single_choice';

    public const TRUE_FALSE = 'true_false';

    public const MULTIPLE_SELECT = 'multiple_select';

    public const TYPES = [self::SINGLE_CHOICE, self::TRUE_FALSE, self::MULTIPLE_SELECT];

    protected $table = 'quiz_questions';

    protected $primaryKey = 'question_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'quiz_id',
        'question_text',
        'explanation',
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

    /**
     * Problems that stop the quiz from going to review (GAM-REQ02).
     */
    public function problem(): ?string
    {
        $answers = $this->answers;
        $correct = $answers->where('is_correct', true)->count();

        return match (true) {
            blank(trim((string) $this->question_text)) => __('A question has no text.'),
            $answers->count() < 2 => __('":question" needs at least two answer options.', ['question' => str($this->question_text)->limit(40)]),
            $this->question_type === self::MULTIPLE_SELECT && $correct < 1 => __('":question" needs at least one correct answer.', ['question' => str($this->question_text)->limit(40)]),
            $this->question_type !== self::MULTIPLE_SELECT && $correct !== 1 => __('":question" needs exactly one correct answer.', ['question' => str($this->question_text)->limit(40)]),
            $this->points < 1 => __('":question" must be worth at least 1 mark.', ['question' => str($this->question_text)->limit(40)]),
            default => null,
        };
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class, 'question_id', 'question_id')->orderBy('order_index');
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
