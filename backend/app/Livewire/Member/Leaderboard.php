<?php

namespace App\Livewire\Member;

use App\Models\Badge;
use App\Models\PointsEntry;
use App\Support\Gamification;
use App\Support\Leaderboard as Ranking;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Leaderboards, the member's badges and points history (GAM-REQ08-10).
 */
#[Layout('components.layouts.app')]
class Leaderboard extends Component
{
    #[Url]
    public string $period = 'month';

    #[Url]
    public string $scope = 'national';

    public function render(): mixed
    {
        $member = auth()->user()->load('institution');
        $earned = DB::table('user_badges')->where('user_id', $member->user_id)->pluck('awarded_at', 'badge_id');

        return view('livewire.member.leaderboard', [
            'enabled' => Ranking::enabled(),
            'ranking' => Ranking::enabled() ? Ranking::rank($member, $this->period, $this->scope) : null,
            'badges' => Badge::where('is_active', true)->orderBy('sort_order')->orderBy('threshold')->get(),
            'earned' => $earned,
            'counts' => Gamification::achievementCounts($member),
            'history' => PointsEntry::where('user_id', $member->user_id)->latest('id')->limit(15)->get(),
            'monthPoints' => Gamification::pointsSince($member, now()->startOfMonth()),
        ])->title(__('Points and leaderboard'));
    }
}
