<?php

namespace App\Support;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionPrize;
use App\Models\CompetitionScore;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Notifications\CompetitionUpdate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Competitions from proposal to published results (CMP-REQ01-05).
 *
 *   draft ─submit→ in_review ─approve→ approved ─publish→ published
 *   entries: registered ─submit→ submitted (or scored from the quiz)
 *   results: pending ─rank & submit→ in_review ─approve→ published
 *
 * Results need a second officer's approval. Publishing them notifies
 * participants, issues certificates and awards the configured prizes.
 */
final class CompetitionDesk
{
    public const FIELDS = ['title', 'description', 'rules', 'type', 'quiz_id', 'submission_kind', 'audience', 'max_participants', 'registration_closes_at', 'starts_at', 'ends_at', 'winners_count'];

    public const SUBMISSION_DISK = 'local';

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function create(array $fields, User $author): Competition
    {
        $competition = new Competition([...self::only($fields), 'code' => Review::nextCode(Competition::class, 'CP'), 'created_by' => $author->user_id]);
        $competition->forceFill(['editorial_status' => Review::DRAFT, 'publication_status' => Competition::NOT_PUBLISHED])->save();
        Review::event($competition, 'created', null, $author);

        return $competition;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function update(Competition $competition, array $fields, User $by): void
    {
        if ($competition->results_status === Competition::RESULTS_PUBLISHED || $competition->publication_status === Competition::CANCELLED) {
            throw new InvalidArgumentException(__('This competition can no longer be changed.'));
        }

        $competition->fill(self::only($fields));
        $changed = array_keys($competition->getDirty());

        if ($changed === []) {
            return;
        }

        $competition->save();

        if ($competition->publication_status === Competition::NOT_PUBLISHED) {
            Review::backToDraft($competition, $by);
        } else {
            Review::event($competition, 'edited', implode(', ', $changed), $by);
        }
    }

    /**
     * @return list<string>
     */
    public static function readinessErrors(Competition $competition): array
    {
        $errors = [];

        if (blank($competition->title) || blank($competition->description)) {
            $errors[] = __('Add a title and a description.');
        }

        if (! $competition->starts_at || ! $competition->ends_at) {
            $errors[] = __('Set when the competition starts and ends.');
        } elseif ($competition->ends_at->lte($competition->starts_at)) {
            $errors[] = __('The end must be after the start.');
        }

        if ($competition->type === 'quiz') {
            $quiz = $competition->quiz;

            if (! $quiz) {
                $errors[] = __('Choose the quiz used for this competition.');
            } elseif (! in_array($quiz->editorial_status, [Quiz::APPROVED], true)) {
                $errors[] = __('The competition quiz must be approved first.');
            }
        } elseif (! in_array($competition->submission_kind, Competition::SUBMISSION_KINDS, true)) {
            $errors[] = __('Choose what participants submit.');
        }

        if ($competition->registration_closes_at && $competition->ends_at && $competition->registration_closes_at->gt($competition->ends_at)) {
            $errors[] = __('Registration must close before the competition ends.');
        }

        return $errors;
    }

    public static function submit(Competition $competition, User $author): void
    {
        Review::submit($competition, $author, self::readinessErrors($competition));
    }

    public static function approve(Competition $competition, User $reviewer, ?string $note = null): void
    {
        Review::decide($competition, $reviewer, Review::APPROVED, $note);
    }

    public static function returnForChanges(Competition $competition, User $reviewer, string $note): void
    {
        Review::decide($competition, $reviewer, Review::RETURNED, $note);
    }

    public static function reject(Competition $competition, User $reviewer, string $note): void
    {
        Review::decide($competition, $reviewer, Review::REJECTED, $note);
    }

    public static function publish(Competition $competition, User $publisher, ?Carbon $publishAt = null): void
    {
        if ($competition->editorial_status !== Review::APPROVED) {
            throw new InvalidArgumentException(__('Only approved competitions can be published.'));
        }

        if ($errors = self::readinessErrors($competition)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $publishAt = $publishAt && $publishAt->isFuture() ? $publishAt : null;

        $competition->forceFill([
            'publication_status' => $publishAt ? Competition::SCHEDULED : Competition::PUBLISHED,
            'publish_at' => $publishAt,
            'published_by' => $publisher->user_id,
            'published_at' => $publishAt ?? now(),
        ])->save();

        // The quiz behind a quiz competition opens for the competition period.
        if ($competition->type === 'quiz' && $competition->quiz && $competition->quiz->publication_status === Quiz::NOT_PUBLISHED) {
            QuizWorkflow::publish($competition->quiz, $publisher, $competition->starts_at, $competition->ends_at);
        }

        Review::event($competition, $publishAt ? 'scheduled' : 'published', $publishAt?->toDayDateTimeString(), $publisher);
    }

    public static function cancel(Competition $competition, User $by, string $reason): void
    {
        $reason = Review::requireReason($reason);
        $competition->forceFill(['publication_status' => Competition::CANCELLED, 'status_reason' => $reason])->save();
        Review::event($competition, 'cancelled', $reason, $by);
        self::tellParticipants($competition, __('was cancelled: :reason', ['reason' => $reason]));
    }

    public static function applySchedule(): int
    {
        $changed = 0;

        Competition::where('publication_status', Competition::SCHEDULED)->where('publish_at', '<=', now())->each(function (Competition $competition) use (&$changed) {
            $competition->forceFill(['publication_status' => Competition::PUBLISHED])->save();
            Review::event($competition, 'went_live', null, null);
            $changed++;
        });

        return $changed;
    }

    // ─── Participation (CMP-REQ03, CMP-REQ04) ──────────────────────

    public static function entryFor(Competition $competition, User $member): ?CompetitionEntry
    {
        return $competition->entries()->where('user_id', $member->user_id)->first();
    }

    public static function registrationProblem(Competition $competition, User $member): ?string
    {
        $entry = self::entryFor($competition, $member);

        return match (true) {
            ! $member->isActive() => __('Only active members can take part.'),
            SafetyDesk::isRestricted($member, 'all') => __('Your account is restricted, so you cannot take part right now.'),
            ! $competition->isVisibleTo($member) => __('This competition is not open to your membership category or region.'),
            $entry !== null && $entry->status !== CompetitionEntry::WITHDRAWN => __('You are already registered.'),
            ! $competition->registrationOpen() => __('Registration has closed.'),
            $competition->max_participants !== null && $competition->entries()->where('status', '!=', CompetitionEntry::WITHDRAWN)->count() >= $competition->max_participants => __('This competition is full.'),
            default => null,
        };
    }

    public static function register(Competition $competition, User $member): CompetitionEntry
    {
        if ($problem = self::registrationProblem($competition, $member)) {
            throw new InvalidArgumentException($problem);
        }

        $entry = DB::transaction(function () use ($competition, $member) {
            Competition::whereKey($competition->competition_id)->lockForUpdate()->first();
            $entry = self::entryFor($competition, $member) ?? new CompetitionEntry(['competition_id' => $competition->competition_id, 'user_id' => $member->user_id]);
            $entry->forceFill(['status' => CompetitionEntry::REGISTERED, 'registered_at' => now(), 'status_reason' => null])->save();

            return $entry;
        });

        Notify::send($member, new CompetitionUpdate($competition->title, __('you are registered. Good luck!'), $competition->slug));

        return $entry;
    }

    public static function withdraw(Competition $competition, User $member): void
    {
        $entry = self::entryFor($competition, $member);

        if (! $entry || $entry->status === CompetitionEntry::WITHDRAWN || $competition->hasEnded()) {
            throw new InvalidArgumentException(__('You cannot withdraw now.'));
        }

        $entry->forceFill(['status' => CompetitionEntry::WITHDRAWN])->save();
    }

    /**
     * Hand in a submission (essay, link or file) during the competition.
     */
    public static function submitEntry(Competition $competition, User $member, ?string $text = null, ?string $url = null, ?UploadedFile $file = null): CompetitionEntry
    {
        $entry = self::entryFor($competition, $member);

        if (! $entry || ! in_array($entry->status, [CompetitionEntry::REGISTERED, CompetitionEntry::SUBMITTED], true)) {
            throw new InvalidArgumentException(__('Register for the competition first.'));
        }

        if ($competition->type !== 'submission') {
            throw new InvalidArgumentException(__('This competition is played through its quiz.'));
        }

        if (! $competition->isRunning()) {
            throw new InvalidArgumentException(__('Entries are accepted only while the competition is running.'));
        }

        $values = match ($competition->submission_kind) {
            'text' => filled(trim((string) $text)) ? ['submission_text' => trim(strip_tags($text))] : null,
            'link' => filter_var($url, FILTER_VALIDATE_URL) ? ['submission_url' => $url] : null,
            'file' => $file ? ['submission_file' => $file->store('competition-entries/'.$competition->competition_id, self::SUBMISSION_DISK)] : null,
            default => null,
        };

        if ($values === null) {
            throw new InvalidArgumentException(__('Add your entry before submitting.'));
        }

        if ($entry->submission_file && isset($values['submission_file'])) {
            Storage::disk(self::SUBMISSION_DISK)->delete($entry->submission_file);
        }

        $entry->forceFill([...$values, 'status' => CompetitionEntry::SUBMITTED, 'submitted_at' => now()])->save();

        return $entry;
    }

    // ─── Scoring and results (CMP-REQ05) ───────────────────────────

    public static function score(CompetitionEntry $entry, User $judge, float $score, ?string $comment = null): void
    {
        if ($entry->status !== CompetitionEntry::SUBMITTED) {
            throw new InvalidArgumentException(__('Only submitted entries can be scored.'));
        }

        if ($entry->user_id === $judge->user_id) {
            throw new InvalidArgumentException(__('You cannot score your own entry.'));
        }

        if ($score < 0 || $score > 100) {
            throw new InvalidArgumentException(__('Give a score from 0 to 100.'));
        }

        if ($entry->competition->results_status === Competition::RESULTS_PUBLISHED) {
            throw new InvalidArgumentException(__('Results are already published.'));
        }

        CompetitionScore::updateOrCreate(['entry_id' => $entry->id, 'judge_id' => $judge->user_id], ['score' => round($score, 2), 'comment' => trim((string) $comment) ?: null]);
        $entry->forceFill(['score' => round((float) $entry->scores()->avg('score'), 2)])->save();
    }

    public static function disqualify(CompetitionEntry $entry, User $by, string $reason): void
    {
        $entry->forceFill(['status' => CompetitionEntry::DISQUALIFIED, 'status_reason' => Review::requireReason($reason), 'rank' => null])->save();
        Review::event($entry->competition, 'disqualified', $entry->user?->membership_id.': '.$reason, $by);
    }

    /**
     * Work out scores and ranks. Quiz competitions use each participant's
     * best attempt made during the competition; ties go to the earlier
     * finisher.
     *
     * @return Collection<int, CompetitionEntry>
     */
    public static function rank(Competition $competition): Collection
    {
        if ($competition->results_status === Competition::RESULTS_PUBLISHED) {
            throw new InvalidArgumentException(__('Results are already published.'));
        }

        if ($competition->type === 'quiz') {
            self::scoreFromQuiz($competition);
        }

        $entries = $competition->entries()
            ->whereIn('status', [CompetitionEntry::SUBMITTED, CompetitionEntry::REGISTERED])
            ->whereNotNull('score')
            ->orderByDesc('score')
            ->orderBy('submitted_at')
            ->get();

        $competition->entries()->update(['rank' => null]);

        foreach ($entries as $position => $entry) {
            $entry->forceFill(['rank' => $position + 1])->save();
        }

        return $entries;
    }

    public static function submitResults(Competition $competition, User $by): void
    {
        if (! $competition->hasEnded()) {
            throw new InvalidArgumentException(__('Results can be prepared after the competition ends.'));
        }

        self::rank($competition);
        $competition->forceFill(['results_status' => Competition::RESULTS_IN_REVIEW, 'results_submitted_by' => $by->user_id])->save();
        Review::event($competition, 'results_submitted', null, $by);
    }

    /**
     * Approve and publish results: participants are told, certificates are
     * issued and prizes are awarded.
     */
    public static function approveResults(Competition $competition, User $approver): void
    {
        if ($competition->results_status !== Competition::RESULTS_IN_REVIEW) {
            throw new InvalidArgumentException(__('The results are not waiting for approval.'));
        }

        if (ContentWorkflow::fourEyes() && $competition->results_submitted_by === $approver->user_id) {
            throw new InvalidArgumentException(__('Someone else must approve results you prepared.'));
        }

        $competition->forceFill(['results_status' => Competition::RESULTS_PUBLISHED, 'results_approved_by' => $approver->user_id, 'results_published_at' => now()])->save();
        Review::event($competition, 'results_published', null, $approver);

        $prizes = $competition->prizes()->with('reward')->get();

        $competition->entries()->whereIn('status', [CompetitionEntry::SUBMITTED, CompetitionEntry::REGISTERED])->whereNotNull('rank')->with('user')->each(function (CompetitionEntry $entry) use ($competition, $prizes, $approver) {
            Certificates::forCompetition($entry->user, $competition, $entry->rank);

            $prizes->filter(fn (CompetitionPrize $prize) => $entry->rank >= $prize->rank_from && $entry->rank <= $prize->rank_to)
                ->each(fn (CompetitionPrize $prize) => Rewards::award($entry->user, $prize->reward, Certificates::ordinal($entry->rank).' · '.$competition->title, $approver, 'competition', $competition->competition_id));

            Notify::send($entry->user, new CompetitionUpdate($competition->title, __('results are out. You placed :rank.', ['rank' => Certificates::ordinal($entry->rank)]), $competition->slug));
        });
    }

    public static function returnResults(Competition $competition, User $approver, string $note): void
    {
        $competition->forceFill(['results_status' => Competition::RESULTS_PENDING])->save();
        Review::event($competition, 'results_returned', Review::requireReason($note), $approver);
    }

    /**
     * Published leaderboard: top places with short names (GAM-REQ10 rules).
     *
     * @return Collection<int, CompetitionEntry>
     */
    public static function results(Competition $competition, int $limit = 20): Collection
    {
        return $competition->entries()->whereNotNull('rank')->with('user:user_id,full_name,institution_id', 'user.institution:institution_id,name,region')->orderBy('rank')->limit($limit)->get();
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

        if (($values['type'] ?? null) === 'quiz') {
            $values['submission_kind'] = null;
        } elseif (($values['type'] ?? null) === 'submission') {
            $values['quiz_id'] = null;
        }

        foreach (['quiz_id', 'submission_kind', 'max_participants', 'registration_closes_at', 'rules'] as $nullable) {
            if (array_key_exists($nullable, $values) && blank($values[$nullable])) {
                $values[$nullable] = null;
            }
        }

        return $values;
    }

    private static function scoreFromQuiz(Competition $competition): void
    {
        $competition->entries()->whereIn('status', [CompetitionEntry::REGISTERED, CompetitionEntry::SUBMITTED])->each(function (CompetitionEntry $entry) use ($competition) {
            $best = QuizAttempt::where('quiz_id', $competition->quiz_id)
                ->where('user_id', $entry->user_id)
                ->whereNotNull('submitted_at')
                ->when($competition->starts_at, fn ($query) => $query->where('submitted_at', '>=', $competition->starts_at))
                ->when($competition->ends_at, fn ($query) => $query->where('submitted_at', '<=', $competition->ends_at->copy()->addMinutes(5)))
                ->orderByDesc('percent')
                ->orderBy('submitted_at')
                ->first();

            $entry->forceFill($best
                ? ['score' => (float) $best->percent, 'submitted_at' => $best->submitted_at, 'status' => CompetitionEntry::SUBMITTED]
                : ['score' => null]
            )->save();
        });
    }

    private static function tellParticipants(Competition $competition, string $update): void
    {
        $competition->entries()->where('status', '!=', CompetitionEntry::WITHDRAWN)->with('user')->each(
            fn (CompetitionEntry $entry) => Notify::send($entry->user, new CompetitionUpdate($competition->title, $update, $competition->slug))
        );
    }
}
