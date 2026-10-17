<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDays(fake()->numberBetween(3, 60))->setTime(9, 0);

        return [
            'title' => fake()->unique()->sentence(3),
            'description' => fake()->paragraph(),
            'location' => fake()->city(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(6),
            'is_active' => true,
        ];
    }
}
