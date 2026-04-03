<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasUuids;

    protected $table = 'roles';
    protected $primaryKey = 'role_id';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_default',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    // ─── Relationships ─────────────────────────────────────────────

    /**
     * Admin who created this role.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /**
     * Users assigned to this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id')
                    ->withPivot('assigned_by', 'assigned_at');
    }

    /**
     * Module controls granted to this role.
     */
    public function controls(): BelongsToMany
    {
        return $this->belongsToMany(
            ModuleControl::class,
            'role_controls',
            'role_id',
            'control_id'
        )->withPivot('granted_by')->withTimestamps();
    }

    // ─── Helpers ───────────────────────────────────────────────────

    /**
     * Get the default role to assign on new user registration.
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->where('is_active', true)->first();
    }
}