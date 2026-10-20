<?php

namespace App\Livewire\Member\Competitions;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Competitions open to the member, and results (CMP-REQ03, CMP-REQ05).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    public function render(): mixed
    {
        $member = auth()->user();
        $entries = CompetitionEntry::where('user_id', $member->user_id)->get()->keyBy('competition_id');
        $competitions = Competition::live()->orderByDesc('starts_at')->limit(100)->get()
            ->filter(fn (Competition $competition) => $competition->isVisibleTo($member) || $entries->has($competition->competition_id));

        return view('livewire.member.competitions.index', [
            'open' => $competitions->filter(fn (Competition $competition) => $competition->publication_status === Competition::PUBLISHED && ! $competition->hasEnded())->values(),
            'finished' => $competitions->filter(fn (Competition $competition) => $competition->hasEnded() || $competition->publication_status === Competition::CANCELLED)->values(),
            'entries' => $entries,
        ])->title(__('Competitions'));
    }
}
