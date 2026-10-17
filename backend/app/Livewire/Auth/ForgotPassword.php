<?php

namespace App\Livewire\Auth;

use App\Support\PasswordRecovery;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Reset a forgotten password with a code sent by SMS (REG-REQ05).
 */
#[Layout('components.layouts.auth')]
class ForgotPassword extends Component
{
    public string $identifier = '';

    public bool $codeSent = false;

    public string $code = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function sendCode(): void
    {
        $this->validate(['identifier' => ['required', 'string', 'max:150']], [], ['identifier' => __('phone number or email')]);

        try {
            PasswordRecovery::requestCode($this->identifier, request()->ip());
        } catch (InvalidArgumentException $exception) {
            $this->addError('identifier', $exception->getMessage());

            return;
        }

        $this->codeSent = true;
        $this->resetErrorBag();
    }

    public function resetPassword(): mixed
    {
        $this->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'code.regex' => __('Enter the 6-digit code from the SMS.'),
        ], [
            'code' => __('code'),
        ]);

        try {
            PasswordRecovery::reset($this->identifier, $this->code, $this->password);
        } catch (InvalidArgumentException $exception) {
            $this->addError('code', $exception->getMessage());
            $this->reset('password', 'password_confirmation');

            return null;
        }

        session()->flash('status', __('Your password was changed. Sign in with your new password.'));

        return $this->redirectRoute('login');
    }

    public function startOver(): void
    {
        $this->reset();
    }

    public function render(): mixed
    {
        return view('livewire.auth.forgot-password')->title(__('Reset your password'));
    }
}
