<?php

namespace Tests\Feature;

use App\Models\CommunitySpace;
use App\Models\Content;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAppApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_app_gets_answers_in_the_language_it_asks_for(): void
    {
        $this->getJson('/api/v1/app/config', ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.labels.user_types.university', 'University')
            ->assertJsonPath('data.min_version', '1.0.0');

        $this->getJson('/api/v1/app/config', ['Accept-Language' => 'sw-TZ'])
            ->assertJsonPath('data.labels.user_types.university', __('University', [], 'sw'));

        $this->postJson('/api/v1/auth/login', ['identifier' => '0700000000', 'password' => 'wrong-password'], ['Accept-Language' => 'sw'])
            ->assertUnauthorized()
            ->assertJsonPath('message', __('Invalid credentials.', [], 'sw'));
    }

    public function test_members_manage_notification_settings_and_see_badges(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member, 'sanctum')->getJson('/api/v1/notification-preferences')->assertOk()->assertJsonFragment(['category' => 'events', 'sms' => true]);
        $this->actingAs($member, 'sanctum')->putJson('/api/v1/notification-preferences', ['category' => 'events', 'sms' => false, 'inbox' => true])
            ->assertOk()
            ->assertJsonFragment(['category' => 'events', 'sms' => false]);
        $this->actingAs($member, 'sanctum')->putJson('/api/v1/notification-preferences', ['category' => 'unknown', 'sms' => false, 'inbox' => true])->assertUnprocessable();

        $this->actingAs($member, 'sanctum')->getJson('/api/v1/badges')->assertOk();
        $this->actingAs($member, 'sanctum')->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.total_points', 0)->assertJsonPath('data.unread_notifications', 0);
    }

    public function test_members_see_which_posts_are_theirs(): void
    {
        $space = CommunitySpace::create(['name' => 'Tax Club Tanzania', 'type' => 'national', 'status' => 'active']);
        $member = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($member, 'sanctum')->getJson('/api/v1/community/spaces')->assertJsonPath('data.0.posting_problem', null);
        $this->actingAs($member, 'sanctum')->postJson("/api/v1/community/spaces/{$space->slug}/posts", ['body' => 'Who is going to Tax Day?'])->assertJsonPath('data.is_mine', true);
        $this->actingAs($other, 'sanctum')->getJson("/api/v1/community/spaces/{$space->slug}/posts")->assertJsonPath('data.0.is_mine', false);
    }

    public function test_visitors_see_the_public_website_pages_without_signing_in(): void
    {
        $lesson = Content::factory()->create(['title' => 'VAT ni nini?']);
        $hidden = Content::factory()->membersOnly()->create();
        $event = Event::factory()->create(['is_public' => true, 'starts_at' => now()->addDays(3)]);

        $this->getJson('/api/v1/public/home')
            ->assertOk()
            ->assertJsonPath('data.lessons.0.slug', $lesson->slug)
            ->assertJsonPath('data.events.0.slug', $event->slug);

        $this->getJson('/api/v1/public/lessons?search=VAT', ['Accept-Language' => 'sw'])->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/public/lessons/{$lesson->slug}", ['Accept-Language' => 'sw'])
            ->assertOk()
            ->assertJsonPath('data.content_type_label', 'Makala')
            ->assertJsonStructure(['data' => ['body_html']]);
        $this->getJson("/api/v1/public/lessons/{$hidden->slug}")->assertNotFound();
        $this->getJson("/api/v1/public/events/{$event->slug}")->assertOk()->assertJsonPath('data.title', $event->title);
        $this->getJson('/api/v1/public/answers')->assertOk();
    }
}
