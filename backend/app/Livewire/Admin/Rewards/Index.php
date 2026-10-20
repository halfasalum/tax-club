<?php

namespace App\Livewire\Admin\Rewards;

use App\Models\Reward;
use App\Models\RewardAward;
use App\Models\User;
use App\Support\MembershipCard;
use App\Support\Rewards;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Reward catalogue and the tracker of rewards given to members
 * (RWD-REQ01/02).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'open';

    public ?int $editingId = null;

    public bool $editing = false;

    /** @var array<string, mixed> */
    public array $reward = ['name_sw' => '', 'name_en' => '', 'type' => 'trophy', 'description' => '', 'value_tzs' => null, 'stock' => null, 'needs_approval' => false, 'is_active' => true];

    public string $awardNumber = '';

    public ?string $awardRewardId = null;

    public string $awardReason = '';

    public ?int $acting = null;

    public string $actionNote = '';

    public ?string $statusMessage = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasControl('manage_rewards') || auth()->user()->hasControl('award_rewards') || auth()->user()->hasControl('approve_rewards'), 403);
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function newReward(): void
    {
        $this->reset('editingId', 'reward');
        $this->editing = true;
    }

    public function editReward(int $id): void
    {
        $model = Reward::findOrFail($id);
        $this->editingId = $id;
        $this->reward = $model->only(['name_sw', 'name_en', 'type', 'description', 'value_tzs', 'stock', 'needs_approval', 'is_active']);
        $this->editing = true;
    }

    public function saveReward(): void
    {
        abort_unless(auth()->user()->hasControl('manage_rewards'), 403);
        $this->validate([
            'reward.name_sw' => 'required|string|max:150',
            'reward.name_en' => 'required|string|max:150',
            'reward.type' => 'required|in:'.implode(',', Reward::TYPES),
            'reward.value_tzs' => 'nullable|integer|min:0',
            'reward.stock' => 'nullable|integer|min:0',
        ]);
        Rewards::save($this->editingId ? Reward::findOrFail($this->editingId) : null, $this->reward, auth()->user());
        $this->editing = false;
        $this->statusMessage = __('Saved.');
    }

    public function award(): void
    {
        abort_unless(auth()->user()->hasControl('award_rewards'), 403);
        $this->validate(['awardNumber' => 'required|string', 'awardRewardId' => 'required|exists:rewards,id', 'awardReason' => 'required|string|max:300']);
        $member = User::where('membership_id', MembershipCard::normalizeNumber($this->awardNumber))->first();

        if (! $member) {
            $this->addError('awardNumber', __('No member has this number.'));

            return;
        }

        $this->attempt(fn () => Rewards::award($member, Reward::findOrFail($this->awardRewardId), $this->awardReason, auth()->user()), __('Reward given to :name.', ['name' => $member->full_name]));
        $this->reset('awardNumber', 'awardReason');
    }

    public function act(int $awardId, string $action): void
    {
        $award = RewardAward::with('reward', 'user')->findOrFail($awardId);
        $control = $action === 'approve' ? 'approve_rewards' : 'award_rewards';
        abort_unless(auth()->user()->hasControl($control), 403);

        $this->attempt(fn () => match ($action) {
            'approve' => Rewards::approve($award, auth()->user()),
            'issue' => Rewards::issue($award, auth()->user(), $this->actionNote),
            'deliver' => Rewards::deliver($award, auth()->user(), $this->actionNote),
            'cancel' => Rewards::cancel($award, auth()->user(), $this->actionNote),
        }, __('Saved.'));
        $this->reset('acting', 'actionNote');
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

        $this->statusMessage = $success;
    }

    public function render(): mixed
    {
        return view('livewire.admin.rewards.index', [
            'rewards' => Reward::orderByDesc('is_active')->orderBy('name_en')->get(),
            'awards' => RewardAward::with(['reward', 'user:user_id,full_name,membership_id,institution_id', 'user.institution:institution_id,name'])
                ->when($this->status === 'open', fn ($query) => $query->whereIn('status', [RewardAward::PENDING_APPROVAL, RewardAward::APPROVED, RewardAward::ISSUED]))
                ->when($this->status !== 'open' && $this->status !== 'all', fn ($query) => $query->where('status', $this->status))
                ->latest()->paginate(25),
            'can' => [
                'manage' => auth()->user()->hasControl('manage_rewards'),
                'award' => auth()->user()->hasControl('award_rewards'),
                'approve' => auth()->user()->hasControl('approve_rewards'),
            ],
        ])->title(__('Rewards'));
    }
}
