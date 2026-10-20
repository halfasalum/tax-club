<?php

namespace Tests\Feature;

use App\Models\CommunitySpace;
use App\Models\User;
use App\Support\Certificates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseFourFiveApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_community_report_ask_and_certificate_endpoints(): void
    {
        $space = CommunitySpace::create(['name' => 'Tax Club Tanzania', 'type' => 'national', 'status' => 'active']);
        $member = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($member, 'sanctum')->getJson('/api/v1/community/spaces')->assertOk()->assertJsonPath('data.0.slug', $space->slug);
        $postId = $this->actingAs($member, 'sanctum')->postJson("/api/v1/community/spaces/{$space->slug}/posts", ['body' => 'Who is going to Tax Day?'])->assertCreated()->json('data.post_id');
        $this->actingAs($other, 'sanctum')->postJson("/api/v1/community/posts/{$postId}/comments", ['body' => 'Our club is!'])->assertCreated();
        $this->actingAs($other, 'sanctum')->getJson("/api/v1/community/posts/{$postId}")->assertOk()->assertJsonCount(1, 'data.comments');
        $this->actingAs($other, 'sanctum')->postJson('/api/v1/reports', ['type' => 'post', 'id' => $postId, 'category' => 'spam'])->assertCreated()->assertJsonStructure(['data' => ['reference']]);
        $this->actingAs($member, 'sanctum')->getJson('/api/v1/forum')->assertNotFound();

        $this->actingAs($member, 'sanctum')->postJson('/api/v1/questions', ['subject' => 'How is VAT calculated?', 'body' => 'What percentage is VAT on goods in Tanzania?'])->assertCreated();
        $this->actingAs($member, 'sanctum')->getJson('/api/v1/questions')->assertJsonPath('data.0.status', 'submitted');

        $certificate = Certificates::issue($member, 'other', 'Cheti', 'Certificate', 'Volunteer');
        $this->actingAs($member, 'sanctum')->getJson('/api/v1/achievements')->assertJsonPath('data.certificates.0.number', $certificate->number);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/certificates/verify', ['number' => $certificate->number])->assertJsonPath('data.result', 'valid');
    }

    public function test_minors_cannot_open_the_alumni_directory_by_api(): void
    {
        $this->actingAs(User::factory()->minor()->create(), 'sanctum')->getJson('/api/v1/alumni/directory')->assertForbidden();
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/alumni/directory')->assertOk();
    }
}
