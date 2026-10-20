<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's tax question to TRA and its reviewed answer (COM-REQ01-04).
 */
class Question extends Model
{
    use Auditable, HasUuids;

    public const SUBMITTED = 'submitted';

    public const ASSIGNED = 'assigned';

    public const IN_REVIEW = 'in_review';

    public const RETURNED = 'returned';

    public const ANSWERED = 'answered';

    public const DECLINED = 'declined';

    public const OPEN = [self::SUBMITTED, self::ASSIGNED, self::IN_REVIEW, self::RETURNED];

    protected $primaryKey = 'question_id';

    /** @var list<string> */
    protected array $auditIgnore = ['body', 'answer', 'public_answer', 'helpful_count'];

    protected $fillable = ['reference', 'user_id', 'category_id', 'subject', 'body', 'language', 'allow_publish', 'status'];

    protected $casts = [
        'allow_publish' => 'boolean',
        'assigned_at' => 'datetime',
        'answered_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    /**
     * In the public knowledge base.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->whereNotNull('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'question_id';
    }

    public function asker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function expert(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to', 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContentCategory::class, 'category_id');
    }
}
