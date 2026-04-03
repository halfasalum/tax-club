<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    use HasUuids;

    protected $table = 'institutions';
    protected $primaryKey = 'institution_id';
    public $timestamps = false; // only has created_at, no updated_at

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'type',
        'region',
        'district',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'type'       => 'string',
    ];

    // ─── Relationships ────────────────────────────────────────────

    /**
     * An institution has many users (members).
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'institution_id', 'institution_id');
    }
}