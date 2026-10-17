<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An achievement awarded automatically when a member reaches a threshold
 * (GAM-REQ09).
 */
class Badge extends Model
{
    use Auditable;

    public const CRITERIA = ['lessons_completed', 'modules_completed', 'quizzes_passed', 'perfect_scores', 'points_total'];

    protected $fillable = ['key', 'icon', 'name_sw', 'name_en', 'description_sw', 'description_en', 'criteria', 'threshold', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'threshold' => 'integer'];

    public function earners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_badges', 'badge_id', 'user_id', 'id', 'user_id')->withPivot('awarded_at');
    }

    public function name(): string
    {
        return app()->getLocale() === 'en' ? $this->name_en : $this->name_sw;
    }

    public function description(): ?string
    {
        return app()->getLocale() === 'en' ? $this->description_en : $this->description_sw;
    }
}
