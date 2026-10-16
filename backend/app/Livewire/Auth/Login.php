<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\MemberRegistration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.auth')]
class Login extends Component
{
    #[Validate('required|string|max:150')]
    public string $identifier = '';

    #[Validate('required|string')]
    public string $password = '';

    public function login(): mixed
    {
        $this->validate();

        $throttleKey = Str::lower($this->identifier).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'identifier' => __('Too many attempts. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        $user = User::findByLoginIdentifier($this->identifier);

        if (! $user || ! Hash::check($this->password, $user->password_hash)) {
            RateLimiter::hit($throttleKey, 900);

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
        Auth::login($user);
        session()->regenerate();

        $user->forceFill(['last_login' => now()])->save();

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
