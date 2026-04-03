<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    use HasUuids;

    protected $table = 'modules';
    protected $primaryKey = 'module_id';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ─── Relationships ─────────────────────────────────────────────

    /**
     * A module has many controls (actions).
     */
    public function controls(): HasMany
    {
        return $this->hasMany(ModuleControl::class, 'module_id', 'module_id');
    }

    /**
     * Only active controls for this module.
     */
    public function activeControls(): HasMany
    {
        return $this->hasMany(ModuleControl::class, 'module_id', 'module_id')
                    ->where('is_active', true);
    }
}