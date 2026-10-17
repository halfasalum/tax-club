<?php

namespace Tests\Feature;

use App\Livewire\Admin\Statistics;
use App\Models\CoordinatorAssignment;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class StatisticsTest extends TestCase
{
    use CreatesOfficers, RefreshDatabase;

    private function seedMembers(): array
    {
        $dodoma = Institution::factory()->create(['region' => 'Dodoma', 'name' => 'Dodoma Secondary']);
        $tanga = Institution::factory()->create(['region' => 'Tanga', 'name' => 'Tanga College', 'type' => 'college']);

        User::factory()->count(3)->create(['institution_id' => $dodoma->institution_id]);
        User::factory()->minor()->create(['institution_id' => $dodoma->institution_id]);
        User::factory()->create(['institution_id' => $dodoma->institution_id, 'user_type' => 'alumni']);
        User::factory()->pending()->create(['institution_id' => $dodoma->institution_id]);
        User::factory()->create(['institution_id' => $dodoma->institution_id, 'user_type' => 'staff']);
        User::factory()->count(2)->create(['institution_id' => $tanga->institution_id, 'user_type' => 'college']);

        return [$dodoma, $tanga];
    }

    public function test_counts_by_region_institution_and_category_without_staff(): void
    {
        $this->seedMembers();
        $officer = $this->officerWith(['view_statistics', 'view_all_registrations'], ['user_type' => 'staff', 'institution_id' => null]);

        Livewire::actingAs($officer)->test(Statistics::class)
            ->assertViewHas('totals', fn (array $totals) => $totals['active_members'] === 7
                && $totals['active_secondary'] === 4
                && $totals['active_college'] === 2
                && $totals['active_alumni'] === 1
                && $totals['under_18'] === 1
                && $totals['waiting'] === 1
                && $totals['institutions'] === 2)
            ->set('region', 'Tanga')
            ->assertViewHas('totals', fn (array $totals) => $totals['active_members'] === 2)
            ->assertSee('Tanga College')
            ->assertDontSee('Dodoma Secondary');
    }

    public function test_coordinators_only_see_their_region(): void
    {
        $this->seedMembers();
        $coordinator = $this->officerWith(['view_statistics'], ['user_type' => 'staff', 'institution_id' => null]);
        CoordinatorAssignment::create(['user_id' => $coordinator->user_id, 'region' => 'Tanga', 'starts_on' => now()->toDateString()]);

        Livewire::actingAs($coordinator)->test(Statistics::class)
            ->assertViewHas('totals', fn (array $totals) => $totals['active_members'] === 2)
            ->assertDontSee('Dodoma Secondary');
    }

    public function test_csv_export(): void
    {
        $this->seedMembers();
        $officer = $this->officerWith(['view_statistics', 'view_all_registrations'], ['user_type' => 'staff', 'institution_id' => null]);

        $csv = $this->actingAs($officer)->get(route('admin.statistics.export', ['region' => 'Dodoma']))->assertOk()->streamedContent();

        $lines = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));
        $this->assertCount(2, $lines);
        $this->assertSame('Dodoma Secondary', $lines[1][1]);
        $this->assertSame('5', $lines[1][7]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'statistics.exported']);
    }
}
