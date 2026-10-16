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
        ];
    }
}
