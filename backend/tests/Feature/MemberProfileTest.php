<?php

namespace Tests\Feature;

use App\Livewire\Member\Profile;
use App\Models\CoordinatorAssignment;
use App\Models\SmsMessage;
use App\Models\User;
use App\Support\MemberProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class MemberProfileTest extends TestCase
{
    use CreatesOfficers, RefreshDatabase;

    public function test_member_updates_permitted_details_only(): void
    {
        $member = User::factory()->create(['full_name' => 'Asha Juma', 'email' => null]);

        Livewire::actingAs($member)->test(Profile::class)
            ->set('email', 'ASHA@Example.com')
            ->set('gender', 'female')
            ->set('preferred_locale', 'sw')
            ->call('saveDetails')
            ->assertHasNoErrors()
            ->assertSee('Ask your Tax Club patron to correct it');

        $member->refresh();
        $this->assertSame('asha@example.com', $member->email);
        $this->assertSame('sw', $member->preferred_locale);
        $this->assertSame('Asha Juma', $member->full_name);
    }

    public function test_photo_is_resized_private_and_shown_to_officers_only(): void
    {
        Storage::fake('local');
        $member = User::factory()->create();

        Livewire::actingAs($member)->test(Profile::class)
            ->set('photo', UploadedFile::fake()->image('me.png', 900, 600))
            ->assertHasNoErrors();

        $member->refresh();
        Storage::disk('local')->assertExists($member->photo_path);
        [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($member->photo_path));
        $this->assertSame([MemberProfile::PHOTO_SIZE, MemberProfile::PHOTO_SIZE], [$width, $height]);

        $url = route('members.photo', $member);
        $this->actingAs($member)->get($url)->assertOk();
        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
        $this->actingAs($this->officerWith(['view_members', 'view_all_registrations']))->get($url)->assertOk();

        $otherRegion = $this->officerWith(['view_members']);
        CoordinatorAssignment::create(['user_id' => $otherRegion->user_id, 'region' => 'Not '.$member->institution->region, 'starts_on' => now()]);
        $this->actingAs($otherRegion)->get($url)->assertNotFound();
    }

    public function test_phone_change_needs_the_code_sent_to_the_new_number(): void
    {
        $member = User::factory()->create(['phone' => '+255712000001']);
        User::factory()->create(['phone' => '+255712000002']);

        $page = Livewire::actingAs($member)->test(Profile::class)
            ->set('newPhone', '0712 000 002')
            ->call('sendPhoneCode')
            ->assertHasErrors('newPhone')
            ->set('newPhone', '0712 000 003')
            ->call('sendPhoneCode')
            ->assertSet('phoneCodeSent', true);

        $sms = SmsMessage::where('to', '+255712000003')->sole();
        preg_match('/\b(\d{6})\b/', $sms->message, $code);

        $page->set('phoneCode', $code[1] === '000000' ? '111111' : '000000')->call('confirmPhone')->assertHasErrors('phoneCode');
        $this->assertSame('+255712000001', $member->fresh()->phone);

        $page->set('phoneCode', $code[1])->call('confirmPhone')->assertHasNoErrors();
        $this->assertSame('+255712000003', $member->fresh()->phone);
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $member = User::factory()->create();
        $member->createToken('mobile_app');

        Livewire::actingAs($member)->test(Profile::class)
            ->set('current_password', 'wrong-one')
            ->set('password', 'brand-new-pass')
            ->set('password_confirmation', 'brand-new-pass')
            ->call('changePassword')
            ->assertHasErrors('current_password')
            ->set('current_password', 'password')
            ->call('changePassword')
            ->assertHasNoErrors();

        $this->assertSame(0, $member->tokens()->count());
        $this->assertDatabaseHas('sms_messages', ['user_id' => $member->user_id, 'purpose' => 'password_changed']);
    }

    public function test_member_requests_alumni_status(): void
    {
        $member = User::factory()->create(['user_type' => 'university']);

        Livewire::actingAs($member)->test(Profile::class)->call('requestAlumni')->assertSee('Request sent');
        $this->assertNotNull($member->fresh()->alumni_requested_at);

        $this->actingAs($member, 'sanctum')->deleteJson('/api/v1/profile/alumni-request')->assertOk();
        $this->assertNull($member->fresh()->alumni_requested_at);
    }

    public function test_other_members_see_a_limited_profile(): void
    {
        $minor = User::factory()->minor()->create(['full_name' => 'Neema Ally Juma', 'phone' => '+255712000009']);
        $adult = User::factory()->create();

        $this->actingAs($adult, 'sanctum')
            ->getJson('/api/v1/members/'.$minor->user_id)
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Neema J.')
            ->assertJsonPath('data.institution', null)
            ->assertJsonMissingPath('data.phone')
            ->assertJsonMissingPath('data.full_name')
            ->assertJsonMissingPath('data.guardian');

        $this->actingAs($minor, 'sanctum')
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Neema Ally Juma')
            ->assertJsonPath('data.phone', '+255712000009')
            ->assertJsonPath('data.can_request_alumni', true);
    }

    public function test_api_profile_update_and_photo(): void
    {
        Storage::fake('local');
        $member = User::factory()->create();

        $this->actingAs($member, 'sanctum')
            ->putJson('/api/v1/profile', ['email' => 'new@example.com', 'gender' => 'male', 'preferred_locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.email', 'new@example.com');

        $this->actingAs($member, 'sanctum')
            ->post('/api/v1/profile/photo', ['photo' => UploadedFile::fake()->image('me.jpg', 300, 300)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.has_photo', true);
    }
}
