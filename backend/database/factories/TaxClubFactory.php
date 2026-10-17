<?php

namespace Database\Factories;

use App\Models\Institution;
use App\Models\TaxClub;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxClub>
 */
class TaxClubFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'name' => 'Tax Club',
            'established_on' => now()->subYear()->toDateString(),
            'status' => TaxClub::STATUS_ACTIVE,
        ];
    }
}
