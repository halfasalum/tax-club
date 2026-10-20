<?php

namespace Database\Seeders;

use App\Models\Reward;
use App\Support\Community;
use Illuminate\Database\Seeder;

class CommunityAndRewardsSeeder extends Seeder
{
    /**
     * The national discussion space, one space per active Tax Club (with
     * its patrons as moderators) and a starter list of rewards. Safe to
     * re-run.
     */
    public function run(): void
    {
        Community::ensureDefaultSpaces();

        $rewards = [
            ['Kombe la Tax Club', 'Tax Club trophy', 'trophy', null],
            ['Medali ya Tax Club', 'Tax Club medal', 'medal', null],
            ['Fulana na kofia ya Tax Club', 'Tax Club T-shirt and cap', 'merchandise', null],
            ['Vitabu na vifaa vya shule', 'Books and stationery', 'books', 25000],
            ['Ziara ya ofisi za TRA', 'Visit to TRA offices', 'visit', null],
        ];

        foreach ($rewards as [$sw, $en, $type, $value]) {
            Reward::firstOrCreate(['name_en' => $en], ['name_sw' => $sw, 'type' => $type, 'value_tzs' => $value, 'needs_approval' => $value !== null, 'is_active' => true]);
        }

        $this->command?->info('✅ Community spaces and starter rewards ready.');
    }
}
