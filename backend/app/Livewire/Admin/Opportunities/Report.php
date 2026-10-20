<?php

namespace App\Livewire\Admin\Opportunities;

use App\Support\OpportunityBoard;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Aggregated engagement with opportunities and career resources for
 * programme monitoring (OPP-REQ08).
 */
#[Layout('components.layouts.app')]
class Report extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasControl('view_opportunity_reports'), 403);
        $this->from = $this->from ?: now()->subDays(90)->toDateString();
        $this->to = $this->to ?: now()->toDateString();
    }

    public function render(): mixed
    {
        $from = rescue(fn () => Carbon::parse($this->from)->startOfDay(), now()->subDays(90)->startOfDay(), false);
        $to = rescue(fn () => Carbon::parse($this->to)->endOfDay(), now()->endOfDay(), false);

        return view('livewire.admin.opportunities.report', [
            'report' => OpportunityBoard::report($from, $to),
        ])->title(__('Opportunity engagement'));
    }
}
