<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\MemberRegistration;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements HasLocalePreference
{
    use Auditable, HasApiTokens, HasFactory, HasUuids, Notifiable;

    /** @var list<string> */
    protected array $auditIgnore = ['last_login', 'total_points', 'points_earned_this_month'];

    protected $table = 'users';

    protected $primaryKey = 'user_id';

    public $timestamps = false; // uses custom created_at / last_login

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    public const STATUS_PENDING_VERIFICATION = 'pending_verification';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_DEACTIVATED = 'deactivated';

    public const AGE_MINOR = 'minor';

    public const AGE_ADULT = 'adult';

    /** Age at which a member is no longer treated as a minor (decision D-04). */
    public const ADULT_AGE = 18;

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'password_hash',
        'user_type',
        'institution_id',
        'membership_id',
        'card_token',
        'membership_issued_at',
        'registration_reference',
        'is_verified',
        'account_status',
        'status_reason',
        'verified_by',
        'verified_at',
        'date_of_birth',
        'gender',
        'age_band',
        'preferred_locale',
        'guardian_name',
        'guardian_phone',
        'guardian_relationship',
        'guardian_consent_at',
        'guardian_consent_confirmed_by',
        'guardian_consent_confirmed_at',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
        'card_token',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'created_at' => 'datetime',
        'last_login' => 'datetime',
        'verified_at' => 'datetime',
        'date_of_birth' => 'date',
        'guardian_consent_at' => 'datetime',
        'guardian_consent_confirmed_at' => 'datetime',
        'membership_issued_at' => 'datetime',
    ];

    /**
     * Laravel's auth system expects getAuthPassword() to return the password field.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    // ─── Age and privacy ──────────────────────────────────────────

    public static function ageBandFor(?\DateTimeInterface $dateOfBirth): ?string
    {
        if (! $dateOfBirth) {
            return null;
        }

        return Carbon::instance($dateOfBirth)->age < self::ADULT_AGE ? self::AGE_MINOR : self::AGE_ADULT;
    }

    public function isMinor(): bool
    {
        return $this->age_band === self::AGE_MINOR;
    }

    /**
     * Name safe to show to other members: first name and last initial
     * (PRO-REQ03, GAM-REQ10). Full names stay with officers.
     */
    public function publicName(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->full_name)) ?: [];
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? end($parts) : '';

        return trim($first.($last !== '' ? ' '.mb_substr($last, 0, 1).'.' : ''));
    }

    /**
     * Phone number for SMS notifications.
     */
    public function routeNotificationForSms(): ?string
    {
        return $this->phone;
    }

    public function preferredLocale(): string
    {
        return $this->preferred_locale ?: config('app.locale');
    }

    // ─── Account Status ───────────────────────────────────────────

    /**
     * Whether the account may sign in (REG-REQ03/04, REG-REQ06).
     */
    public function isActive(): bool
    {
        return $this->account_status === self::STATUS_ACTIVE;
    }

    /**
     * Find a user by email address or Tanzanian phone number.
     */
    public static function findByLoginIdentifier(string $identifier): ?self
    {
        $identifier = trim($identifier);

        if (str_contains($identifier, '@')) {
            return static::where('email', Str::lower($identifier))->first();
        }

        return static::where('phone', MemberRegistration::normalizePhone($identifier))->first();
    }

    /**
     * Institutions whose registrations this user may see and decide.
     * Null means every institution; patrons are limited to the institutions
     * of the Tax Clubs they currently look after.
     *
     * @return list<string>|null
     */
    public function verifiableInstitutionIds(): ?array
    {
        if ($this->hasControl('view_all_registrations')) {
            return null;
        }

        return ClubPatron::current()
            ->where('club_patrons.user_id', $this->user_id)
            ->join('tax_clubs', 'tax_clubs.tax_club_id', '=', 'club_patrons.tax_club_id')
            ->pluck('tax_clubs.institution_id')
            ->unique()
            ->values()
            ->all();
    }

    public function patronAssignments(): HasMany
    {
        return $this->hasMany(ClubPatron::class, 'user_id', 'user_id');
    }

    /**
     * Whether the user holds any administration permission.
     */
    public function canAccessAdmin(): bool
    {
        return $this->allControls() !== [];
    }

    // ─── RBAC Helpers ─────────────────────────────────────────────

    /**
     * Check if the user has a specific role by slug.
     * Usage: $user->hasRole('admin')
     */
    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->where('is_active', true)->exists();
    }

    /**
     * Check if the user has any of the given roles.
     * Usage: $user->hasAnyRole(['admin', 'educator'])
     */
    public function hasAnyRole(array $slugs): bool
    {
        return $this->roles()->whereIn('slug', $slugs)->where('is_active', true)->exists();
    }

    /**
     * Check if the user has a specific module control permission by slug.
     * Usage: $user->hasControl('add_user')
     */
    public function hasControl(string $controlSlug): bool
    {
        return $this->roles()
            ->where('roles.is_active', true)
            ->whereHas('controls', function ($query) use ($controlSlug) {
                $query->where('slug', $controlSlug)
                    ->where('module_controls.is_active', true);
            })->exists();
    }

    /**
     * Get all control slugs this user has (via their roles).
     * Useful for returning permissions in API responses.
     */
    public function allControls(): array
    {
        return $this->roles()
            ->where('roles.is_active', true)
            ->with(['controls' => fn ($q) => $q->where('module_controls.is_active', true)])
            ->get()
            ->pluck('controls')
            ->flatten()
            ->pluck('slug')
            ->unique()
            ->values()
            ->toArray();
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_id', 'institution_id');
    }

    public function roles(): BelongsToMany
    {
        // No withTimestamps() — user_roles uses assigned_at instead of
        // standard created_at/updated_at columns.
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->withPivot('assigned_by', 'assigned_at');
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class, 'user_id', 'user_id');
    }

    public function forumPosts(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'user_id', 'user_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(UserProgress::class, 'user_id', 'user_id');
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_registrations', 'user_id', 'event_id')
            ->withPivot('status', 'registered_at');
    }

    public function uploadedContent(): HasMany
    {
        return $this->hasMany(Content::class, 'uploaded_by', 'user_id');
    }

    public function postedOpportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'posted_by', 'user_id');
    }

    // app/Models/User.php - Add these methods

    public function addPoints(int $points, ?string $reason = null): void
    {
        $this->total_points += $points;
        $this->points_earned_this_month += $points;
        $this->save();

        // Log points activity (optional)
        // You can create a points_log table to track points history
    }

    public function deductPoints(int $points, ?string $reason = null): void
    {
        $this->total_points = max(0, $this->total_points - $points);
        $this->points_earned_this_month = max(0, $this->points_earned_this_month - $points);
        $this->save();
    }

    public function getBestAttemptForQuiz(string $quizId): ?QuizAttempt
    {
        return $this->quizAttempts()
            ->where('quiz_id', $quizId)
            ->where('is_best_attempt', true)
            ->first();
    }

    public function recalculatePoints(): void
    {
        $totalPoints = $this->quizAttempts()
            ->where('is_best_attempt', true)
            ->sum('total_points_earned');

        $this->total_points = $totalPoints;
        $this->save();
    }
}
