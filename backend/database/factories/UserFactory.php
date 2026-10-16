<?php

namespace Database\Factories;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * An active, verified student member.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+2557'.fake()->unique()->numerify('########'),
            'password_hash' => static::$password ??= Hash::make('password'),
            'user_type' => 'secondary',
            'institution_id' => Institution::factory(),
            'membership_id' => 'TC-'.now()->format('y').'-'.Str::upper(Str::random(8)),
            'registration_reference' => 'REG-'.now()->format('Y').'-'.Str::upper(Str::random(6)),
            'is_verified' => true,
            'account_status' => User::STATUS_ACTIVE,
        ];
    }

    /**
     * A registration awaiting verification.
     */
    public function pending(): static
    {
        return $this->state(fn () => [
            'membership_id' => null,
            'is_verified' => false,
            'account_status' => User::STATUS_PENDING_VERIFICATION,
        ]);
    }
}
