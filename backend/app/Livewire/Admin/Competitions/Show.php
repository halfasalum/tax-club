<?php

namespace App\Livewire\Admin\Competitions;

use App\Models\Competition;
use App\Models\Reward;
use App\Support\CompetitionDesk;
use App\Support\Review;
use App\Support\Rewards;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Review, publish, judge and publish results of a competition
 * (CMP-REQ02-05, RWD-REQ01).
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public Competition $competition;

    public string $reviewNote = '';

    public string $publishAt = '';

    public string $reason = '';

    /** @var array<int, string> */
    public array $scores = [];

    /** @var array<int, string> */
    public array $comments = [];

    /** @var list<array{rank_from: int, rank_to: int, reward_id: ?int}> */
    public array $prizes = [];

    public ?int $disqualifying = null;

    public ?string $statusMessage = null;

    public function mount(Competition $competition): void
    {
        $this->competition = $competition;
        $this->statusMessage = session('status');
        $this->prizes = $competition->prizes->map(fn ($prize) => ['rank_from' => $prize->rank_from, 'rank_to' => $prize->rank_to, 'reward_id' => $prize->reward_id])->all();
    }

    public function submit(): void
    {
        $this->can('create_competition');
        $this->attempt(fn () => CompetitionDesk::submit($this->competition, auth()->user()), __('Sent for review.'));
    }

    public function approve(): void
    {
        $this->can('review_competition');
        $this->attempt(fn () => CompetitionDesk::approve($this->competition, auth()->user(), trim($this->reviewNote) ?: null), __('Approved. It can now be published.'));
    }

    public function returnForChanges(): void
    {
        $this->can('review_competition');
        $this->attempt(fn () => CompetitionDesk::returnForChanges($this->competition, auth()->user(), trim($this->reviewNote)), __('Returned to the author with your notes.'));
    }

    public function reject(): void
    {
        $this->can('review_competition');
        $this->attempt(fn () => CompetitionDesk::reject($this->competition, auth()->user(), trim($this->reviewNote)), __('Rejected.'));
    }

    public function publish(): void
    {
        $this->can('publish_competition');
        $at = $this->publishAt ? Carbon::parse($this->publishAt) : null;
        $this->attempt(fn () => CompetitionDesk::publish($this->competition, auth()->user(), $at), $at?->isFuture() ? __('Scheduled.') : __('Published. Eligible members can register.'));
    }

    public function cancel(): void
    {
        $this->can('publish_competition');
        $this->attempt(fn () => CompetitionDesk::cancel($this->competition, auth()->user(), $this->reason), __('Cancelled. Participants were told.'));
    }

    public function addPrize(): void
    {
        $last = end($this->prizes);
        $from = $last ? $last['rank_to'] + 1 : 1;
        $this->prizes[] = ['rank_from' => $from, 'rank_to' => $from, 'reward_id' => null];
    }

    public function removePrize(int $index): void
    {
        unset($this->prizes[$index]);
        $this->prizes = array_values($this->prizes);
    }

    public function savePrizes(): void
    {
        $this->can('manage_rewards');
        $this->validate(['prizes.*.rank_from' => 'required|integer|min:1', 'prizes.*.rank_to' => 'required|integer|min:1', 'prizes.*.reward_id' => 'required|exists:rewards,id']);
        $this->attempt(fn () => Rewards::setPrizes($this->competition, array_map(fn ($prize) => ['rank_from' => (int) $prize['rank_from'], 'rank_to' => (int) $prize['rank_to'], 'reward_id' => (int) $prize['reward_id']], $this->prizes), auth()->user()), __('Prizes saved.'));
    }

    public function saveScore(int $entryId): void
    {
        $this->can('judge_competition');
        $entry = $this->competition->entries()->findOrFail($entryId);
        $this->attempt(fn () => CompetitionDesk::score($entry, auth()->user(), (float) ($this->scores[$entryId] ?? -1), $this->comments[$entryId] ?? null), __('Score saved.'));
    }

    public function disqualify(): void
    {
        $this->can('approve_results');
        $entry = $this->competition->entries()->findOrFail($this->disqualifying);
        $this->attempt(function () use ($entry) {
            CompetitionDesk::disqualify($entry, auth()->user(), $this->reason);
            $this->disqualifying = null;
        }, __('Entry disqualified.'));
    }

    public function rank(): void
    {
        $this->can('create_competition', 'approve_results');
        $this->attempt(fn () => CompetitionDesk::rank($this->competition), __('Ranking updated.'));
    }

    public function submitResults(): void
    {
        $this->can('create_competition', 'judge_competition');
        $this->attempt(fn () => CompetitionDesk::submitResults($this->competition, auth()->user()), __('Results sent for approval.'));
    }

    public function approveResults(): void
    {
        $this->can('approve_results');
        $this->attempt(fn () => CompetitionDesk::approveResults($this->competition, auth()->user()), __('Results published. Certificates and prizes were issued.'));
    }

    public function returnResults(): void
    {
        $this->can('approve_results');
        $this->attempt(fn () => CompetitionDesk::returnResults($this->competition, auth()->user(), $this->reviewNote), __('Results returned for changes.'));
    }

    private function attempt(callable $action, string $success): void
    {
        $this->resetErrorBag();

        try {
            $action();
        } catch (InvalidArgumentException $exception) {
            $this->addError('action', $exception->getMessage());

            return;
        }

        $this->reset('reviewNote', 'reason', 'publishAt');
        $this->statusMessage = $success;
    }

    private function can(string ...$controls): void
    {
        abort_unless(collect($controls)->contains(fn ($control) => auth()->user()->hasControl($control)), 403);
    }

    public function render(): mixed
    {
        $this->competition->refresh();
        $user = auth()->user();
        $entries = $this->competition->entries()
            ->with(['user:user_id,full_name,membership_id,institution_id,age_band', 'user.institution:institution_id,name,region', 'scores' => fn ($query) => $query->where('judge_id', $user->user_id)])
            ->withCount('scores')
            ->orderByRaw('rank is null')->orderBy('rank')->orderByDesc('submitted_at')
            ->get();

        foreach ($entries as $entry) {
            $this->scores[$entry->id] ??= (string) ($entry->scores->first()?->score ?? '');
            $this->comments[$entry->id] ??= (string) ($entry->scores->first()?->comment ?? '');
        }

        return view('livewire.admin.competitions.show', [
            'entries' => $entries,
            'problems' => CompetitionDesk::readinessErrors($this->competition),
            'rewards' => Reward::where('is_active', true)->orderBy('name_en')->get(),
            'history' => $this->competition->history(),
            'can' => [
                'edit' => $user->hasControl('create_competition'),
                'review' => $user->hasControl('review_competition'),
                'publish' => $user->hasControl('publish_competition'),
                'judge' => $user->hasControl('judge_competition'),
                'approveResults' => $user->hasControl('approve_results'),
                'prizes' => $user->hasControl('manage_rewards'),
            ],
            'ownSubmission' => $this->competition->editorial_status === Review::IN_REVIEW && $this->competition->submitted_by === $user->user_id,
        ])->title($this->competition->title);
    }
}
