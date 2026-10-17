<?php

namespace Database\Factories;

use App\Models\Content;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Content>
 */
class ContentFactory extends Factory
{
    /**
     * A published lesson that is visible on the public website.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(4),
            'summary' => fake()->sentence(12),
            'content_type' => 'article',
            'body_text' => '<p>'.fake()->paragraph().'</p>',
            'published_at' => now()->subDay(),
            'is_active' => true,
            'is_public' => true,
        ];
    }

    /**
     * Published for signed-in members only.
     */
    public function membersOnly(): static
    {
        return $this->state(fn () => ['is_public' => false]);
    }
}
