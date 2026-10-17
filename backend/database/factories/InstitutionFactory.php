<?php

namespace Database\Factories;

use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Institution>
 */
class InstitutionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->lastName().' Secondary School',
            'type' => 'secondary',
            'region' => fake()->randomElement(['Dar es Salaam', 'Arusha', 'Mwanza', 'Dodoma', 'Mbeya']),
            'district' => fake()->city(),
            'code' => 'TST-S-'.fake()->unique()->numerify('####'),
            'status' => Institution::STATUS_ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => Institution::STATUS_INACTIVE, 'status_reason' => 'Closed']);
    }
}
