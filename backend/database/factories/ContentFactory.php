<?php

namespace Database\Factories;

use App\Models\Content;
use App\Models\ContentVersion;
use App\Support\ContentWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Content>
 */
class ContentFactory extends Factory
{
    /**
     * A published, public article (version 1).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'LC-'.fake()->unique()->numerify('9###'),
            'title' => fake()->unique()->sentence(4),
            'summary' => fake()->sentence(12),
            'content_type' => 'article',
            'body_text' => '<p>'.fake()->paragraph().'</p>',
            'level' => 'beginner',
            'language' => 'sw',
            'published_at' => now()->subDay(),
            'is_active' => true,
            'is_public' => true,
            'publication_status' => Content::PUBLISHED,
            'current_version' => 1,
            'review_due_at' => now()->addYear(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Content $content) {
            ContentVersion::create([
                'content_id' => $content->content_id,
                'version' => 1,
                'status' => $content->current_version ? ContentVersion::PUBLISHED : ContentVersion::DRAFT,
                'fields' => ContentWorkflow::liveFields($content),
                'published_at' => $content->current_version ? $content->published_at : null,
            ]);
        });
    }

    /**
     * Published for signed-in members only.
     */
    public function membersOnly(): static
    {
        return $this->state(fn () => ['is_public' => false]);
    }

    /**
     * Published from a future date.
     */
    public function scheduled(): static
    {
        return $this->state(fn () => ['publication_status' => Content::SCHEDULED, 'publish_at' => now()->addWeek()]);
    }

    /**
     * A first draft that has never been published.
     */
    public function draft(): static
    {
        return $this->state(fn () => [
            'publication_status' => Content::NOT_PUBLISHED,
            'current_version' => null,
            'published_at' => null,
            'is_active' => false,
        ]);
    }
}
