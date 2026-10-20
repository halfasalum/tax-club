<?php

namespace App\Support;

use App\Models\ClubPatron;
use App\Models\CommunityPost;
use App\Models\CommunitySpace;
use App\Models\Institution;
use App\Models\User;
use App\Notifications\CommunityReply;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Moderated discussion spaces, posts and collaboration groups
 * (COM-REQ05, COM-REQ06, COM-REQ09).
 *
 * Posts are screened by the content filter; anything with contact
 * details or blocked words waits for a moderator. Members with a
 * community restriction cannot post (SAF-REQ04).
 */
final class Community
{
    /** Posts and comments allowed per member per hour. */
    public const POSTS_PER_HOUR = 20;

    /**
     * @param  array<string, mixed>  $values
     */
    public static function createSpace(array $values, User $by): CommunitySpace
    {
        $space = CommunitySpace::create([...self::spaceFields($values), 'created_by' => $by->user_id, 'status' => CommunitySpace::ACTIVE]);
        Audit::record('community_space.created', $space, after: ['name' => $space->name, 'type' => $space->type], actor: $by);

        return $space;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function updateSpace(CommunitySpace $space, array $values, User $by): void
    {
        $before = $space->only(['name', 'type', 'status', 'members_only', 'moderators_post_only']);
        $space->update(self::spaceFields($values));
        Audit::record('community_space.updated', $space, $before, $space->only(array_keys($before)), actor: $by);
    }

    public static function setStatus(CommunitySpace $space, string $status, User $by, ?string $reason = null): void
    {
        if (! in_array($status, [CommunitySpace::ACTIVE, CommunitySpace::LOCKED, CommunitySpace::ARCHIVED], true)) {
            return;
        }

        $before = $space->status;
        $space->update(['status' => $status]);
        Audit::record('community_space.status', $space, ['status' => $before], ['status' => $status], $reason, $by);
    }

    /**
     * Add members to a space or group by membership number (COM-REQ09).
     *
     * @return array{added: int, unknown: list<string>}
     */
    public static function addMembers(CommunitySpace $space, string $numbers, string $role, User $by): array
    {
        $codes = collect(preg_split('/[\s,;]+/', strtoupper($numbers)) ?: [])->filter()->map(fn ($code) => MembershipCard::normalizeNumber($code))->unique();
        $users = User::whereIn('membership_id', $codes)->where('account_status', User::STATUS_ACTIVE)->get();
        $added = 0;

        foreach ($users as $user) {
            $added += DB::table('community_space_members')->updateOrInsert(
                ['space_id' => $space->id, 'user_id' => $user->user_id],
                ['role' => $role === 'moderator' ? 'moderator' : 'member', 'added_by' => $by->user_id, 'created_at' => now()],
            ) ? 1 : 0;
        }

        Audit::record('community_space.members_added', $space, after: ['count' => $users->count(), 'role' => $role], actor: $by);

        return ['added' => $users->count(), 'unknown' => $codes->diff($users->pluck('membership_id'))->values()->all()];
    }

    public static function removeMember(CommunitySpace $space, User $member, User $by): void
    {
        $space->members()->detach($member->user_id);
        Audit::record('community_space.member_removed', $space, after: ['member' => $member->membership_id], actor: $by);
    }

    /**
     * Spaces a member can read, with their newest activity.
     *
     * @return Collection<int, CommunitySpace>
     */
    public static function spacesFor(User $user): Collection
    {
        return CommunitySpace::notArchived()
            ->withCount(['posts as posts_count' => fn ($query) => $query->whereNull('parent_id')->where('status', CommunityPost::PUBLISHED)])
            ->orderByRaw("case type when 'national' then 0 when 'institution' then 1 when 'group' then 2 else 3 end")
            ->orderBy('name')
            ->get()
            ->filter(fn (CommunitySpace $space) => $space->isVisibleTo($user))
            ->values();
    }

    /**
     * Why the member cannot post in the space, or null.
     */
    public static function postingProblem(CommunitySpace $space, User $user): ?string
    {
        if ($restriction = SafetyDesk::activeRestriction($user, 'community')) {
            return $restriction->ends_at
                ? __('You cannot post until :date because of a community rules decision.', ['date' => $restriction->ends_at->translatedFormat('j M Y')])
                : __('You cannot post because of a community rules decision.');
        }

        return match (true) {
            ! $user->isActive() => __('Only active members can post.'),
            ! $space->isVisibleTo($user) => __('You are not a member of this space.'),
            $space->status === CommunitySpace::LOCKED => __('This space is read-only for now.'),
            $space->status === CommunitySpace::ARCHIVED => __('This space is archived.'),
            $space->moderators_post_only && ! $space->isModerator($user) => __('Only moderators post here. You can comment on posts.'),
            default => null,
        };
    }

    public static function post(CommunitySpace $space, User $author, ?string $title, string $body): CommunityPost
    {
        if ($problem = self::postingProblem($space, $author)) {
            throw new InvalidArgumentException($problem);
        }

        return self::write($space, $author, null, $title, $body);
    }

    public static function comment(CommunityPost $post, User $author, string $body): CommunityPost
    {
        $space = $post->space;

        if ($post->parent_id !== null || $post->status !== CommunityPost::PUBLISHED) {
            throw new InvalidArgumentException(__('You cannot comment on this.'));
        }

        if (($problem = self::postingProblem($space, $author)) && ! ($space->moderators_post_only && $problem === __('Only moderators post here. You can comment on posts.'))) {
            throw new InvalidArgumentException($problem);
        }

        $comment = self::write($space, $author, $post, null, $body);

        if ($comment->status === CommunityPost::PUBLISHED && ! $post->author->is($author)) {
            Notify::send($post->author, new CommunityReply($post->title ?: Str::limit($post->body, 40), $post->post_id));
        }

        return $comment;
    }

    /**
     * Change one's own post. Edited text is screened again.
     */
    public static function edit(CommunityPost $post, User $author, ?string $title, string $body): void
    {
        if (! $post->author->is($author) || $post->status === CommunityPost::REMOVED || $post->status === CommunityPost::HIDDEN) {
            throw new InvalidArgumentException(__('You cannot change this post.'));
        }

        $body = self::clean($body);
        $problem = ContentFilter::problem(trim($title.' '.$body));
        $post->forceFill([
            'title' => $post->parent_id ? null : (trim((string) $title) ?: null),
            'body' => $body,
            'edited_at' => now(),
            'status' => $problem ? CommunityPost::HELD : CommunityPost::PUBLISHED,
            'held_reason' => $problem,
        ])->save();

        if ($problem) {
            SafetyDesk::flag('post', $post->post_id, $problem === 'contact_sharing' ? 'contact_sharing' : 'other', ContentFilter::label($problem));
        }

        self::recount($post);
    }

    /**
     * Members delete their own posts; the text is kept for moderation.
     */
    public static function delete(CommunityPost $post, User $by): void
    {
        $isModerator = $post->space->isModerator($by);

        if (! $post->author->is($by) && ! $isModerator) {
            throw new InvalidArgumentException(__('You cannot delete this post.'));
        }

        $post->forceFill(['status' => CommunityPost::REMOVED])->save();
        self::recount($post);

        if (! $post->author->is($by)) {
            SafetyDesk::log(null, 'removed', 'post', $post->post_id, $post->author, __('Removed by a space moderator.'), $by);
        }
    }

    /**
     * Keep the parent's comment count and activity date right.
     */
    public static function recount(CommunityPost $post): void
    {
        $parentId = $post->parent_id ?? $post->post_id;
        $count = CommunityPost::where('parent_id', $parentId)->where('status', CommunityPost::PUBLISHED)->count();
        CommunityPost::whereKey($parentId)->update(['comments_count' => $count]);
    }

    /**
     * Default spaces: one national space and one per institution with an
     * active Tax Club. Safe to run again.
     */
    public static function ensureDefaultSpaces(): void
    {
        CommunitySpace::firstOrCreate(['slug' => 'tax-club-tanzania'], [
            'name' => 'Tax Club Tanzania',
            'description' => __('Talk about taxes, learning and Tax Club life with members across the country.'),
            'type' => 'national',
            'status' => CommunitySpace::ACTIVE,
        ]);

        Institution::whereHas('taxClubs', fn ($query) => $query->where('status', 'active'))->get()->each(function (Institution $institution) {
            $space = CommunitySpace::firstOrCreate(['type' => 'institution', 'institution_id' => $institution->institution_id], [
                'name' => $institution->name.' Tax Club',
                'description' => __('The space for members of your own Tax Club.'),
                'status' => CommunitySpace::ACTIVE,
            ]);

            // Current patrons moderate their own club's space.
            ClubPatron::current()->whereIn('tax_club_id', $institution->taxClubs()->select('tax_club_id'))
                ->pluck('user_id')
                ->each(fn (string $userId) => DB::table('community_space_members')->updateOrInsert(
                    ['space_id' => $space->id, 'user_id' => $userId],
                    ['role' => 'moderator', 'created_at' => now()],
                ));
        });
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            'national' => __('National'),
            'institution' => __('My institution'),
            'thematic' => __('Topic'),
            'event' => __('Event'),
            'competition' => __('Competition'),
            'group' => __('Collaboration group'),
        ];
    }

    private static function write(CommunitySpace $space, User $author, ?CommunityPost $parent, ?string $title, string $body): CommunityPost
    {
        $key = 'community-post:'.$author->user_id;

        if (RateLimiter::tooManyAttempts($key, self::POSTS_PER_HOUR)) {
            throw new InvalidArgumentException(__('You are posting very fast. Please wait a little.'));
        }

        $body = self::clean($body);

        if (mb_strlen($body) < 2) {
            throw new InvalidArgumentException(__('Write something first.'));
        }

        RateLimiter::hit($key, 3600);
        $problem = ContentFilter::problem(trim($title.' '.$body));

        $post = CommunityPost::create([
            'space_id' => $space->id,
            'user_id' => $author->user_id,
            'parent_id' => $parent?->post_id,
            'title' => $parent ? null : (trim((string) $title) ?: null),
            'body' => $body,
            'status' => $problem ? CommunityPost::HELD : CommunityPost::PUBLISHED,
            'held_reason' => $problem,
            'last_activity_at' => now(),
        ]);

        if ($problem) {
            SafetyDesk::flag('post', $post->post_id, $problem === 'contact_sharing' ? 'contact_sharing' : 'other', ContentFilter::label($problem));
        }

        if ($parent) {
            self::recount($post);
            $parent->forceFill(['last_activity_at' => now()])->save();
        }

        return $post;
    }

    private static function clean(string $body): string
    {
        return trim(strip_tags($body));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function spaceFields(array $values): array
    {
        $type = in_array($values['type'] ?? null, CommunitySpace::TYPES, true) ? $values['type'] : 'thematic';

        return [
            'name' => trim((string) $values['name']),
            'description' => trim((string) ($values['description'] ?? '')) ?: null,
            'type' => $type,
            'institution_id' => $type === 'institution' ? ($values['institution_id'] ?? null) : null,
            'event_id' => $type === 'event' ? ($values['event_id'] ?? null) : null,
            'competition_id' => $type === 'competition' ? ($values['competition_id'] ?? null) : null,
            'audience' => Audience::isEveryone($values['audience'] ?? null) ? null : Audience::normalize($values['audience']),
            'members_only' => $type === 'group' || (bool) ($values['members_only'] ?? false),
            'moderators_post_only' => (bool) ($values['moderators_post_only'] ?? false),
        ];
    }
}
