<?php

namespace App\Support;

use App\Models\Content;
use App\Models\ContentVersion;
use App\Models\EditorialEvent;
use App\Models\User;
use App\Notifications\ContentReviewed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Authoring, review and publication of learning content (LRN-REQ01-03, 08).
 *
 *   draft ─submit→ in_review ─approve→ approved ─publish→ published
 *                      │ └─return→ returned ─(edit, submit)→ …
 *                      └─reject→ rejected
 *
 * Publishing copies the approved revision onto the live content row; the
 * previous published revision becomes "superseded". Reviewers may not
 * approve their own submissions (four-eyes) unless switched off.
 */
final class ContentWorkflow
{
    /** Tax content is due for review a year after publication (decision D-10). */
    public const REVIEW_CYCLE_MONTHS = 12;

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function create(array $fields, User $author): Content
    {
        return DB::transaction(function () use ($fields, $author) {
            $content = Content::create([
                ...self::only($fields),
                'code' => self::nextCode(),
                'uploaded_by' => $author->user_id,
                'publication_status' => Content::NOT_PUBLISHED,
                'is_active' => false,
            ]);

            ContentVersion::create([
                'content_id' => $content->content_id,
                'version' => 1,
                'status' => ContentVersion::DRAFT,
                'fields' => self::only($fields),
                'created_by' => $author->user_id,
            ]);

            self::event($content, 1, 'created', null, $author);

            return $content;
        });
    }

    /**
     * The revision being worked on, if any.
     */
    public static function openRevision(Content $content): ?ContentVersion
    {
        return $content->versions()->open()->first();
    }

    /**
     * Save changes. Editing an item that is in review or approved sends the
     * revision back to draft; editing live content starts a new revision
     * while members keep seeing the published version.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function saveDraft(Content $content, array $fields, User $author, ?string $changeNote = null): ContentVersion
    {
        return DB::transaction(function () use ($content, $fields, $author, $changeNote) {
            $revision = self::openRevision($content);

            if (! $revision) {
                $revision = ContentVersion::create([
                    'content_id' => $content->content_id,
                    'version' => (int) $content->versions()->max('version') + 1,
                    'status' => ContentVersion::DRAFT,
                    'fields' => self::liveFields($content),
                    'created_by' => $author->user_id,
                ]);
                self::event($content, $revision->version, 'revision_started', null, $author);
            } elseif (! in_array($revision->status, ContentVersion::EDITABLE, true)) {
                self::event($content, $revision->version, 'back_to_draft', __('Edited after it was submitted'), $author);
            }

            $revision->forceFill([
                'fields' => self::only($fields),
                'status' => ContentVersion::DRAFT,
                'change_note' => $changeNote ?? $revision->change_note,
                'submitted_at' => null,
                'reviewed_at' => null,
                'reviewed_by' => null,
            ])->save();

            // Never-published items show their working title in lists.
            if ($content->current_version === null) {
                $content->forceFill(self::only($fields))->save();
            }

            return $revision;
        });
    }

    /**
     * Problems that stop a revision from going to review.
     *
     * @return list<string>
     */
    public static function readinessErrors(ContentVersion $revision): array
    {
        $fields = $revision->fields;
        $errors = [];

        if (blank($fields['title'] ?? null)) {
            $errors[] = __('Add a title.');
        }

        if (blank($fields['category_id'] ?? null)) {
            $errors[] = __('Choose a category.');
        }

        $hasBody = filled(trim(strip_tags((string) ($fields['body_text'] ?? ''))));
        $hasMedia = filled($fields['media_path'] ?? null) || filled($fields['file_url'] ?? null);

        match ($fields['content_type'] ?? 'article') {
            'article' => $hasBody ?: $errors[] = __('Write the lesson text.'),
            'video' => filled($fields['file_url'] ?? null) ?: $errors[] = __('Add the video link.'),
            default => $hasMedia ?: $errors[] = __('Upload the file or add its link.'),
        };

        return $errors;
    }

    public static function submit(Content $content, User $author, ?string $changeNote = null): void
    {
        $revision = self::requireRevision($content, ContentVersion::EDITABLE, __('There is nothing to submit.'));

        if ($errors = self::readinessErrors($revision)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        if ($revision->version > 1 && blank($changeNote ?? $revision->change_note)) {
            throw new InvalidArgumentException(__('Describe what changed, so the reviewer knows what to check.'));
        }

        $revision->forceFill([
            'status' => ContentVersion::IN_REVIEW,
            'submitted_by' => $author->user_id,
            'submitted_at' => now(),
            'change_note' => $changeNote ?? $revision->change_note,
            'review_note' => null,
        ])->save();

        self::event($content, $revision->version, 'submitted', $revision->change_note, $author);
    }

    public static function approve(Content $content, User $reviewer, ?string $note = null): void
    {
        self::decide($content, $reviewer, ContentVersion::APPROVED, $note);
    }

    public static function returnForChanges(Content $content, User $reviewer, string $note): void
    {
        self::decide($content, $reviewer, ContentVersion::RETURNED, $note);
    }

    public static function reject(Content $content, User $reviewer, string $note): void
    {
        self::decide($content, $reviewer, ContentVersion::REJECTED, $note);
    }

    /**
     * Publish the approved revision now or from $publishAt, optionally until
     * $expiresAt. Without an approved revision, republishes the current one.
     */
    public static function publish(Content $content, User $publisher, ?Carbon $publishAt = null, ?Carbon $expiresAt = null): void
    {
        $publishAt = $publishAt && $publishAt->isFuture() ? $publishAt : null;

        if ($expiresAt && $expiresAt->lte($publishAt ?? now())) {
            throw new InvalidArgumentException(__('The end date must be after the publication date.'));
        }

        $revision = $content->versions()->where('status', ContentVersion::APPROVED)->first();

        if (! $revision && $content->current_version === null) {
            throw new InvalidArgumentException(__('Only approved content can be published.'));
        }

        DB::transaction(function () use ($content, $publisher, $publishAt, $expiresAt, $revision) {
            if ($revision) {
                $content->versions()->where('status', ContentVersion::PUBLISHED)->update(['status' => ContentVersion::SUPERSEDED]);
                $revision->forceFill(['status' => ContentVersion::PUBLISHED, 'published_by' => $publisher->user_id, 'published_at' => $publishAt ?? now()])->save();
                $content->forceFill([...self::only($revision->fields), 'current_version' => $revision->version]);
            }

            $content->forceFill([
                'publication_status' => $publishAt ? Content::SCHEDULED : Content::PUBLISHED,
                'publish_at' => $publishAt,
                'expires_at' => $expiresAt,
                'archived_at' => null,
                'published_at' => $publishAt ?? now(),
                'review_due_at' => ($publishAt ?? now())->copy()->addMonths(self::REVIEW_CYCLE_MONTHS),
                'is_active' => true,
            ])->save();

            self::event($content, $content->current_version, $publishAt ? 'scheduled' : 'published', $publishAt?->toDayDateTimeString(), $publisher);
        });
    }

    public static function unpublish(Content $content, User $by, string $reason): void
    {
        self::requireReason($reason);
        self::setPublication($content, Content::UNPUBLISHED, 'unpublished', $reason, $by);
    }

    public static function archive(Content $content, User $by, string $reason): void
    {
        self::requireReason($reason);
        $content->forceFill(['archived_at' => now()]);
        self::setPublication($content, Content::ARCHIVED, 'archived', $reason, $by);
    }

    /**
     * Mark scheduled items that reached their date as published, and items
     * past their end date as unpublished. Run by the scheduler.
     */
    public static function applySchedule(): int
    {
        $changed = 0;

        Content::where('publication_status', Content::SCHEDULED)->where('publish_at', '<=', now())->each(function (Content $content) use (&$changed) {
            $content->forceFill(['publication_status' => Content::PUBLISHED])->save();
            self::event($content, $content->current_version, 'went_live', null, null);
            $changed++;
        });

        Content::where('publication_status', Content::PUBLISHED)->whereNotNull('expires_at')->where('expires_at', '<=', now())->each(function (Content $content) use (&$changed) {
            $content->forceFill(['publication_status' => Content::UNPUBLISHED, 'is_active' => false])->save();
            self::event($content, $content->current_version, 'expired', null, null);
            $changed++;
        });

        return $changed;
    }

    public static function fourEyes(): bool
    {
        return (bool) config('tax_club.four_eyes_review', true);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public static function only(array $fields): array
    {
        $values = array_intersect_key($fields, array_flip(Content::VERSIONED_FIELDS));

        if (array_key_exists('audience', $values)) {
            $values['audience'] = array_values(array_intersect((array) $values['audience'], Content::AUDIENCES)) ?: null;
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public static function liveFields(Content $content): array
    {
        return collect(Content::VERSIONED_FIELDS)->mapWithKeys(fn ($field) => [$field => $content->getAttribute($field)])->all();
    }

    private static function decide(Content $content, User $reviewer, string $status, ?string $note): void
    {
        $revision = self::requireRevision($content, [ContentVersion::IN_REVIEW], __('Nothing is waiting for review.'));

        if ($status !== ContentVersion::APPROVED && blank($note)) {
            throw new InvalidArgumentException(__('Tell the author what to change.'));
        }

        if ($status === ContentVersion::APPROVED && self::fourEyes() && $revision->submitted_by === $reviewer->user_id) {
            throw new InvalidArgumentException(__('Someone else must review content you submitted.'));
        }

        $revision->forceFill([
            'status' => $status,
            'reviewed_by' => $reviewer->user_id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ])->save();

        self::event($content, $revision->version, $status, $note, $reviewer);

        if ($revision->submitter && ! $revision->submitter->is($reviewer)) {
            Notify::send($revision->submitter, new ContentReviewed(
                $revision->field('title') ?? $content->title,
                $status,
                $note,
                route('admin.learning.show', $content),
            ));
        }
    }

    /**
     * @param  list<string>  $statuses
     */
    private static function requireRevision(Content $content, array $statuses, string $message): ContentVersion
    {
        $revision = self::openRevision($content);

        if (! $revision || ! in_array($revision->status, $statuses, true)) {
            throw new InvalidArgumentException($message);
        }

        return $revision;
    }

    private static function setPublication(Content $content, string $status, string $action, string $reason, User $by): void
    {
        if ($content->current_version === null) {
            throw new InvalidArgumentException(__('This content has never been published.'));
        }

        $content->forceFill(['publication_status' => $status, 'is_active' => false])->save();
        self::event($content, $content->current_version, $action, $reason, $by);
    }

    private static function requireReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException(__('Give a reason.'));
        }
    }

    private static function nextCode(): string
    {
        $last = Content::where('code', 'like', 'LC-%')->orderByDesc('code')->value('code');

        return sprintf('LC-%04d', $last ? ((int) substr($last, 3)) + 1 : 1);
    }

    public static function event(Content $content, ?int $version, string $action, ?string $note, ?User $user): void
    {
        EditorialEvent::create([
            'subject_type' => 'content',
            'subject_id' => $content->content_id,
            'version' => $version,
            'action' => $action,
            'note' => $note,
            'user_id' => $user?->user_id,
        ]);
    }
}
