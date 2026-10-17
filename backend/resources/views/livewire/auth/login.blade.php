<div>
    <h1 class="text-3xl font-black tracking-tight">{{ __('Welcome back') }}</h1>
    <p class="mt-2 text-lg text-slate">{{ __('Use the phone number or email you registered with.') }}</p>

    @if (session('status'))
        <p class="mt-6 rounded-xl bg-savanna px-4 py-3 font-extrabold text-white" role="status">{{ session('status') }}</p>
    @endif

    @if ($awaitingCode)
        <p class="mt-6 rounded-xl bg-brand-wash p-4 text-lg font-bold" role="status">{{ __('Staff accounts need a second step. We sent a 6-digit code by SMS to the phone number on your account.') }}</p>

        <form wire:submit="confirmCode" class="mt-8 space-y-6" novalidate>
            <div>
                <label for="code" class="block font-extrabold">{{ __('Code from the SMS') }}</label>
                <input wire:model="code" id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus
                       @class(['field font-receipt text-2xl tracking-[0.4em]', 'field-error' => $errors->has('code')])>
                @error('code')<p class="mt-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primary w-full text-lg" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="confirmCode">{{ __('Confirm and sign in') }}</span>
                <span wire:loading wire:target="confirmCode">{{ __('Signing in…') }}</span>
            </button>
        </form>
        <p class="mt-6 text-slate">
            {{ __('No SMS after a few minutes?') }}
            <button type="button" wire:click="startOver" class="link">{{ __('Start again') }}</button>
        </p>
    @else
    <form wire:submit="login" class="mt-8 space-y-6" novalidate>
        <div>
            <label for="identifier" class="block font-extrabold">{{ __('Phone number or email') }}</label>
            <input wire:model="identifier" id="identifier" type="text" autocomplete="username" inputmode="email" autofocus
                   @class(['field', 'field-error' => $errors->has('identifier')])
                   placeholder="0712 345 678"
                   aria-describedby="identifier-error" @error('identifier') aria-invalid="true" @enderror>
            @error('identifier')<p id="identifier-error" class="mt-2 flex gap-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
        </div>

        <div x-data="{ show: false }">
            <label for="password" class="block font-extrabold">{{ __('Password') }}</label>
            <div class="relative">
                <input wire:model="password" id="password" :type="show ? 'text' : 'password'" type="password" autocomplete="current-password"
                       @class(['field pr-24', 'field-error' => $errors->has('password')])>
                <button type="button" x-on:click="show = !show" class="absolute right-2 top-1/2 mt-[3px] -translate-y-1/2 rounded-lg px-3 py-2 text-sm font-extrabold hover:bg-brand-wash"
                        :aria-pressed="show.toString()" aria-controls="password">
                    <span x-show="!show">{{ __('Show') }}</span><span x-show="show" x-cloak>{{ __('Hide') }}</span>
                </button>
            </div>
            @error('password')<p class="mt-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
            <p class="mt-3 text-right"><a href="{{ route('password.request') }}" class="link">{{ __('Forgot your password?') }}</a></p>
        </div>

        <button type="submit" class="btn-primary w-full text-lg" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">{{ __('Sign in') }}</span>
            <span wire:loading wire:target="login">{{ __('Signing in…') }}</span>
        </button>
    </form>
    @endif

    <div class="mt-8 rounded-2xl bg-brand-wash p-5">
        <p class="font-extrabold">{{ __('New to Tax Club?') }}</p>
        <p class="mt-1 text-slate">{{ __('Membership is free for students. It takes about two minutes.') }}</p>
        <a href="{{ route('register') }}" class="btn-brand mt-4 w-full">{{ __('Join Tax Club') }}</a>
    </div>
</div>
