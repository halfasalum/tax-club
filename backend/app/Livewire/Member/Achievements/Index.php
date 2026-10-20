<?php

namespace App\Livewire\Member\Achievements;

use App\Models\Certificate;
use App\Models\RewardAward;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The member's certificates and rewards (CER-REQ01, RWD-REQ02).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    public function render(): mixed
    {
        $member = auth()->user();

        return view('livewire.member.achievements.index', [
            'certificates' => Certificate::where('user_id', $member->user_id)->latest('issued_at')->get(),
            'rewards' => RewardAward::where('user_id', $member->user_id)->where('status', '!=', RewardAward::PENDING_APPROVAL)->with('reward')->latest()->get(),
        ])->title(__('My achievements'));
    }
}
