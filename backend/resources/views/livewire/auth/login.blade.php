<div>
    <h1 class="text-3xl font-extrabold tracking-tight">{{ __('Sign in') }}</h1>
    <p class="mt-2 text-slate">{{ __('Use the phone number or email you registered with.') }}</p>

    <form wire:submit="login" class="mt-8 space-y-5" novalidate>
        <div>
            <label for="identifier" class="block text-sm font-semibold">{{ __('Phone number or email') }}</label>
            <input wire:model="identifier" id="identifier" type="text" autocomplete="username" inputmode="email" autofocus
                   @class(['mt-1.5 w-full rounded-md border bg-white px-4 py-3 focus:outline-none', 'border-danger' => $errors->has('identifier'), 'border-line focus:border-ink' => ! $errors->has('identifier')])
                   aria-describedby="identifier-error" @error('identifier') aria-invalid="true" @enderror>
            @error('identifier')<p id="identifier-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-semibold">{{ __('Password') }}</label>
            <input wire:model="password" id="password" type="password" autocomplete="current-password"
                   @class(['mt-1.5 w-full rounded-md border bg-white px-4 py-3 focus:outline-none', 'border-danger' => $errors->has('password'), 'border-line focus:border-ink' => ! $errors->has('password')])>
            @error('password')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
        </div>

        <button type="submit" class="w-full rounded-md bg-ink px-5 py-3 font-semibold text-white hover:bg-ink-soft disabled:opacity-60" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">{{ __('Sign in') }}</span>
            <span wire:loading wire:target="login">{{ __('Signing in…') }}</span>
        </button>
    </form>

    <p class="mt-8 text-slate">
        {{ __('New to Tax Club?') }}
        <a href="{{ route('register') }}" class="font-semibold text-ink underline decoration-flag decoration-2 underline-offset-4">{{ __('Join Tax Club') }}</a>
    </p>
</div>
