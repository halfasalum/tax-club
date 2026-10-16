@php
    $inputClasses = fn (string $field) => 'mt-1.5 w-full rounded-md border bg-white px-4 py-3 focus:outline-none '.($errors->has($field) ? 'border-danger' : 'border-line focus:border-ink');
    $categories = [
        'secondary' => __('Secondary school'),
        'college' => __('College'),
        'university' => __('University'),
    ];
@endphp
<div>
    @if ($reference)
        <div role="status">
            <h1 class="text-3xl font-extrabold tracking-tight">{{ __('Registration received') }}</h1>
            <p class="mt-3 text-lg text-slate">{{ __('Your patron or TRA coordinator will confirm your membership. You can sign in once it is approved.') }}</p>
            <div class="mt-8 rounded-lg border-2 border-dashed border-ink/30 bg-white p-6 font-receipt">
                <p class="text-sm text-slate">{{ __('Registration reference') }}</p>
                <p class="mt-1 text-2xl font-semibold tracking-wide">{{ $reference }}</p>
                <p class="mt-3 text-sm text-slate">{{ __('Keep this reference. You may be asked for it when your membership is checked.') }}</p>
            </div>
            <a href="{{ route('login') }}" class="mt-8 inline-block rounded-md bg-ink px-5 py-3 font-semibold text-white hover:bg-ink-soft">{{ __('Go to sign in') }}</a>
        </div>
    @else
        <h1 class="text-3xl font-extrabold tracking-tight">{{ __('Join Tax Club') }}</h1>
        <p class="mt-2 text-slate">{{ __('Membership is free for students. It takes about two minutes.') }}</p>

        <form wire:submit="register" class="mt-8 space-y-5" novalidate>
            <div>
                <label for="full_name" class="block text-sm font-semibold">{{ __('Full name') }}</label>
                <input wire:model="full_name" id="full_name" type="text" autocomplete="name" class="{{ $inputClasses('full_name') }}">
                @error('full_name')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="phone" class="block text-sm font-semibold">{{ __('Mobile number') }}</label>
                <input wire:model="phone" id="phone" type="tel" autocomplete="tel" inputmode="tel" placeholder="0712 345 678" class="{{ $inputClasses('phone') }}">
                @error('phone')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-semibold">{{ __('Email') }} <span class="font-normal text-slate">({{ __('optional') }})</span></label>
                <input wire:model="email" id="email" type="email" autocomplete="email" class="{{ $inputClasses('email') }}">
                @error('email')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
            </div>

            <fieldset>
                <legend class="text-sm font-semibold">{{ __('Where do you study?') }}</legend>
                <div class="mt-2 grid grid-cols-3 gap-2">
                    @foreach ($categories as $value => $label)
                        <label @class(['flex cursor-pointer items-center justify-center rounded-md border px-3 py-2.5 text-center text-sm font-medium', 'border-ink bg-ink text-white' => $user_type === $value, 'border-line bg-white hover:border-ink' => $user_type !== $value])>
                            <input type="radio" wire:model.live="user_type" value="{{ $value }}" class="sr-only">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('user_type')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
            </fieldset>

            <div>
                <label for="institution_search" class="block text-sm font-semibold">{{ __('Your institution') }}</label>
                @if ($this->selectedInstitution)
                    <div class="mt-1.5 flex items-center justify-between gap-3 rounded-md border border-ink bg-white px-4 py-3">
                        <span>
                            <span class="font-semibold">{{ $this->selectedInstitution->name }}</span>
                            <span class="block text-sm text-slate">{{ collect([$this->selectedInstitution->district, $this->selectedInstitution->region])->filter()->implode(', ') }}</span>
                        </span>
                        <button type="button" wire:click="clearInstitution" class="text-sm font-semibold text-ink underline">{{ __('Change') }}</button>
                    </div>
                @else
                    <input wire:model.live.debounce.300ms="institution_search" id="institution_search" type="search" autocomplete="off"
                           placeholder="{{ __('Type at least 2 letters of the name') }}" class="{{ $inputClasses('institution_id') }}"
                           aria-controls="institution-results">
                    @if (mb_strlen(trim($institution_search)) >= 2)
                        <ul id="institution-results" class="mt-2 divide-y divide-line overflow-hidden rounded-md border border-line bg-white">
                            @forelse ($this->institutions as $institution)
                                <li>
                                    <button type="button" wire:click="selectInstitution('{{ $institution->institution_id }}')" class="w-full px-4 py-2.5 text-left hover:bg-paper focus:bg-paper">
                                        <span class="font-medium">{{ $institution->name }}</span>
                                        <span class="block text-sm text-slate">{{ collect([$institution->district, $institution->region])->filter()->implode(', ') }}</span>
                                    </button>
                                </li>
                            @empty
                                <li class="px-4 py-3 text-sm text-slate">{{ __('No registered institution matches. Ask your patron to register your institution with TRA.') }}</li>
                            @endforelse
                        </ul>
                    @endif
                @endif
                @error('institution_id')<p class="mt-1.5 text-sm text-danger">{{ __('Choose your institution from the list.') }}</p>@enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold">{{ __('Password') }}</label>
                <input wire:model="password" id="password" type="password" autocomplete="new-password" aria-describedby="password-help" class="{{ $inputClasses('password') }}">
                <p id="password-help" class="mt-1.5 text-sm text-slate">{{ __('At least 8 characters.') }}</p>
                @error('password')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-semibold">{{ __('Repeat password') }}</label>
                <input wire:model="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password" class="{{ $inputClasses('password_confirmation') }}">
            </div>

            <div>
                <label class="flex items-start gap-3">
                    <input type="checkbox" wire:model="accepted_terms" class="mt-1 size-4 accent-ink">
                    <span class="text-sm leading-relaxed">{{ __('I agree that TRA may use my details to manage my Tax Club membership. If I am under 18, my parent or guardian knows I am registering.') }}</span>
                </label>
                @error('accepted_terms')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="w-full rounded-md bg-flag px-5 py-3 font-bold text-ink hover:bg-[#ffc81f] disabled:opacity-60" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="register">{{ __('Submit registration') }}</span>
                <span wire:loading wire:target="register">{{ __('Submitting…') }}</span>
            </button>
        </form>

        <p class="mt-8 text-slate">
            {{ __('Already a member?') }}
            <a href="{{ route('login') }}" class="font-semibold text-ink underline decoration-flag decoration-2 underline-offset-4">{{ __('Sign in') }}</a>
        </p>
    @endif
</div>
