<?php

namespace App\Models\Concerns;

use App\Models\EditorialEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Review state shared by events, opportunities and broadcasts: who sent
 * the item for review and who decided, with the decision note.
 *
 * Models using this trait declare reviewSubjectType() and reviewUrl().
 */
trait HasReview
{
    abstract public function reviewSubjectType(): string;

    abstract public function reviewUrl(): string;

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by', 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }

    /**
     * @return Collection<int, EditorialEvent>
     */
    public function history(int $limit = 30): Collection
    {
        return EditorialEvent::where('subject_type', $this->reviewSubjectType())
            ->where('subject_id', $this->getKey())
            ->with('user:user_id,full_name')
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
