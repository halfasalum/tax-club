<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventUpdate;
use App\Models\MembershipVerification;
use App\Models\User;
use App\Notifications\EventChanged;
use App\Notifications\EventPlaceOffered;
use App\Notifications\EventRegistrationConfirmed;
use App\Notifications\EventUpdatePosted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Events and campaigns from proposal to participation summary
 * (EVT-REQ01-08).
 *
 *   draft ─submit→ in_review ─approve→ approved ─publish→ published
 *   published → postponed / cancelled → closed → archived
 *
 * Registration follows capacity: once full, members join the waitlist
 * (when enabled) and move up automatically when a place frees up.
 * Attendance is recorded by scanning the membership card QR code, typing
 * the membership number or ticking the registration list.
 */
final class EventManager
{
    /** Check-in opens this many hours before the start. */
    public const CHECK_IN_OPENS_HOURS = 3;

    /** Fields organizers fill in. */
    public const FIELDS = [
        'title', 'type', 'mode', 'description', 'objective', 'organizer', 'location', 'region', 'online_url',
        'starts_at', 'ends_at', 'audience', 'registration_required', 'registration_closes_at', 'capacity',
        'waitlist_enabled', 'registration_question', 'is_public',
    ];

    /** Changes that registered members are told about. */
    private const NOTIFIED_FIELDS = ['starts_at', 'ends_at', 'location', 'online_url'];

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function create(array $fields, User $author): Event
    {
        $event = Event::create([
            ...self::only($fields),
            'code' => Review::nextCode(Event::class, 'EV'),
            'created_by' => $author->user_id,
            'is_active' => false,
        ]);
        $event->forceFill(['editorial_status' => Review::DRAFT, 'publication_status' => Event::NOT_PUBLISHED])->save();

        Review::event($event, 'created', null, $author);

        return $event;
    }

    /**
     * Save changes. Before publication, changes send the event back to
     * draft for another review. After publication, organizers keep it up
     * to date and registered members hear about new dates or places.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function update(Event $event, array $fields, User $by): void
    {
        $values = self::only($fields);
        $event->fill($values);
        $changed = array_keys($event->getDirty());

        if ($changed === []) {
            return;
        }

        if (in_array($event->publication_status, [Event::CLOSED, Event::ARCHIVED], true)) {
            throw new InvalidArgumentException(__('Closed events cannot be changed.'));
        }

        $event->save();

        if ($event->publication_status === Event::NOT_PUBLISHED) {
            Review::backToDraft($event, $by);
        } else {
            Review::event($event, 'edited', implode(', ', $changed), $by);
        }

        if ($event->isVisible() && array_intersect($changed, self::NOTIFIED_FIELDS)) {
            self::tellParticipants($event, __('has new details: :when, :place', ['when' => self::when($event), 'place' => self::place($event)]), null);
        }

        if (in_array('capacity', $changed, true) || in_array('waitlist_enabled', $changed, true)) {
            self::fillFromWaitlist($event);
        }
    }

    /**
     * @return list<string>
     */
    public static function readinessErrors(Event $event): array
    {
        $errors = [];

        if (blank($event->title)) {
            $errors[] = __('Add a title.');
        }

        if (blank($event->description)) {
            $errors[] = __('Describe the event.');
        }

        if (! $event->starts_at) {
            $errors[] = __('Set the start date and time.');
        } elseif ($event->ends_at && $event->ends_at->lte($event->starts_at)) {
            $errors[] = __('The end must be after the start.');
        }

        if ($event->mode !== 'online' && blank($event->location)) {
            $errors[] = __('Add the venue.');
        }

        if ($event->mode !== 'in_person' && blank($event->online_url)) {
            $errors[] = __('Add the online meeting link.');
        }

        if ($event->registration_closes_at && $event->starts_at && $event->registration_closes_at->gt($event->ends_at ?? $event->starts_at)) {
            $errors[] = __('Registration must close before the event ends.');
        }

        return $errors;
    }

    public static function submit(Event $event, User $author): void
    {
        Review::submit($event, $author, self::readinessErrors($event));
    }

    public static function approve(Event $event, User $reviewer, ?string $note = null): void
    {
        Review::decide($event, $reviewer, Review::APPROVED, $note);
    }

    public static function returnForChanges(Event $event, User $reviewer, string $note): void
    {
        Review::decide($event, $reviewer, Review::RETURNED, $note);
    }

    public static function reject(Event $event, User $reviewer, string $note): void
    {
        Review::decide($event, $reviewer, Review::REJECTED, $note);
    }

    /**
     * Publish now or from $publishAt to the event's audience (EVT-REQ03).
     */
    public static function publish(Event $event, User $publisher, ?Carbon $publishAt = null): void
    {
        if ($event->editorial_status !== Review::APPROVED) {
            throw new InvalidArgumentException(__('Only approved events can be published.'));
        }

        if ($event->starts_at && $event->hasEnded()) {
            throw new InvalidArgumentException(__('This event has already ended.'));
        }

        $publishAt = $publishAt && $publishAt->isFuture() ? $publishAt : null;

        $event->forceFill([
            'publication_status' => $publishAt ? Event::SCHEDULED : Event::PUBLISHED,
            'publish_at' => $publishAt,
            'published_by' => $publisher->user_id,
            'published_at' => $publishAt ?? now(),
            'is_active' => $publishAt === null,
        ])->save();

        Review::event($event, $publishAt ? 'scheduled' : 'published', $publishAt?->toDayDateTimeString(), $publisher);
    }

    /**
     * Postpone with new dates, or until further notice (EVT-REQ03).
     */
    public static function postpone(Event $event, User $by, string $reason, ?Carbon $startsAt = null, ?Carbon $endsAt = null): void
    {
        $reason = Review::requireReason($reason);
        self::requireStatus($event, [Event::PUBLISHED, Event::POSTPONED]);

        if ($startsAt && $endsAt && $endsAt->lte($startsAt)) {
            throw new InvalidArgumentException(__('The end must be after the start.'));
        }

        $event->forceFill([
            'publication_status' => $startsAt ? Event::PUBLISHED : Event::POSTPONED,
            'starts_at' => $startsAt ?? $event->starts_at,
            'ends_at' => $startsAt ? $endsAt : $event->ends_at,
            'registration_closes_at' => $startsAt && $event->registration_closes_at?->gt($startsAt) ? $startsAt : $event->registration_closes_at,
            'status_reason' => $reason,
        ])->save();

        Review::event($event, 'postponed', $reason, $by);
        self::tellParticipants($event, $startsAt ? __('was moved to :when', ['when' => self::when($event)]) : __('was postponed until further notice'), $reason);
    }

    public static function cancel(Event $event, User $by, string $reason): void
    {
        $reason = Review::requireReason($reason);
        self::requireStatus($event, [Event::PUBLISHED, Event::POSTPONED, Event::SCHEDULED]);

        $event->forceFill(['publication_status' => Event::CANCELLED, 'status_reason' => $reason, 'is_active' => false])->save();
        Review::event($event, 'cancelled', $reason, $by);
        self::tellParticipants($event, __('was cancelled'), $reason);
    }

    /**
     * Close a finished event with notes (EVT-REQ08). Members who
     * registered but did not check in count as absent.
     */
    public static function close(Event $event, User $by, ?string $notes = null): void
    {
        self::requireStatus($event, [Event::PUBLISHED, Event::POSTPONED, Event::CANCELLED]);

        if ($event->publication_status !== Event::CANCELLED && ! $event->hasEnded()) {
            throw new InvalidArgumentException(__('You can close the event after it ends.'));
        }

        $event->forceFill([
            'publication_status' => Event::CLOSED,
            'closed_at' => now(),
            'closed_by' => $by->user_id,
            'closing_notes' => trim((string) $notes) ?: null,
            'is_active' => false,
        ])->save();

        $event->registrations()->where('status', EventRegistration::WAITLISTED)->update(['status' => EventRegistration::CANCELLED, 'cancelled_at' => now()]);
        Review::event($event, 'closed', $notes, $by);

        // Everyone who attended gets a certificate of participation (CER-REQ01).
        $event->registrations()->where('status', EventRegistration::ATTENDED)->with('user')->each(
            fn (EventRegistration $registration) => Certificates::forEvent($registration->user, $event)
        );
    }

    public static function archive(Event $event, User $by, string $reason): void
    {
        $reason = Review::requireReason($reason);
        $event->forceFill(['publication_status' => Event::ARCHIVED, 'is_active' => false])->save();
        Review::event($event, 'archived', $reason, $by);
    }

    /**
     * Make scheduled events live. Run by the scheduler.
     */
    public static function applySchedule(): int
    {
        $changed = 0;

        Event::where('publication_status', Event::SCHEDULED)->where('publish_at', '<=', now())->each(function (Event $event) use (&$changed) {
            $event->forceFill(['publication_status' => Event::PUBLISHED, 'is_active' => true])->save();
            Review::event($event, 'went_live', null, null);
            $changed++;
        });

        return $changed;
    }

    // ─── Registration (EVT-REQ04, EVT-REQ05) ───────────────────────

    public static function registrationFor(Event $event, User $user): ?EventRegistration
    {
        return $event->registrations()->where('user_id', $user->user_id)->first();
    }

    /**
     * Why a member cannot register now, or null if they can.
     */
    public static function registrationProblem(Event $event, User $user): ?string
    {
        return match (true) {
            ! $user->isActive() => __('Only active members can register.'),
            ! $event->isVisibleTo($user) => __('This event is not open to your membership category or region.'),
            ! $event->registration_required => __('No registration is needed. Just come along.'),
            $event->publication_status === Event::CANCELLED => __('This event was cancelled.'),
            $event->publication_status === Event::POSTPONED => __('This event was postponed. Registration opens again when there is a new date.'),
            ! $event->acceptsRegistrations() => __('Registration for this event has closed.'),
            $event->placesLeft() === 0 && ! $event->waitlist_enabled => __('This event is full.'),
            default => null,
        };
    }

    public static function register(Event $event, User $user, ?string $answer = null): EventRegistration
    {
        if ($problem = self::registrationProblem($event, $user)) {
            throw new InvalidArgumentException($problem);
        }

        $answer = trim((string) $answer);

        if (filled($event->registration_question) && $answer === '') {
            throw new InvalidArgumentException(__('Answer the organizer\'s question to register.'));
        }

        $registration = DB::transaction(function () use ($event, $user, $answer) {
            Event::whereKey($event->event_id)->lockForUpdate()->first();
            $registration = self::registrationFor($event, $user);

            if ($registration?->isActive()) {
                throw new InvalidArgumentException(__('You are already registered for this event.'));
            }

            $full = $event->capacity !== null && $event->placesTaken() >= $event->capacity;

            if ($full && ! $event->waitlist_enabled) {
                throw new InvalidArgumentException(__('This event is full.'));
            }

            $registration ??= new EventRegistration(['event_id' => $event->event_id, 'user_id' => $user->user_id]);
            $registration->forceFill([
                'status' => $full ? EventRegistration::WAITLISTED : EventRegistration::REGISTERED,
                'answer' => $answer ?: null,
                'registered_at' => now(),
                'waitlisted_at' => $full ? now() : null,
                'cancelled_at' => null,
                'promoted_at' => null,
            ])->save();

            return $registration;
        });

        Notify::send($user, new EventRegistrationConfirmed($event->title, self::when($event), $registration->status === EventRegistration::WAITLISTED, $event->slug));

        return $registration;
    }

    public static function cancelRegistration(Event $event, User $user): void
    {
        $registration = self::registrationFor($event, $user);

        if (! $registration || ! in_array($registration->status, [EventRegistration::REGISTERED, EventRegistration::WAITLISTED], true)) {
            throw new InvalidArgumentException(__('You are not registered for this event.'));
        }

        if ($event->hasEnded()) {
            throw new InvalidArgumentException(__('This event has already ended.'));
        }

        $registration->forceFill(['status' => EventRegistration::CANCELLED, 'cancelled_at' => now()])->save();
        self::fillFromWaitlist($event);
    }

    /**
     * Offer free places to the waitlist, oldest first.
     */
    public static function fillFromWaitlist(Event $event): int
    {
        $event->refresh();

        if (! $event->acceptsRegistrations() && $event->publication_status !== Event::PUBLISHED) {
            return 0;
        }

        $free = $event->capacity === null ? PHP_INT_MAX : $event->placesLeft();
        $promoted = 0;

        while ($free > 0) {
            $next = $event->registrations()->where('status', EventRegistration::WAITLISTED)->orderBy('waitlisted_at')->orderBy('id')->first();

            if (! $next) {
                break;
            }

            $next->forceFill(['status' => EventRegistration::REGISTERED, 'promoted_at' => now()])->save();
            Notify::send($next->user, new EventPlaceOffered($event->title, self::when($event), $event->slug));
            $free--;
            $promoted++;
        }

        return $promoted;
    }

    // ─── Check-in (EVT-REQ06) ──────────────────────────────────────

    public static function checkInIsOpen(Event $event): bool
    {
        return $event->publication_status === Event::PUBLISHED
            && $event->starts_at !== null
            && $event->starts_at->copy()->subHours(self::CHECK_IN_OPENS_HOURS)->isPast()
            && ! ($event->ends_at ?? $event->starts_at->copy()->endOfDay())->copy()->addHours(6)->isPast();
    }

    /**
     * Find the member from a scanned card (the QR code holds the verify
     * link) or a typed membership number.
     */
    public static function memberFromCard(string $input): ?User
    {
        $input = trim($input);

        if (preg_match('~/verify/([A-Za-z0-9]{32})~', $input, $match) || preg_match('/^([A-Za-z0-9]{32})$/', $input, $match)) {
            return User::where('card_token', $match[1])->first();
        }

        $number = MembershipCard::normalizeNumber($input);

        if (MembershipCard::isMistyped($number)) {
            throw new InvalidArgumentException(__('This membership number is not correct. Check it and try again.'));
        }

        return User::where('membership_id', $number)->first();
    }

    /**
     * Record attendance. Walk-ins (members who did not register) are
     * accepted only when the officer confirms it.
     *
     * @return array{result: string, registration: ?EventRegistration, member: ?User}
     *                                                                                result: checked_in, already, not_registered, not_valid, not_found
     */
    public static function checkIn(Event $event, string $input, User $officer, bool $allowWalkIn = false): array
    {
        if (! self::checkInIsOpen($event)) {
            throw new InvalidArgumentException(__('Check-in is open from :hours hours before the event starts until it ends.', ['hours' => self::CHECK_IN_OPENS_HOURS]));
        }

        $looksLikeQr = (bool) preg_match('~/verify/|^[A-Za-z0-9]{32}$~', trim($input));
        $member = self::memberFromCard($input);

        MembershipVerification::create([
            'member_id' => $member?->user_id,
            'verified_by' => $officer->user_id,
            'method' => $looksLikeQr ? MembershipVerification::METHOD_QR : MembershipVerification::METHOD_NUMBER,
            'result' => $member ? (MembershipCard::isValid($member) ? MembershipCard::RESULT_VALID : MembershipCard::RESULT_NOT_VALID) : MembershipCard::RESULT_NOT_FOUND,
            'full_details' => false,
            'ip' => request()?->ip(),
        ]);

        if (! $member) {
            return ['result' => 'not_found', 'registration' => null, 'member' => null];
        }

        if (! MembershipCard::isValid($member)) {
            return ['result' => 'not_valid', 'registration' => null, 'member' => $member];
        }

        $registration = self::registrationFor($event, $member);

        if ($registration?->status === EventRegistration::ATTENDED) {
            return ['result' => 'already', 'registration' => $registration, 'member' => $member];
        }

        $isRegistered = in_array($registration?->status, [EventRegistration::REGISTERED, EventRegistration::WAITLISTED], true);

        if (! $isRegistered && $event->registration_required && ! $allowWalkIn) {
            return ['result' => 'not_registered', 'registration' => $registration, 'member' => $member];
        }

        $registration = self::markAttended($event, $member, $officer, $looksLikeQr ? EventRegistration::CHECK_IN_QR : EventRegistration::CHECK_IN_NUMBER, ! $isRegistered);

        return ['result' => 'checked_in', 'registration' => $registration, 'member' => $member];
    }

    /**
     * Tick a member on the registration list.
     */
    public static function checkInManually(Event $event, EventRegistration $registration, User $officer): void
    {
        if (! self::checkInIsOpen($event)) {
            throw new InvalidArgumentException(__('Check-in is open from :hours hours before the event starts until it ends.', ['hours' => self::CHECK_IN_OPENS_HOURS]));
        }

        if ($registration->event_id !== $event->event_id || $registration->status === EventRegistration::CANCELLED) {
            throw new InvalidArgumentException(__('This member is not registered for this event.'));
        }

        self::markAttended($event, $registration->user, $officer, EventRegistration::CHECK_IN_MANUAL);
    }

    /**
     * Correct attendance recorded by mistake, with a reason (EVT-REQ06).
     */
    public static function correctAttendance(EventRegistration $registration, bool $attended, string $reason, User $officer): void
    {
        $reason = Review::requireReason($reason);
        $event = $registration->event;

        if ($event->publication_status === Event::ARCHIVED) {
            throw new InvalidArgumentException(__('Archived events cannot be changed.'));
        }

        $before = ['status' => $registration->status];

        $registration->forceFill($attended
            ? ['status' => EventRegistration::ATTENDED, 'checked_in_at' => $registration->checked_in_at ?? now(), 'checked_in_by' => $officer->user_id, 'check_in_method' => EventRegistration::CHECK_IN_MANUAL, 'correction_reason' => $reason]
            : ['status' => EventRegistration::REGISTERED, 'checked_in_at' => null, 'checked_in_by' => null, 'check_in_method' => null, 'correction_reason' => $reason]
        )->save();

        Audit::record('event.attendance_corrected', $registration->user, $before, ['status' => $registration->status, 'event' => $event->code], $reason, $officer);

        if ($attended) {
            Gamification::eventAttended($registration->user, $event);
        } else {
            Gamification::revoke($registration->user, 'event_attended', 'event', $event->event_id, __('Attendance corrected: :title', ['title' => $event->title]));
        }
    }

    private static function markAttended(Event $event, User $member, User $officer, string $method, bool $walkIn = false): EventRegistration
    {
        $registration = self::registrationFor($event, $member) ?? new EventRegistration(['event_id' => $event->event_id, 'user_id' => $member->user_id]);
        $registration->forceFill([
            'walk_in' => $walkIn,
            'status' => EventRegistration::ATTENDED,
            'registered_at' => $registration->registered_at ?? now(),
            'checked_in_at' => now(),
            'checked_in_by' => $officer->user_id,
            'check_in_method' => $method,
        ])->save();

        Gamification::eventAttended($member, $event);

        return $registration;
    }

    // ─── Updates and materials (EVT-REQ07) ─────────────────────────

    /**
     * @param  array{kind?: string, title: string, body?: ?string, link_url?: ?string, registered_only?: bool}  $values
     */
    public static function postUpdate(Event $event, array $values, User $by, ?UploadedFile $file = null): EventUpdate
    {
        if (! $event->isVisible()) {
            throw new InvalidArgumentException(__('Publish the event before posting updates.'));
        }

        if (blank($values['title'] ?? null)) {
            throw new InvalidArgumentException(__('Add a title.'));
        }

        $update = new EventUpdate([
            'event_id' => $event->event_id,
            'kind' => in_array($values['kind'] ?? null, EventUpdate::KINDS, true) ? $values['kind'] : 'update',
            'title' => trim($values['title']),
            'body' => trim((string) ($values['body'] ?? '')) ?: null,
            'link_url' => trim((string) ($values['link_url'] ?? '')) ?: null,
            'registered_only' => (bool) ($values['registered_only'] ?? false),
            'posted_by' => $by->user_id,
        ]);

        if ($file) {
            $update->file_path = $file->store('event-materials/'.$event->event_id, EventUpdate::DISK);
            $update->file_name = Str::limit($file->getClientOriginalName(), 190, '');
        }

        $update->save();
        Review::event($event, 'update_posted', $update->title, $by);

        $event->registrations()->holdingPlace()->with('user')->get()
            ->each(fn (EventRegistration $registration) => Notify::send($registration->user, new EventUpdatePosted($event->title, $update->title, $event->slug)));

        return $update;
    }

    public static function removeUpdate(EventUpdate $update, User $by): void
    {
        if ($update->file_path) {
            Storage::disk(EventUpdate::DISK)->delete($update->file_path);
        }

        Review::event($update->event, 'update_removed', $update->title, $by);
        $update->delete();
    }

    /**
     * Updates a member can see: public ones, and those for registered
     * members when they hold a place.
     *
     * @return Collection<int, EventUpdate>
     */
    public static function updatesFor(Event $event, ?User $user): Collection
    {
        $registered = $user && ($user->user_type === 'staff' || self::registrationFor($event, $user)?->isActive());

        return $event->updates()->when(! $registered, fn ($query) => $query->where('registered_only', false))->get();
    }

    // ─── Participation summary (EVT-REQ08) ─────────────────────────

    /**
     * @return array{registered: int, attended: int, absent: int, waitlisted: int, cancelled: int, walk_ins: int, rate: ?int, capacity: ?int, by_category: array<string, array{registered: int, attended: int}>, by_region: array<string, array{registered: int, attended: int}>, by_method: array<string, int>, updates: int}
     */
    public static function summary(Event $event): array
    {
        $rows = DB::table('event_registrations')
            ->leftJoin('users', 'users.user_id', '=', 'event_registrations.user_id')
            ->leftJoin('institutions', 'institutions.institution_id', '=', 'users.institution_id')
            ->where('event_registrations.event_id', $event->event_id)
            ->get(['event_registrations.status', 'event_registrations.check_in_method', 'event_registrations.walk_in', 'users.user_type', 'institutions.region']);

        $attended = $rows->where('status', EventRegistration::ATTENDED);
        $registered = $rows->whereIn('status', [EventRegistration::REGISTERED, EventRegistration::ATTENDED]);
        $absent = $event->hasEnded() ? $rows->where('status', EventRegistration::REGISTERED)->count() : 0;
        $walkIns = $attended->where('walk_in', true)->count();

        $group = fn (string $key) => $registered->groupBy(fn ($row) => $row->{$key} ?? '—')
            ->map(fn ($group) => ['registered' => $group->count(), 'attended' => $group->where('status', EventRegistration::ATTENDED)->count()])
            ->sortByDesc('registered')->all();

        return [
            'registered' => $registered->count(),
            'attended' => $attended->count(),
            'absent' => $absent,
            'waitlisted' => $rows->where('status', EventRegistration::WAITLISTED)->count(),
            'cancelled' => $rows->where('status', EventRegistration::CANCELLED)->count(),
            'walk_ins' => $walkIns,
            'rate' => $registered->count() > 0 && ($event->hasEnded() || $attended->isNotEmpty()) ? (int) round($attended->count() / $registered->count() * 100) : null,
            'capacity' => $event->capacity,
            'by_category' => $group('user_type'),
            'by_region' => $group('region'),
            'by_method' => $attended->countBy(fn ($row) => $row->check_in_method ?? 'manual')->all(),
            'updates' => $event->updates()->count(),
        ];
    }

    // ─── Helpers ───────────────────────────────────────────────────

    public static function when(Event $event): string
    {
        return $event->starts_at?->translatedFormat('D j M Y, H:i') ?? __('date to be announced');
    }

    public static function place(Event $event): string
    {
        return match ($event->mode) {
            'online' => __('online'),
            default => (string) $event->location,
        };
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

        foreach (['capacity'] as $number) {
            if (array_key_exists($number, $values)) {
                $values[$number] = filled($values[$number]) ? max(1, (int) $values[$number]) : null;
            }
        }

        foreach (['registration_closes_at', 'ends_at', 'online_url', 'registration_question', 'region', 'organizer', 'objective'] as $nullable) {
            if (array_key_exists($nullable, $values) && blank($values[$nullable])) {
                $values[$nullable] = null;
            }
        }

        return $values;
    }

    /**
     * @param  list<string>  $statuses
     */
    private static function requireStatus(Event $event, array $statuses): void
    {
        if (! in_array($event->publication_status, $statuses, true)) {
            throw new InvalidArgumentException(__('This action is not available for the event now.'));
        }
    }

    private static function tellParticipants(Event $event, string $change, ?string $reason): void
    {
        $event->registrations()->whereIn('status', [EventRegistration::REGISTERED, EventRegistration::WAITLISTED])->with('user')->get()
            ->each(fn (EventRegistration $registration) => Notify::send($registration->user, new EventChanged($event->title, $change, $reason, $event->slug)));
    }
}
