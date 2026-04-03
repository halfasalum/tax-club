<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ModuleControl extends Model
{
    use HasUuids;

    protected $table = 'module_controls';
    protected $primaryKey = 'control_id';

    protected $fillable = [
        'name',
        'slug',
        'module_id',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ─── Relationships ─────────────────────────────────────────────

    /**
     * The module this control belongs to.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id', 'module_id');
    }

    /**
     * Roles that have been granted this control.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_controls', 'control_id', 'role_id')
                    ->withPivot('granted_by')
                    ->withTimestamps();
    }
}