<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ModuleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait CreatesOfficers
{
    /**
     * An active user holding a role with exactly the given permissions.
     *
     * @param  list<string>  $controlSlugs
     */
    protected function officerWith(array $controlSlugs, array $attributes = []): User
    {
        if (DB::table('module_controls')->doesntExist()) {
            $this->seed(ModuleSeeder::class);
        }

        $role = Role::create([
            'role_id' => (string) Str::uuid(),
            'name' => 'Officer '.Str::random(6),
            'slug' => 'officer_'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        $role->controls()->attach(DB::table('module_controls')->whereIn('slug', $controlSlugs)->pluck('control_id')->all());

        $officer = User::factory()->create($attributes);
        $officer->roles()->attach($role->role_id, ['assigned_at' => now()]);

        return $officer;
    }
}
