<?php

namespace App\Livewire\Admin\Competitions;

use App\Models\Competition;
use App\Support\Review;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Competitions in every state (CMP-REQ01-05).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    #[Url]
    public string $view = 'all';

    public function render(): mixed
    {
        $competitions = Competition::withCount(['entries as entries_count' => fn ($query) => $query->where('status', '!=', 'withdrawn')])
            ->when($this->view === 'review', fn ($query) => $query->where('editorial_status', Review::IN_REVIEW))
            ->when($this->view === 'live', fn ($query) => $query->where('publication_status', Competition::PUBLISHED)->where('results_status', '!=', Competition::RESULTS_PUBLISHED))
            ->when($this->view === 'results', fn ($query) => $query->where('results_status', Competition::RESULTS_IN_REVIEW))
            ->when($this->view === 'done', fn ($query) => $query->where('results_status', Competition::RESULTS_PUBLISHED))
            ->latest('updated_at')
            ->limit(100)
            ->get();

        return view('livewire.admin.competitions.index', [
            'competitions' => $competitions,
            'counts' => [
                'review' => Competition::where('editorial_status', Review::IN_REVIEW)->count(),
                'results' => Competition::where('results_status', Competition::RESULTS_IN_REVIEW)->count(),
            ],
            'canCreate' => auth()->user()->hasControl('create_competition'),
        ])->title(__('Competitions'));
    }
}
