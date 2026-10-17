<?php

namespace Tests\Feature;

use App\Livewire\Member\Card;
use App\Livewire\Membership\Verify;
use App\Models\Institution;
use App\Models\MembershipVerification;
use App\Models\User;
use App\Support\MemberRegistration;
use App\Support\MembershipCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class MembershipCardTest extends TestCase
{
    use CreatesOfficers, RefreshDatabase;

    public function test_approval_issues_a_sequenced_number_with_region_and_check_digit(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 17));
        $institution = Institution::factory()->create(['region' => 'Mwanza']);
        $first = User::factory()->pending()->create(['institution_id' => $institution->institution_id]);
        $second = User::factory()->pending()->create(['institution_id' => $institution->institution_id]);
        $officer = User::factory()->create();

        MemberRegistration::approve($first, $officer);
        MemberRegistration::approve($second, $officer);

        $this->assertSame('TC-26-MWA-000001-'.MembershipCard::checkDigit('26000001'), $first->fresh()->membership_id);
        $this->assertSame('TC-26-MWA-000002-'.MembershipCard::checkDigit('26000002'), $second->fresh()->membership_id);
        $this->assertSame(32, strlen($first->fresh()->card_token));
        $this->assertNotNull($first->fresh()->membership_issued_at);
    }

    public function test_check_digit_catches_mistyped_numbers(): void
    {
        $this->assertSame(3, MembershipCard::checkDigit('7992739871')); // standard Luhn example

        $number = MembershipCard::nextNumber(Institution::factory()->create(['region' => 'Dar es Salaam']));
        $this->assertFalse(MembershipCard::isMistyped($number));
        $this->assertSame($number, MembershipCard::normalizeNumber(strtolower(str_replace('-', ' ', $number))));

        $swapped = substr_replace($number, (string) ((int) $number[-1] + 1) % 10, -1);
        $this->assertTrue(MembershipCard::isMistyped($swapped));
    }

    public function test_member_sees_card_with_qr_and_can_replace_it(): void
    {
        $member = User::factory()->create();
        $oldToken = $member->card_token;

        Livewire::actingAs($member)
            ->test(Card::class)
            ->assertSee($member->membership_id)
            ->assertSee('Active member')
            ->assertSeeHtml('<svg')
            ->call('reissue')
            ->assertSee('Old copies of your card no longer verify');

        $this->assertNotSame($oldToken, $member->fresh()->card_token);
        $this->get('/verify/'.$oldToken.'?lang=en')->assertOk()->assertSee('Card not recognised');
    }

    public function test_suspended_member_card_shows_not_valid_without_qr(): void
    {
        $member = User::factory()->create(['account_status' => User::STATUS_SUSPENDED]);

        Livewire::actingAs($member)
            ->test(Card::class)
            ->assertSee('Suspended')
            ->assertDontSeeHtml('<svg');
    }

    public function test_scanning_a_qr_shows_status_and_short_name_only_to_the_public(): void
    {
        $member = User::factory()->create(['full_name' => 'Asha Juma Mohamed']);

        $this->get('/verify/'.$member->card_token.'?lang=en')
            ->assertOk()
            ->assertSee('Valid membership')
            ->assertSee('Asha M.')
            ->assertDontSee('Asha Juma Mohamed')
            ->assertDontSee($member->membership_id)
            ->assertSee('noindex', false);

        $this->assertDatabaseHas('membership_verifications', [
            'member_id' => $member->user_id,
            'method' => MembershipVerification::METHOD_QR,
            'result' => MembershipCard::RESULT_VALID,
            'full_details' => false,
        ]);
    }

    public function test_officers_with_permission_see_full_details(): void
    {
        $officer = $this->officerWith(['verify_membership']);
        $member = User::factory()->minor()->create(['full_name' => 'Asha Juma Mohamed']);

        $this->actingAs($officer)
            ->get('/verify/'.$member->card_token.'?lang=en')
            ->assertSee('Asha Juma Mohamed')
            ->assertSee($member->membership_id)
            ->assertSee('Under 18');
    }

    public function test_typed_numbers_are_checked(): void
    {
        $member = User::factory()->create(['account_status' => User::STATUS_DEACTIVATED]);
        $member->update(['membership_id' => MembershipCard::nextNumber($member->institution)]);

        Livewire::test(Verify::class)
            ->set('number', strtolower($member->membership_id))
            ->call('check')
            ->assertSee('Membership not valid')
            ->assertSee('Deactivated');

        $mistyped = substr_replace($member->membership_id, (string) (((int) $member->membership_id[-1] + 1) % 10), -1);
        Livewire::test(Verify::class)
            ->set('number', $mistyped)
            ->call('check')
            ->assertHasErrors('number');

        Livewire::test(Verify::class)
            ->set('number', 'TC-26-ABCDEFGH')
            ->call('check')
            ->assertSee('Card not recognised');
    }

    public function test_checks_are_rate_limited(): void
    {
        $component = Livewire::test(Verify::class);

        for ($i = 0; $i < Verify::CHECKS_PER_MINUTE; $i++) {
            $component->set('number', 'TC-26-NOTREAL1')->call('check')->call('startOver');
        }

        $component->set('number', 'TC-26-NOTREAL1')->call('check')->assertHasErrors('number');
    }

    public function test_api_card_and_verification(): void
    {
        $member = User::factory()->create(['full_name' => 'Juma Ally']);

        $card = $this->actingAs($member, 'sanctum')
            ->getJson('/api/v1/membership/card')
            ->assertOk()
            ->assertJsonPath('data.is_valid', true)
            ->assertJsonPath('data.membership_id', $member->membership_id)
            ->json('data');
        $this->assertStringContainsString('<svg', $card['qr_svg']);
        $this->assertStringEndsWith('/verify/'.$member->card_token, $card['verify_url']);

        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/membership/verify', ['membership_number' => $member->membership_id])
            ->assertOk()
            ->assertJsonPath('data.result', 'valid')
            ->assertJsonPath('data.display_name', 'Juma A.')
            ->assertJsonPath('data.details', null);

        $officer = $this->officerWith(['verify_membership']);
        $this->actingAs($officer, 'sanctum')
            ->postJson('/api/v1/membership/verify', ['token' => $member->card_token])
            ->assertOk()
            ->assertJsonPath('data.details.full_name', 'Juma Ally');
    }
}
