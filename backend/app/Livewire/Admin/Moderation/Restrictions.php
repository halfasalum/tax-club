<?php

namespace App\Livewire\Admin\Moderation;

use App\Models\User;
use App\Models\UserRestriction;
use App\Support\MembershipCard;
use App\Support\SafetyDesk;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Active account restrictions, and restricting a member directly by
 * membership number (SAF-REQ04).
 */
#[Layout('components.layouts.app')]
class Restrictions extends Component
{
    public string $number = '';

    public string $type = 'community';

    public ?int $days = 7;

    public string $reason = '';

    public ?int $lifting = null;

    public string $liftReason = '';

    public ?string $statusMessage = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasControl('restrict_users'), 403);
    }

    public function restrict(): void
    {
        $this->validate(['number' => 'required|string', 'type' => 'required|in:'.implode(',', UserRestriction::TYPES), 'days' => 'nullable|integer|min:1|max:365', 'reason' => 'required|string|max:1000']);
        $member = User::where('membership_id', MembershipCard::normalizeNumber($this->number))->first();

        if (! $member) {
            $this->addError('number', __('No member has this number.'));

            return;
        }

        try {
            SafetyDesk::restrict($member, $this->type, $this->days ?: null, $this->reason, auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('number', $exception->getMessage());

            return;
        }

        $this->reset('number', 'reason');
        $this->statusMessage = __('Restriction applied.');
    }

    public function lift(): void
    {
        $restriction = UserRestriction::findOrFail($this->lifting);

        try {
            SafetyDesk::liftRestriction($restriction, auth()->user(), $this->liftReason);
        } catch (InvalidArgumentException $exception) {
            $this->addError('liftReason', $exception->getMessage());

            return;
        }

        $this->reset('lifting', 'liftReason');
        $this->statusMessage = __('Restriction lifted.');
    }

    public function render(): mixed
    {
        return view('livewire.admin.moderation.restrictions', [
            'restrictions' => UserRestriction::active()->with(['user:user_id,full_name,membership_id', 'creator:user_id,full_name'])->latest()->limit(200)->get(),
        ])->title(__('Restrictions'));
    }
}
