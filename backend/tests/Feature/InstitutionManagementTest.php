<?php

namespace Tests\Feature;

use App\Livewire\Admin\Institutions\Form;
use App\Livewire\Admin\Institutions\Import;
use App\Livewire\Admin\Institutions\Index;
use App\Livewire\Admin\Institutions\Show;
use App\Livewire\Auth\Register;
use App\Models\ClubPatron;
use App\Models\Institution;
use App\Models\TaxClub;
use App\Models\User;
use Database\Seeders\PatronRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class InstitutionManagementTest extends TestCase
{
    use CreatesOfficers, RefreshDatabase;

    private function admin(): User
    {
        return $this->officerWith([
            'view_institutions', 'add_institution', 'edit_institution', 'change_institution_status',
            'manage_tax_clubs', 'assign_patron', 'import_institutions', 'view_all_registrations',
        ]);
    }

    public function test_officer_registers_an_institution_with_a_generated_code_and_history(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(Form::class)
            ->set('name', 'Azania Secondary School')
            ->set('type', 'secondary')
            ->set('region', 'Dar es Salaam')
            ->set('district', 'Ilala')
            ->set('contact_phone', '0712 345678')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $institution = Institution::where('name', 'Azania Secondary School')->firstOrFail();

        $this->assertSame('DSM-S-0001', $institution->code);
        $this->assertSame(Institution::STATUS_ACTIVE, $institution->status);
        $this->assertSame('+255712345678', $institution->contact_phone);
        $this->assertSame($admin->user_id, $institution->created_by);
        $this->assertDatabaseHas('institution_status_history', ['institution_id' => $institution->institution_id, 'to_status' => 'active', 'changed_by' => $admin->user_id]);
    }

    public function test_duplicate_institution_in_the_same_district_is_refused(): void
    {
        Institution::factory()->create(['name' => 'Azania Secondary School', 'region' => 'Dar es Salaam', 'district' => 'Ilala', 'code' => 'DSM-S-0001']);

        Livewire::actingAs($this->admin())
            ->test(Form::class)
            ->set('name', 'azania  secondary-school')
            ->set('type', 'secondary')
            ->set('region', 'Dar es Salaam')
            ->set('district', 'ilala')
            ->assertSet('duplicateWarning', fn ($warning) => str_contains((string) $warning, 'DSM-S-0001'))
            ->call('save')
            ->assertHasErrors('name');

        $this->assertSame(1, Institution::where('region', 'Dar es Salaam')->where('district', 'Ilala')->count());
    }

    public function test_region_must_come_from_the_official_list(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Form::class)
            ->set('name', 'Mystery School')
            ->set('region', 'Atlantis')
            ->set('district', 'Nowhere')
            ->call('save')
            ->assertHasErrors(['region' => 'in']);
    }

    public function test_editing_keeps_the_institution_code(): void
    {
        $institution = Institution::factory()->create(['code' => 'ARU-S-0007', 'region' => 'Arusha', 'district' => 'Arusha']);

        Livewire::actingAs($this->admin())
            ->test(Form::class, ['institution' => $institution])
            ->set('name', 'Renamed High School')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('ARU-S-0007', $institution->fresh()->code);
        $this->assertSame('Renamed High School', $institution->fresh()->name);
    }

    public function test_deactivation_requires_a_reason_and_hides_the_institution_from_registration(): void
    {
        $institution = Institution::factory()->create(['name' => 'Closing Secondary']);

        $component = Livewire::actingAs($this->admin())
            ->test(Show::class, ['institution' => $institution])
            ->set('changingStatus', true)
            ->call('changeStatus')
            ->assertHasErrors('statusReason');

        $component->set('statusReason', 'School merged with another')->call('changeStatus')->assertHasNoErrors();

        $this->assertSame(Institution::STATUS_INACTIVE, $institution->fresh()->status);
        $this->assertSame(2, $institution->statusHistory()->count() + 1);

        Livewire::test(Register::class)
            ->set('institution_search', 'Closing')
            ->assertDontSee('Closing Secondary');
    }

    public function test_only_one_active_tax_club_per_institution(): void
    {
        $institution = Institution::factory()->create();
        $component = Livewire::actingAs($this->admin())->test(Show::class, ['institution' => $institution]);

        $component->set('clubName', 'Azania Tax Club')->call('establishClub')->assertHasNoErrors();
        $component->call('establishClub')->assertHasErrors('action');

        $this->assertSame(1, TaxClub::where('institution_id', $institution->institution_id)->count());
    }

    public function test_assigning_a_new_patron_creates_an_account_with_a_one_time_password(): void
    {
        $this->seed(PatronRoleSeeder::class);
        $club = TaxClub::factory()->create();

        $component = Livewire::actingAs($this->admin())
            ->test(Show::class, ['institution' => $club->institution])
            ->set('patronPhone', '0754 000 111')
            ->set('patronName', 'Mwalimu Rehema')
            ->call('assignPatron', $club->tax_club_id)
            ->assertHasNoErrors();

        $patron = User::where('phone', '+255754000111')->firstOrFail();
        $credentials = $component->get('newPatronCredentials');

        $this->assertSame('staff', $patron->user_type);
        $this->assertTrue($patron->hasRole('patron'));
        $this->assertTrue(Hash::check($credentials['password'], $patron->password_hash));
        $this->assertSame($club->institution_id, $patron->verifiableInstitutionIds()[0]);
    }

    public function test_existing_user_can_be_assigned_without_creating_a_new_account(): void
    {
        $this->seed(PatronRoleSeeder::class);
        $club = TaxClub::factory()->create();
        $teacher = User::factory()->create(['phone' => '+255765000222']);

        Livewire::actingAs($this->admin())
            ->test(Show::class, ['institution' => $club->institution])
            ->set('patronPhone', '0765000222')
            ->call('assignPatron', $club->tax_club_id)
            ->assertHasNoErrors()
            ->assertSet('newPatronCredentials', null);

        $this->assertSame(1, User::where('phone', '+255765000222')->count());
        $this->assertTrue($teacher->fresh()->hasRole('patron'));
    }

    public function test_unknown_phone_without_a_name_explains_what_is_needed(): void
    {
        $club = TaxClub::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Show::class, ['institution' => $club->institution])
            ->set('patronPhone', '0754999888')
            ->call('assignPatron', $club->tax_club_id)
            ->assertHasErrors('patron');

        $this->assertSame(0, ClubPatron::count());
    }

    public function test_ending_an_assignment_keeps_history_and_removes_patron_access(): void
    {
        $this->seed(PatronRoleSeeder::class);
        $club = TaxClub::factory()->create();
        $admin = $this->admin();

        $component = Livewire::actingAs($admin)
            ->test(Show::class, ['institution' => $club->institution])
            ->set('patronPhone', '0754000111')
            ->set('patronName', 'Mwalimu Rehema')
            ->call('assignPatron', $club->tax_club_id);

        $assignment = ClubPatron::firstOrFail();

        $component->call('startEndingAssignment', $assignment->id)
            ->set('endReason', 'Transferred to another school')
            ->call('endAssignment')
            ->assertHasNoErrors();

        $assignment->refresh();
        $this->assertNotNull($assignment->ends_on);
        $this->assertSame($admin->user_id, $assignment->ended_by);
        $this->assertFalse($assignment->user->fresh()->hasRole('patron'));
        $this->assertSame([], $assignment->user->fresh()->verifiableInstitutionIds());
    }

    public function test_csv_import_creates_valid_rows_and_reports_skipped_ones(): void
    {
        Institution::factory()->create(['name' => 'Existing School', 'region' => 'Mwanza', 'district' => 'Ilemela']);

        $csv = "name,category,region,district,contact_name,contact_phone,contact_email\n"
            ."Mwanza Secondary School,secondary,mwanza,Nyamagana,,,\n"
            ."Existing School,secondary,Mwanza,Ilemela,,,\n"
            ."Bad Region College,college,Atlantis,Somewhere,,,\n"
            .",,,,,,\n"
            ."University of Testing,university,Dodoma,Dodoma,Registrar,0712000333,registrar@example.ac.tz\n";

        $component = Livewire::actingAs($this->admin())
            ->test(Import::class)
            ->set('file', UploadedFile::fake()->createWithContent('schools.csv', $csv))
            ->call('import')
            ->assertHasNoErrors();

        $report = $component->get('report');

        $this->assertSame(2, $report['created']);
        $this->assertSame([3, 4], array_column($report['skipped'], 'line'));
        $this->assertDatabaseHas('institutions', ['name' => 'Mwanza Secondary School', 'region' => 'Mwanza', 'code' => 'MWA-S-0001']);
        $this->assertDatabaseHas('institutions', ['name' => 'University of Testing', 'contact_phone' => '+255712000333']);
    }

    public function test_list_filters_by_region_and_status(): void
    {
        Institution::factory()->create(['name' => 'Arusha Active', 'region' => 'Arusha']);
        Institution::factory()->inactive()->create(['name' => 'Arusha Closed', 'region' => 'Arusha']);
        Institution::factory()->create(['name' => 'Mbeya Active', 'region' => 'Mbeya']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('region', 'Arusha')
            ->set('status', 'active')
            ->assertSee('Arusha Active')
            ->assertDontSee('Arusha Closed')
            ->assertDontSee('Mbeya Active');
    }

    public function test_pages_require_permissions(): void
    {
        $institution = Institution::factory()->create();
        $viewer = $this->officerWith(['view_institutions', 'view_all_registrations']);

        $this->actingAs($viewer)->get(route('admin.institutions.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.institutions.show', $institution))->assertOk();
        $this->actingAs($viewer)->get(route('admin.institutions.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.institutions.import'))->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(Show::class, ['institution' => $institution])
            ->set('statusReason', 'Not allowed to do this')
            ->call('changeStatus')
            ->assertForbidden();
    }

    public function test_public_api_lists_only_active_institutions_without_contact_details(): void
    {
        Institution::factory()->create(['name' => 'Open School', 'contact_phone' => '+255712000444']);
        Institution::factory()->inactive()->create(['name' => 'Closed School']);

        $this->getJson('/api/v1/institutions')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Open School'])
            ->assertJsonMissing(['name' => 'Closed School'])
            ->assertJsonMissing(['contact_phone' => '+255712000444']);
    }

    public function test_patrons_are_not_counted_as_members(): void
    {
        $this->seed(PatronRoleSeeder::class);
        $club = TaxClub::factory()->create();
        User::factory()->create(['institution_id' => $club->institution_id]);

        Livewire::actingAs($this->admin())
            ->test(Show::class, ['institution' => $club->institution])
            ->set('patronPhone', '0754000111')
            ->set('patronName', 'Mwalimu Rehema')
            ->call('assignPatron', $club->tax_club_id)
            ->assertViewHas('memberCounts', fn (array $counts) => $counts['active'] === 1);
    }
}
