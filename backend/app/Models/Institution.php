<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Institution extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /** @var list<string> */
    public const CATEGORIES = ['secondary', 'college', 'university'];

    protected $table = 'institutions';

    protected $primaryKey = 'institution_id';

    protected $fillable = [
        'code',
        'name',
        'type',
        'region',
        'district',
        'status',
        'status_reason',
        'contact_name',
        'contact_phone',
        'contact_email',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'type' => 'string',
    ];

    // ─── Scopes ────────────────────────────────────────────────────

    /**
     * Institutions that may receive new member registrations (INS-REQ03).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    // ─── Relationships ────────────────────────────────────────────

    /**
     * An institution has many users (members).
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'institution_id', 'institution_id');
    }

    public function taxClubs(): HasMany
    {
        return $this->hasMany(TaxClub::class, 'institution_id', 'institution_id');
    }

    /**
     * The institution's current (active) Tax Club, if any.
     */
    public function activeTaxClub(): HasOne
    {
        return $this->hasOne(TaxClub::class, 'institution_id', 'institution_id')
            ->where('status', TaxClub::STATUS_ACTIVE)
            ->latestOfMany('created_at');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(InstitutionStatusChange::class, 'institution_id', 'institution_id')->latest('changed_at');
    }
}
