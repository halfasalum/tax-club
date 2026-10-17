<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Members turning 18 move from the minor to the adult age band, which lifts
 * the minor privacy and safeguarding restrictions (PRO-REQ03, SAF-REQ05).
 */
#[Signature('members:refresh-age-bands')]
#[Description('Move members who have turned 18 into the adult age band')]
class RefreshAgeBands extends Command
{
    public function handle(): int
    {
        $updated = User::query()
            ->where('age_band', User::AGE_MINOR)
            ->whereNotNull('date_of_birth')
            ->whereDate('date_of_birth', '<=', now()->subYears(User::ADULT_AGE)->toDateString())
            ->update(['age_band' => User::AGE_ADULT]);

        $this->info("Moved {$updated} member(s) to the adult age band.");

        return self::SUCCESS;
    }
}
