<?php

namespace App\Livewire\Membership;

use App\Models\MembershipVerification;
use App\Support\MembershipCard;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Check a Tax Club card by scanning its QR code or typing its number
 * (MEM-REQ03). Open to everyone; full details only for officers.
 */
#[Layout('components.layouts.auth')]
class Verify extends Component
{
    public const CHECKS_PER_MINUTE = 20;

    public string $number = '';

    /**
     * @var array{result: string, status_label: ?string, display_name: ?string, details: ?array<string, mixed>}|null
     */
    public ?array $outcome = null;

    public function mount(?string $token = null): void
    {
        if ($token !== null) {
            $this->runCheck($token, MembershipVerification::METHOD_QR);
        }
    }

    public function check(): void
    {
        $this->validate(['number' => 'required|string|max:40'], [], ['number' => __('membership number')]);

        $this->runCheck($this->number, MembershipVerification::METHOD_NUMBER);

        if ($this->outcome['result'] === MembershipCard::RESULT_MISTYPED) {
            $this->outcome = null;
            throw ValidationException::withMessages(['number' => __('This number is not correct. Check each character and try again.')]);
        }
    }

    public function startOver(): void
    {
        $this->reset('number', 'outcome');
    }

    private function runCheck(string $input, string $method): void
    {
        $key = 'membership-verify:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, self::CHECKS_PER_MINUTE)) {
            throw ValidationException::withMessages([
                'number' => __('Too many checks. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }
        RateLimiter::hit($key, 60);

        $check = MembershipCard::verify($input, $method, auth()->user(), request()->ip());
        $member = $check['member'];

        $this->outcome = [
            'result' => $check['result'],
            'status_label' => $check['status'] ? MembershipCard::statusLabel($check['status']) : null,
            'display_name' => $check['display_name'],
            'details' => $member ? [
                'full_name' => $member->full_name,
                'membership_id' => $member->membership_id,
                'user_type' => $member->user_type,
                'institution' => $member->institution?->name,
                'region' => $member->institution?->region,
                'member_since' => $member->membership_issued_at?->translatedFormat('j M Y'),
                'is_minor' => $member->isMinor(),
            ] : null,
        ];
    }

    public function render(): mixed
    {
        return view('livewire.membership.verify')->title(__('Check a membership card'));
    }
}
