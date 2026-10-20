<?php

namespace App\Livewire\Member\Community;

use App\Support\Community;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The discussion spaces and groups a member can join in (COM-REQ05, COM-REQ09).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    public function render(): mixed
    {
        return view('livewire.member.community.index', [
            'spaces' => Community::spacesFor(auth()->user()),
        ])->title(__('Community'));
    }
}
