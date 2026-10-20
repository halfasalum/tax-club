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
            'type' => 'workshop',
            'mode' => 'in_person',
            'organizer' => 'TRA',
            'registration_required' => true,
            'is_public' => true,
            'is_active' => true,
            'editorial_status' => 'approved',
            'publication_status' => 'published',
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['editorial_status' => 'draft', 'publication_status' => 'not_published', 'is_active' => false, 'published_at' => null]);
    }

    /**
     * Started an hour ago, so check-in is open.
     */
    public function happeningNow(): static
    {
        return $this->state(['starts_at' => now()->subHour(), 'ends_at' => now()->addHours(3)]);
    }

    public function ended(): static
    {
        return $this->state(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDays(2)->addHours(4)]);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Event $event) {
            $event->code ??= sprintf('EV-%04d', fake()->unique()->numberBetween(1, 9999));
        });
    }
}
