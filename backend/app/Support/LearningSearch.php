<?php

namespace App\Support;

use App\Models\Content;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Search and filter the lessons a member may see (LRN-REQ07). Every word
 * must appear in the title, summary, keywords or text; title and keyword
 * matches rank first.
 */
final class LearningSearch
{
    /**
     * @param  array{search?: ?string, category?: int|string|null, type?: ?string, level?: ?string, language?: ?string}  $filters
     */
    public static function query(User $member, array $filters = []): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $words = array_slice(array_filter(preg_split('/\s+/', $search) ?: []), 0, 6);

        $query = Content::query()
            ->live()
            ->availableTo($member)
            ->when(filled($filters['category'] ?? null), fn (Builder $query) => $query->where('category_id', $filters['category']))
            ->when(in_array($filters['type'] ?? null, Content::TYPES, true), fn (Builder $query) => $query->where('content_type', $filters['type']))
            ->when(in_array($filters['level'] ?? null, Content::LEVELS, true), fn (Builder $query) => $query->where('level', $filters['level']))
            ->when(in_array($filters['language'] ?? null, Content::LANGUAGES, true), fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('language', $filters['language'])->orWhereNull('language')));

        foreach ($words as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $query->where(fn (Builder $query) => $query
                ->where('title', 'like', $like)
                ->orWhere('summary', 'like', $like)
                ->orWhere('keywords', 'like', $like)
                ->orWhere('body_text', 'like', $like));
        }

        if ($words !== []) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $first = '%'.addcslashes($words[0], '%_\\').'%';
            $query->orderByRaw('case when title like ? then 0 when title like ? then 1 when keywords like ? then 2 else 3 end', [$like, $first, $first]);
        }

        return $query->latest('published_at');
    }
}
