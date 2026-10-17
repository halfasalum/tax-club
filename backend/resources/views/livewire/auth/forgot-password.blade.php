@php($errorText = 'mt-2 font-semibold text-danger')
<div>
    <h1 class="text-3xl font-black tracking-tight">{{ __('Reset your password') }}</h1>

    @if (! $codeSent)
        <p class="mt-2 text-lg text-slate">{{ __('Enter the phone number or email you registered with. We will send a 6-digit code by SMS to your phone.') }}</p>

        <form wire:submit="sendCode" class="mt-8 space-y-6" novalidate>
            <div>
                <label for="identifier" class="block font-extrabold">{{ __('Phone number or email') }}</label>
                <input wire:model="identifier" id="identifier" type="text" autocomplete="username" inputmode="email" autofocus placeholder="0712 345 678"
                       @class(['field', 'field-error' => $errors->has('identifier')])>
                @error('identifier')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primary w-full text-lg" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="sendCode">{{ __('Send code') }}</span>
                <span wire:loading wire:target="sendCode">{{ __('Sending…') }}</span>
            </button>
        </form>
    @else
        <p class="mt-2 text-lg text-slate" role="status">
            {{ __('If :identifier belongs to an active account, a 6-digit code is on its way by SMS. It expires in :minutes minutes.', ['identifier' => $identifier, 'minutes' => \App\Support\PasswordRecovery::CODE_MINUTES]) }}
        </p>

        <form wire:submit="resetPassword" class="mt-8 space-y-6" novalidate x-data="{ show: false }">
            <div>
                <label for="code" class="block font-extrabold">{{ __('Code from the SMS') }}</label>
                <input wire:model="code" id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus
                       @class(['field font-receipt text-2xl tracking-[0.4em]', 'field-error' => $errors->has('code')])>
                @error('code')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password" class="block font-extrabold">{{ __('New password') }}</label>
                <div class="relative">
                    <input wire:model="password" id="password" :type="show ? 'text' : 'password'" type="password" autocomplete="new-password" aria-describedby="password-help"
                           @class(['field pr-24', 'field-error' => $errors->has('password')])>
                    <button type="button" x-on:click="show = !show" class="absolute right-2 top-[calc(50%+3px)] -translate-y-1/2 rounded-lg px-3 py-2 text-sm font-extrabold hover:bg-brand-wash" :aria-pressed="show.toString()" aria-controls="password password_confirmation">
                        <span x-show="!show">{{ __('Show') }}</span><span x-show="show" x-cloak>{{ __('Hide') }}</span>
                    </button>
                </div>
                @error('password')
                    <p class="{{ $errorText }}" role="alert">{{ $message }}</p>
                @else
                    <p id="password-help" class="mt-2 text-slate">{{ __('At least 8 characters.') }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block font-extrabold">{{ __('Repeat new password') }}</label>
                <input wire:model="password_confirmation" id="password_confirmation" :type="show ? 'text' : 'password'" type="password" autocomplete="new-password" class="field">
            </div>

            <button type="submit" class="btn-primary w-full text-lg" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="resetPassword">{{ __('Change password') }}</span>
                <span wire:loading wire:target="resetPassword">{{ __('Saving…') }}</span>
            </button>
        </form>

        <p class="mt-6 text-slate">
            {{ __('No SMS after a few minutes?') }}
            <button type="button" wire:click="startOver" class="link">{{ __('Send a new code') }}</button>
        </p>
    @endif

    <p class="mt-8 text-lg text-slate">
        <a href="{{ route('login') }}" class="link">{{ __('Back to sign in') }}</a>
    </p>
</div>
