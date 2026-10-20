<?php

namespace App\Livewire\Member\Opportunities;

use App\Support\OpportunityBoard;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Browse, search and filter open opportunities, saved ones and career
 * guidance (OPP-REQ04, OPP-REQ06, OPP-REQ07).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    #[Url]
    public string $tab = 'open';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $type = '';

    #[Url(as: 'soon')]
    public bool $closingSoon = false;

    public function render(): mixed
    {
        $member = auth()->user();

        return view('livewire.member.opportunities.index', [
            'opportunities' => $this->tab === 'saved' ? OpportunityBoard::savedBy($member) : OpportunityBoard::browse($member, $this->search, $this->type, $this->closingSoon),
            'resources' => OpportunityBoard::careerResources($member),
        ])->title(__('Opportunities'));
    }
}
