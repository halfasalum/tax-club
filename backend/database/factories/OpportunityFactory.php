<?php

namespace Database\Factories;

use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => sprintf('OP-%04d', fake()->unique()->numberBetween(1, 9999)),
            'title' => fake()->unique()->sentence(4),
            'organization' => 'Tanzania Revenue Authority',
            'type' => 'internship',
            'description' => fake()->paragraph(),
            'eligibility' => 'University students in their final year.',
            'deadline' => now()->addDays(30),
            'apply_method' => 'link',
            'apply_url' => 'https://www.tra.go.tz/vacancies',
            'is_active' => true,
        ];
    }

    public function published(): static
    {
        return $this->afterCreating(fn (Opportunity $opportunity) => $opportunity->forceFill([
            'editorial_status' => 'approved',
            'publication_status' => Opportunity::PUBLISHED,
            'published_at' => now(),
        ])->save());
    }
}
