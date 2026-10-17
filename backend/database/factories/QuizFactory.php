<?php

namespace Database\Factories;

use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    /**
     * An approved, published, scored quiz without questions.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'QZ-'.fake()->unique()->numerify('9###'),
            'title' => fake()->unique()->sentence(3),
            'mode' => Quiz::SCORED,
            'pass_mark' => 50,
            'retained_result' => Quiz::RETAIN_BEST,
            'show_answers' => Quiz::ANSWERS_AFTER_SUBMIT,
            'editorial_status' => Quiz::APPROVED,
            'publication_status' => Quiz::PUBLISHED,
            'published_at' => now()->subDay(),
            'is_active' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['editorial_status' => Quiz::DRAFT, 'publication_status' => Quiz::NOT_PUBLISHED, 'published_at' => null, 'is_active' => false]);
    }
}
