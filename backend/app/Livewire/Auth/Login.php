<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\MemberRegistration;
use App\Support\StaffSignIn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.auth')]
class Login extends Component
{
    private const CHALLENGE_KEY = 'login.challenge';

    public string $identifier = '';

    public string $password = '';

    public string $code = '';

    /** True while waiting for a staff member's SMS code. */
    public bool $awaitingCode = false;

    public function login(): mixed
    {
        $this->validate(['identifier' => 'required|string|max:150', 'password' => 'required|string']);

        $throttleKey = Str::lower($this->identifier).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'identifier' => __('Too many attempts. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        $user = User::findByLoginIdentifier($this->identifier);

        if (! $user || ! Hash::check($this->password, $user->password_hash)) {
            RateLimiter::hit($throttleKey, 900);
            StaffSignIn::recordFailure($this->identifier, $user, 'web');

            throw ValidationException::withMessages([
                'identifier' => __('The phone number, email or password is incorrect.'),
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'identifier' => MemberRegistration::blockedLoginMessage($user),
            ]);
        }

        RateLimiter::clear($throttleKey);

        if (StaffSignIn::required($user)) {
            try {
                StaffSignIn::sendCode($user, request()->ip());
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['identifier' => $exception->getMessage()]);
            }

            session()->put(self::CHALLENGE_KEY, ['user_id' => $user->user_id, 'expires_at' => now()->addMinutes(StaffSignIn::CODE_MINUTES)->timestamp]);
            $this->reset('password');
            $this->awaitingCode = true;

            return null;
        }

        return $this->completeLogin($user, twoFactor: false);
    }

    public function confirmCode(): mixed
    {
        $this->validate(['code' => 'required|digits:6'], [], ['code' => __('code')]);

        $challenge = session()->get(self::CHALLENGE_KEY);
        $user = $challenge && $challenge['expires_at'] > now()->timestamp ? User::find($challenge['user_id']) : null;

        if (! $user || ! $user->isActive()) {
            $this->startOver();

            throw ValidationException::withMessages(['identifier' => __('Your sign-in code has expired. Sign in again.')]);
        }

        if (! StaffSignIn::confirm($user, $this->code)) {
            throw ValidationException::withMessages(['code' => __('The code is incorrect or has expired. Request a new code and try again.')]);
        }

        session()->forget(self::CHALLENGE_KEY);

        return $this->completeLogin($user, twoFactor: true);
    }

    public function startOver(): void
    {
        session()->forget(self::CHALLENGE_KEY);
        $this->reset('password', 'code', 'awaitingCode');
    }

    private function completeLogin(User $user, bool $twoFactor): mixed
    {
        Auth::login($user);
        session()->regenerate();
        StaffSignIn::recordLogin($user, 'web', $twoFactor);

        return $this->redirectIntended(
            $user->canAccessAdmin() ? route('admin.dashboard') : route('member.dashboard'),
            navigate: false,
        );
    }

    public function render(): mixed
    {
        return view('livewire.auth.login')->title(__('Sign in'));
    }
}
