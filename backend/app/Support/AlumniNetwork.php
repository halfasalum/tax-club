<?php

namespace App\Support;

use App\Models\AlumniProfile;
use App\Models\AlumniStory;
use App\Models\Event;
use App\Models\User;
use App\Notifications\AlumniConnection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The alumni network (ALM-REQ01-03).
 *
 *  - Alumni keep a profile and choose whether to appear in the directory.
 *  - The directory is for alumni, adult members and staff; members under
 *    18 cannot browse it or be contacted through it (SAF-REQ05).
 *  - Contact details are shared only after a connection is accepted.
 *  - Alumni stories are published after review.
 */
final class AlumniNetwork
{
    public const EVENT_TYPES = ['career_talk', 'alumni_session'];

    public static function isAlumnus(User $user): bool
    {
        return $user->user_type === 'alumni' && $user->isActive();
    }

    public static function canUseDirectory(User $user): bool
    {
        return $user->isActive() && ($user->user_type === 'staff' || ! $user->isMinor());
    }

    public static function profileFor(User $user): AlumniProfile
    {
        return AlumniProfile::firstOrNew(['user_id' => $user->user_id]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function saveProfile(User $alumnus, array $values): AlumniProfile
    {
        if (! self::isAlumnus($alumnus)) {
            throw new InvalidArgumentException(__('Only alumni have an alumni profile.'));
        }

        $text = implode(' ', array_filter([$values['occupation'] ?? '', $values['organization'] ?? '', $values['bio'] ?? '', $values['skills'] ?? '']));

        if (ContentFilter::problem($text)) {
            throw new InvalidArgumentException(__('Remove phone numbers, emails and inappropriate words from your profile. People can contact you after you accept their connection.'));
        }

        $profile = self::profileFor($alumnus);
        $profile->fill([
            'graduation_year' => ($values['graduation_year'] ?? null) ?: null,
            'occupation' => trim((string) ($values['occupation'] ?? '')) ?: null,
            'organization' => trim((string) ($values['organization'] ?? '')) ?: null,
            'field' => trim((string) ($values['field'] ?? '')) ?: null,
            'bio' => trim(strip_tags((string) ($values['bio'] ?? ''))) ?: null,
            'skills' => trim((string) ($values['skills'] ?? '')) ?: null,
            'linkedin_url' => trim((string) ($values['linkedin_url'] ?? '')) ?: null,
            'show_in_directory' => (bool) ($values['show_in_directory'] ?? false) && ! $alumnus->isMinor(),
            'open_to_mentoring' => (bool) ($values['open_to_mentoring'] ?? false),
        ])->save();

        return $profile;
    }

    /**
     * Alumni who chose to be listed, searchable by name, field or work.
     */
    public static function directory(User $viewer, string $search = '', ?string $region = null): LengthAwarePaginator
    {
        if (! self::canUseDirectory($viewer)) {
            throw new InvalidArgumentException(__('The alumni directory is open to members aged 18 and over.'));
        }

        $search = trim($search);

        return AlumniProfile::query()
            ->where('show_in_directory', true)
            ->whereHas('user', fn ($query) => $query->where('user_type', 'alumni')->where('account_status', User::STATUS_ACTIVE)
                ->where(fn ($query) => $query->whereNull('age_band')->orWhere('age_band', User::AGE_ADULT))
                ->when($region, fn ($query) => $query->whereHas('institution', fn ($query) => $query->where('region', $region))))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('occupation', 'like', "%{$search}%")
                ->orWhere('organization', 'like', "%{$search}%")
                ->orWhere('field', 'like', "%{$search}%")
                ->orWhere('skills', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($query) => $query->where('full_name', 'like', "%{$search}%"))))
            ->where('user_id', '!=', $viewer->user_id)
            ->with('user:user_id,full_name,institution_id', 'user.institution:institution_id,name,region')
            ->orderByDesc('graduation_year')
            ->paginate(18)
            ->withQueryString();
    }

    public static function connectionBetween(User $a, User $b): ?object
    {
        return DB::table('alumni_connections')
            ->where(fn ($query) => $query->where('requester_id', $a->user_id)->where('recipient_id', $b->user_id))
            ->orWhere(fn ($query) => $query->where('requester_id', $b->user_id)->where('recipient_id', $a->user_id))
            ->first();
    }

    public static function connect(User $requester, User $alumnus, ?string $message = null): void
    {
        $profile = AlumniProfile::find($alumnus->user_id);

        match (true) {
            $requester->is($alumnus) => throw new InvalidArgumentException(__('You cannot connect with yourself.')),
            ! self::canUseDirectory($requester) => throw new InvalidArgumentException(__('The alumni directory is open to members aged 18 and over.')),
            ! $profile?->show_in_directory || ! self::isAlumnus($alumnus) || $alumnus->isMinor() => throw new InvalidArgumentException(__('This person is not in the alumni directory.')),
            SafetyDesk::isRestricted($requester, 'community') => throw new InvalidArgumentException(__('Your account is restricted, so you cannot send connection requests right now.')),
            self::connectionBetween($requester, $alumnus) !== null => throw new InvalidArgumentException(__('You already asked to connect.')),
            default => null,
        };

        if ($message && ContentFilter::problem($message)) {
            throw new InvalidArgumentException(__('Do not include contact details or inappropriate words in your message.'));
        }

        DB::table('alumni_connections')->insert([
            'requester_id' => $requester->user_id,
            'recipient_id' => $alumnus->user_id,
            'message' => trim((string) $message) ?: null,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notify::send($alumnus, new AlumniConnection($requester->publicName(), accepted: false));
    }

    public static function respond(User $recipient, int $connectionId, bool $accept): void
    {
        $connection = DB::table('alumni_connections')->where('id', $connectionId)->where('recipient_id', $recipient->user_id)->where('status', 'pending')->first();

        if (! $connection) {
            throw new InvalidArgumentException(__('This request is no longer waiting.'));
        }

        DB::table('alumni_connections')->where('id', $connectionId)->update(['status' => $accept ? 'accepted' : 'declined', 'responded_at' => now(), 'updated_at' => now()]);

        if ($accept && ($requester = User::find($connection->requester_id))) {
            Notify::send($requester, new AlumniConnection($recipient->publicName(), accepted: true));
        }
    }

    /**
     * @return Collection<int, object>
     */
    public static function pendingRequestsFor(User $user): Collection
    {
        return DB::table('alumni_connections')
            ->join('users', 'users.user_id', '=', 'alumni_connections.requester_id')
            ->where('alumni_connections.recipient_id', $user->user_id)
            ->where('alumni_connections.status', 'pending')
            ->get(['alumni_connections.id', 'alumni_connections.message', 'alumni_connections.created_at', 'users.full_name', 'users.user_type']);
    }

    /**
     * Contact details once a connection was accepted.
     *
     * @return array{phone: ?string, email: ?string, linkedin: ?string}|null
     */
    public static function contactFor(User $viewer, User $alumnus): ?array
    {
        $connection = self::connectionBetween($viewer, $alumnus);

        if (! $connection || $connection->status !== 'accepted') {
            return null;
        }

        $alumnus = User::find($alumnus->user_id);

        return ['phone' => $alumnus?->phone, 'email' => $alumnus?->email, 'linkedin' => AlumniProfile::find($alumnus?->user_id)?->linkedin_url];
    }

    // ─── Stories (ALM-REQ03) ───────────────────────────────────────

    public static function submitStory(User $alumnus, string $title, string $body, string $kind = 'story'): AlumniStory
    {
        if (! self::isAlumnus($alumnus)) {
            throw new InvalidArgumentException(__('Only alumni can share stories.'));
        }

        $story = AlumniStory::create([
            'user_id' => $alumnus->user_id,
            'title' => trim($title),
            'body' => trim(strip_tags($body)),
            'kind' => in_array($kind, AlumniStory::KINDS, true) ? $kind : 'story',
        ]);
        $story->forceFill(['editorial_status' => Review::DRAFT])->save();
        Review::submit($story, $alumnus, ContentFilter::problem($title.' '.$body) === 'contact_sharing' ? [__('Remove phone numbers and emails from your story.')] : []);

        return $story;
    }

    public static function approveStory(AlumniStory $story, User $reviewer): void
    {
        Review::decide($story, $reviewer, Review::APPROVED, null);
        $story->forceFill(['published_at' => now()])->save();
    }

    public static function returnStory(AlumniStory $story, User $reviewer, string $note): void
    {
        Review::decide($story, $reviewer, Review::RETURNED, $note);
    }

    /**
     * Career talks and alumni sessions coming up.
     *
     * @return Collection<int, Event>
     */
    public static function upcomingSessions(User $viewer): Collection
    {
        return Event::upcoming()->whereIn('type', self::EVENT_TYPES)->orderBy('starts_at')->limit(10)->get()
            ->filter(fn (Event $event) => $event->isVisibleTo($viewer))->values();
    }
}
