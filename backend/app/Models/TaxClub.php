<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Tax Club established under a registered institution (INS-REQ04).
 */
class TaxClub extends Model
{
    use Auditable, HasFactory, HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $primaryKey = 'tax_club_id';

    protected $fillable = [
        'institution_id',
        'name',
        'established_on',
        'status',
        'status_reason',
        'created_by',
    ];

    protected $casts = [
        'established_on' => 'date',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_id', 'institution_id');
    }

    public function patronAssignments(): HasMany
    {
        return $this->hasMany(ClubPatron::class, 'tax_club_id', 'tax_club_id')->latest('starts_on');
    }

    public function currentPatrons(): HasMany
    {
        return $this->hasMany(ClubPatron::class, 'tax_club_id', 'tax_club_id')->current();
    }
}
