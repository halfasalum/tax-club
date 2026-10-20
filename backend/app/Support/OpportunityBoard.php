<?php

namespace App\Support;

use App\Models\Content;
use App\Models\ContentCategory;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Employment and development opportunities (OPP-REQ01-08).
 *
 *   draft ─submit→ in_review ─approve→ approved ─publish→ published
 *   published ─deadline→ closed ─90 days→ archived
 *   published ─withdraw→ withdrawn
 *
 * Members only see open opportunities meant for them. Views, saves and
 * clicks on the application channel are counted for reporting, without
 * storing anything about the member beyond category and region.
 */
final class OpportunityBoard
{
    /** Closed or withdrawn opportunities are archived after this many days. */
    public const ARCHIVE_AFTER_DAYS = 90;

    /** Career guidance lessons live in this learning category (OPP-REQ07). */
    public const CAREER_CATEGORY = 'tax-careers';

    public const FIELDS = [
        'title', 'organization', 'type', 'description', 'eligibility', 'location', 'region', 'work_mode', 'audience',
        'deadline', 'apply_method', 'apply_url', 'apply_email', 'apply_instructions',
    ];

    public const ATTACHMENT_DISK = 'public';

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function create(array $fields, User $author, ?UploadedFile $attachment = null): Opportunity
    {
        $opportunity = new Opportunity([...self::only($fields), 'code' => Review::nextCode(Opportunity::class, 'OP'), 'posted_by' => $author->user_id, 'is_active' => false]);
        self::storeAttachment($opportunity, $attachment);
        $opportunity->forceFill(['editorial_status' => Review::DRAFT, 'publication_status' => Opportunity::NOT_PUBLISHED])->save();
        Review::event($opportunity, 'created', null, $author);

        return $opportunity;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function update(Opportunity $opportunity, array $fields, User $by, ?UploadedFile $attachment = null): void
    {
        if (in_array($opportunity->publication_status, [Opportunity::WITHDRAWN, Opportunity::ARCHIVED], true)) {
            throw new InvalidArgumentException(__('Withdrawn or archived opportunities cannot be changed.'));
        }

        $opportunity->fill(self::only($fields));
        self::storeAttachment($opportunity, $attachment);
        $changed = array_keys($opportunity->getDirty());

        if ($changed === []) {
            return;
        }

        $opportunity->save();

        if ($opportunity->publication_status === Opportunity::NOT_PUBLISHED) {
            Review::backToDraft($opportunity, $by);
        } else {
            Review::event($opportunity, 'edited', implode(', ', $changed), $by);
        }

        // A later deadline reopens a listing that closed automatically.
        if ($opportunity->publication_status === Opportunity::CLOSED && in_array('deadline', $changed, true) && $opportunity->deadline?->endOfDay()->isFuture()) {
            $opportunity->forceFill(['publication_status' => Opportunity::PUBLISHED, 'closed_at' => null, 'is_active' => true])->save();
            Review::event($opportunity, 'reopened', __('New deadline'), $by);
        }
    }

    /**
     * @return list<string>
     */
    public static function readinessErrors(Opportunity $opportunity): array
    {
        $errors = [];

        foreach (['title' => __('Add a title.'), 'organization' => __('Name the organization offering it.'), 'description' => __('Describe the opportunity.')] as $field => $message) {
            if (blank($opportunity->{$field})) {
                $errors[] = $message;
            }
        }

        if ($opportunity->deadline && $opportunity->deadline->endOfDay()->isPast()) {
            $errors[] = __('The deadline has already passed.');
        }

        $channel = match ($opportunity->apply_method) {
            'link' => filled($opportunity->apply_url),
            'email' => filled($opportunity->apply_email),
            default => filled($opportunity->apply_instructions),
        };

        if (! $channel) {
            $errors[] = __('Say how members apply: a link, an email address or instructions.');
        }

        return $errors;
    }

    public static function submit(Opportunity $opportunity, User $author): void
    {
        Review::submit($opportunity, $author, self::readinessErrors($opportunity));
    }

    public static function approve(Opportunity $opportunity, User $reviewer, ?string $note = null): void
    {
        Review::decide($opportunity, $reviewer, Review::APPROVED, $note);
    }

    public static function returnForChanges(Opportunity $opportunity, User $reviewer, string $note): void
    {
        Review::decide($opportunity, $reviewer, Review::RETURNED, $note);
    }

    public static function reject(Opportunity $opportunity, User $reviewer, string $note): void
    {
        Review::decide($opportunity, $reviewer, Review::REJECTED, $note);
    }

    public static function publish(Opportunity $opportunity, User $publisher, ?Carbon $publishAt = null): void
    {
        if ($opportunity->editorial_status !== Review::APPROVED) {
            throw new InvalidArgumentException(__('Only approved opportunities can be published.'));
        }

        if ($errors = self::readinessErrors($opportunity)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $publishAt = $publishAt && $publishAt->isFuture() ? $publishAt : null;

        if ($publishAt && $opportunity->deadline && $publishAt->gt($opportunity->deadline->copy()->endOfDay())) {
            throw new InvalidArgumentException(__('The publication date must be before the deadline.'));
        }

        $opportunity->forceFill([
            'publication_status' => $publishAt ? Opportunity::SCHEDULED : Opportunity::PUBLISHED,
            'publish_at' => $publishAt,
            'published_by' => $publisher->user_id,
            'published_at' => $publishAt ?? now(),
            'is_active' => $publishAt === null,
            'closed_at' => null,
        ])->save();

        Review::event($opportunity, $publishAt ? 'scheduled' : 'published', $publishAt?->toDayDateTimeString(), $publisher);
    }

    public static function close(Opportunity $opportunity, User $by, ?string $reason = null): void
    {
        self::setStatus($opportunity, Opportunity::CLOSED, 'closed', trim((string) $reason) ?: null, $by);
    }

    /**
     * Take an opportunity down early, e.g. when the offer was cancelled.
     */
    public static function withdraw(Opportunity $opportunity, User $by, string $reason): void
    {
        self::setStatus($opportunity, Opportunity::WITHDRAWN, 'withdrawn', Review::requireReason($reason), $by);
    }

    public static function archive(Opportunity $opportunity, User $by, string $reason): void
    {
        $opportunity->forceFill(['archived_at' => now()]);
        self::setStatus($opportunity, Opportunity::ARCHIVED, 'archived', Review::requireReason($reason), $by);
    }

    /**
     * Go live on schedule, close at the deadline and archive old listings.
     * Run by the scheduler.
     */
    public static function applySchedule(): int
    {
        $changed = 0;

        Opportunity::where('publication_status', Opportunity::SCHEDULED)->where('publish_at', '<=', now())->each(function (Opportunity $opportunity) use (&$changed) {
            $opportunity->forceFill(['publication_status' => Opportunity::PUBLISHED, 'is_active' => true])->save();
            Review::event($opportunity, 'went_live', null, null);
            $changed++;
        });

        Opportunity::where('publication_status', Opportunity::PUBLISHED)->whereNotNull('deadline')->where('deadline', '<', now()->toDateString())->each(function (Opportunity $opportunity) use (&$changed) {
            $opportunity->forceFill(['publication_status' => Opportunity::CLOSED, 'closed_at' => now(), 'is_active' => false])->save();
            Review::event($opportunity, 'deadline_passed', null, null);
            $changed++;
        });

        Opportunity::whereIn('publication_status', [Opportunity::CLOSED, Opportunity::WITHDRAWN])->where('closed_at', '<=', now()->subDays(self::ARCHIVE_AFTER_DAYS))->each(function (Opportunity $opportunity) use (&$changed) {
            $opportunity->forceFill(['publication_status' => Opportunity::ARCHIVED, 'archived_at' => now()])->save();
            Review::event($opportunity, 'archived', __('Archived automatically'), null);
            $changed++;
        });

        return $changed;
    }

    // ─── Members (OPP-REQ04-06) ────────────────────────────────────

    /**
     * Open opportunities for a member, closing soonest first, with filters.
     *
     * @return Collection<int, Opportunity>
     */
    public static function browse(User $member, string $search = '', string $type = '', bool $closingSoon = false): Collection
    {
        $search = trim($search);

        return Opportunity::open()
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('title', 'like', "%{$search}%")->orWhere('organization', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")))
            ->when($closingSoon, fn ($query) => $query->whereNotNull('deadline')->where('deadline', '<=', now()->addDays(Opportunity::CLOSING_SOON_DAYS)->toDateString()))
            ->orderByRaw('deadline is null')
            ->orderBy('deadline')
            ->latest('published_at')
            ->limit(200)
            ->get()
            ->filter(fn (Opportunity $opportunity) => Audience::includes($opportunity->audience, $member))
            ->values();
    }

    public static function isSaved(Opportunity $opportunity, User $member): bool
    {
        return DB::table('opportunity_saves')->where('user_id', $member->user_id)->where('opportunity_id', $opportunity->opportunity_id)->exists();
    }

    public static function save(Opportunity $opportunity, User $member): void
    {
        if (! $opportunity->isVisibleTo($member)) {
            throw new InvalidArgumentException(__('This opportunity is no longer open.'));
        }

        if (DB::table('opportunity_saves')->insertOrIgnore(['user_id' => $member->user_id, 'opportunity_id' => $opportunity->opportunity_id, 'created_at' => now()])) {
            self::record($opportunity, $member, 'save');
        }
    }

    public static function unsave(Opportunity $opportunity, User $member): void
    {
        DB::table('opportunity_saves')->where('user_id', $member->user_id)->where('opportunity_id', $opportunity->opportunity_id)->delete();
    }

    /**
     * @return Collection<int, Opportunity>
     */
    public static function savedBy(User $member): Collection
    {
        return Opportunity::whereIn('opportunity_id', DB::table('opportunity_saves')->where('user_id', $member->user_id)->select('opportunity_id'))
            ->whereNotIn('publication_status', [Opportunity::ARCHIVED])
            ->orderByRaw('deadline is null')
            ->orderBy('deadline')
            ->get();
    }

    /**
     * Count a view once per member per day, and every click on the
     * application channel.
     */
    public static function record(Opportunity $opportunity, ?User $member, string $action): void
    {
        if ($action === 'view' && $member && DB::table('opportunity_engagements')->where('opportunity_id', $opportunity->opportunity_id)->where('user_id', $member->user_id)->where('action', 'view')->where('created_at', '>=', now()->startOfDay())->exists()) {
            return;
        }

        DB::table('opportunity_engagements')->insert([
            'opportunity_id' => $opportunity->opportunity_id,
            'user_id' => $member?->user_id,
            'action' => $action,
            'user_type' => $member?->user_type,
            'region' => $member?->institution?->region,
            'created_at' => now(),
        ]);
    }

    /**
     * Career guidance and employability lessons for a member (OPP-REQ07).
     *
     * @return Collection<int, Content>
     */
    public static function careerResources(User $member, int $limit = 6): Collection
    {
        $category = ContentCategory::where('slug', self::CAREER_CATEGORY)->value('id');

        if (! $category) {
            return collect();
        }

        return Content::live()->availableTo($member)->where('category_id', $category)->latest('published_at')->limit($limit)->get();
    }

    // ─── Reporting (OPP-REQ08) ─────────────────────────────────────

    /**
     * Aggregated engagement between two dates.
     *
     * @return array{totals: array<string, int>, by_type: Collection<int, object>, by_member_type: Collection<int, object>, by_region: Collection<int, object>, top: Collection<int, object>, career: Collection<int, object>}
     */
    public static function report(Carbon $from, Carbon $to): array
    {
        $engagements = fn () => DB::table('opportunity_engagements')->whereBetween('opportunity_engagements.created_at', [$from, $to]);
        $sum = "count(distinct case when action = 'view' then coalesce(user_id, cast(id as char)) end) as viewers, sum(case when action = 'save' then 1 else 0 end) as saves, sum(case when action = 'apply' then 1 else 0 end) as applies";

        $published = Opportunity::where('published_at', '<=', $to)->where(fn ($query) => $query->whereNull('closed_at')->orWhere('closed_at', '>=', $from))->whereNotIn('publication_status', [Opportunity::NOT_PUBLISHED, Opportunity::SCHEDULED]);

        $careerCategory = ContentCategory::where('slug', self::CAREER_CATEGORY)->value('id');

        return [
            'totals' => [
                'published' => (clone $published)->count(),
                'views' => (int) $engagements()->where('action', 'view')->count(),
                'saves' => (int) $engagements()->where('action', 'save')->count(),
                'applies' => (int) $engagements()->where('action', 'apply')->count(),
                'members' => (int) $engagements()->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
            ],
            'by_type' => $engagements()->join('opportunities', 'opportunities.opportunity_id', '=', 'opportunity_engagements.opportunity_id')
                ->groupBy('opportunities.type')->selectRaw('opportunities.type as label, '.$sum)->orderByDesc('applies')->get(),
            'by_member_type' => $engagements()->groupBy('user_type')->selectRaw('user_type as label, '.$sum)->orderByDesc('viewers')->get(),
            'by_region' => $engagements()->groupBy('region')->selectRaw('region as label, '.$sum)->orderByDesc('viewers')->limit(10)->get(),
            'top' => $engagements()->join('opportunities', 'opportunities.opportunity_id', '=', 'opportunity_engagements.opportunity_id')
                ->groupBy('opportunities.opportunity_id', 'opportunities.title', 'opportunities.code')
                ->selectRaw('opportunities.opportunity_id, opportunities.title, opportunities.code, '.$sum)
                ->orderByDesc('applies')->orderByDesc('viewers')->limit(10)->get(),
            'career' => $careerCategory ? DB::table('user_progress')
                ->join('content', 'content.content_id', '=', 'user_progress.content_id')
                ->where('content.category_id', $careerCategory)
                ->where(fn ($query) => $query->whereBetween('user_progress.updated_at', [$from, $to])->orWhereBetween('user_progress.created_at', [$from, $to]))
                ->groupBy('content.content_id', 'content.title')
                ->selectRaw("content.title, count(distinct user_progress.user_id) as readers, sum(case when user_progress.status = 'completed' then 1 else 0 end) as completed")
                ->orderByDesc('readers')->limit(10)->get() : collect(),
        ];
    }

    /**
     * Engagement for one opportunity.
     *
     * @return array{viewers: int, saves: int, applies: int, saved_now: int}
     */
    public static function engagement(Opportunity $opportunity): array
    {
        $rows = DB::table('opportunity_engagements')->where('opportunity_id', $opportunity->opportunity_id);

        return [
            'viewers' => (int) (clone $rows)->where('action', 'view')->distinct('user_id')->count('user_id'),
            'saves' => (int) (clone $rows)->where('action', 'save')->count(),
            'applies' => (int) (clone $rows)->where('action', 'apply')->count(),
            'saved_now' => DB::table('opportunity_saves')->where('opportunity_id', $opportunity->opportunity_id)->count(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            'job' => __('Job'),
            'internship' => __('Internship'),
            'attachment' => __('Field attachment'),
            'scholarship' => __('Scholarship'),
            'volunteer' => __('Volunteering'),
            'training' => __('Training'),
            'entrepreneurship' => __('Entrepreneurship'),
            'competition' => __('Competition'),
            'other' => __('Other'),
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public static function only(array $fields): array
    {
        $values = array_intersect_key($fields, array_flip(self::FIELDS));

        if (array_key_exists('audience', $values)) {
            $values['audience'] = Audience::isEveryone($values['audience']) ? null : Audience::normalize($values['audience']);
        }

        foreach (['eligibility', 'location', 'region', 'work_mode', 'deadline', 'apply_url', 'apply_email', 'apply_instructions'] as $nullable) {
            if (array_key_exists($nullable, $values) && blank($values[$nullable])) {
                $values[$nullable] = null;
            }
        }

        return $values;
    }

    private static function storeAttachment(Opportunity $opportunity, ?UploadedFile $attachment): void
    {
        if (! $attachment) {
            return;
        }

        if ($opportunity->attachment_path) {
            Storage::disk(self::ATTACHMENT_DISK)->delete($opportunity->attachment_path);
        }

        $opportunity->attachment_path = $attachment->store('opportunities', self::ATTACHMENT_DISK);
        $opportunity->attachment_name = Str::limit($attachment->getClientOriginalName(), 190, '');
    }

    private static function setStatus(Opportunity $opportunity, string $status, string $action, ?string $reason, User $by): void
    {
        if (! in_array($opportunity->publication_status, [Opportunity::PUBLISHED, Opportunity::SCHEDULED, Opportunity::CLOSED, Opportunity::WITHDRAWN], true)) {
            throw new InvalidArgumentException(__('This action is not available now.'));
        }

        $opportunity->forceFill([
            'publication_status' => $status,
            'status_reason' => $reason ?? $opportunity->status_reason,
            'closed_at' => $opportunity->closed_at ?? now(),
            'is_active' => false,
        ])->save();

        Review::event($opportunity, $action, $reason, $by);
    }
}
