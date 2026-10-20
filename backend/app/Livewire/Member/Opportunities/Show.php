<?php

namespace App\Livewire\Member\Opportunities;

use App\Models\Opportunity;
use App\Support\OpportunityBoard;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * One opportunity: details, eligibility, save and apply (OPP-REQ04, OPP-REQ06).
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public Opportunity $opportunity;

    public function mount(string $slug): void
    {
        $member = auth()->user();
        $this->opportunity = Opportunity::where('slug', $slug)->whereNotIn('publication_status', [Opportunity::NOT_PUBLISHED, Opportunity::SCHEDULED, Opportunity::ARCHIVED])->firstOrFail();

        // Closed listings stay readable for members who saved them.
        abort_unless($this->opportunity->isVisibleTo($member) || OpportunityBoard::isSaved($this->opportunity, $member), 404);
        OpportunityBoard::record($this->opportunity, $member, 'view');
    }

    public function toggleSave(): void
    {
        $member = auth()->user();

        if (OpportunityBoard::isSaved($this->opportunity, $member)) {
            OpportunityBoard::unsave($this->opportunity, $member);

            return;
        }

        try {
            OpportunityBoard::save($this->opportunity, $member);
        } catch (InvalidArgumentException $exception) {
            $this->addError('save', $exception->getMessage());
        }
    }

    public function render(): mixed
    {
        return view('livewire.member.opportunities.show', [
            'saved' => OpportunityBoard::isSaved($this->opportunity, auth()->user()),
        ])->title($this->opportunity->title);
    }
}
