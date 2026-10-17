@php
    $categories = [
        'secondary' => __('Secondary school'),
        'college' => __('College'),
        'university' => __('University'),
    ];
    $errorText = 'mt-2 font-semibold text-danger';
@endphp
<div>
    @if ($reference)
        <div role="status">
            <span class="grid size-16 place-items-center rounded-full bg-savanna text-white" aria-hidden="true">
                <svg viewBox="0 0 24 24" class="size-9" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5 10 17l9-10"/></svg>
            </span>
            <h1 class="mt-6 text-3xl font-black tracking-tight">{{ __('Registration received') }}</h1>
            <p class="mt-3 text-lg text-slate">{{ __('Your patron or TRA coordinator will confirm your membership. You can sign in once it is approved.') }}</p>
            <div class="mt-8 rounded-2xl border-2 border-dashed border-ink bg-brand-wash p-6">
                <p class="font-extrabold text-ink-soft">{{ __('Registration reference') }}</p>
                <p class="mt-1 select-all font-receipt text-3xl font-semibold tracking-wide">{{ $reference }}</p>
                <p class="mt-3 text-slate">{{ __('Keep this reference. You may be asked for it when your membership is checked.') }}</p>
            </div>
            <ol class="mt-8 space-y-3 text-lg">
                <li class="flex gap-3"><span class="grid size-8 shrink-0 place-items-center rounded-full bg-savanna font-black text-white" aria-hidden="true">✓</span>{{ __('Registration submitted') }}</li>
                <li class="flex gap-3"><span class="grid size-8 shrink-0 place-items-center rounded-full bg-brand font-black" aria-hidden="true">2</span>{{ __('Your patron confirms your membership') }}</li>
                <li class="flex gap-3 text-slate"><span class="grid size-8 shrink-0 place-items-center rounded-full bg-mist font-black" aria-hidden="true">3</span>{{ __('Sign in and get your membership card') }}</li>
            </ol>
            <a href="{{ route('login') }}" class="btn-primary mt-8 w-full">{{ __('Go to sign in') }}</a>
        </div>
    @else
        <h1 class="text-3xl font-black tracking-tight">{{ __('Join Tax Club') }}</h1>
        <p class="mt-2 text-lg text-slate">{{ __('Membership is free for students. It takes about two minutes.') }}</p>

        <form wire:submit="register" class="mt-8 space-y-8" novalidate>
            <fieldset class="space-y-5">
                <legend class="text-sm font-black text-slate">{{ __('Your details') }}</legend>

                <div>
                    <label for="full_name" class="block font-extrabold">{{ __('Full name') }}</label>
                    <input wire:model.live.blur="full_name" id="full_name" type="text" autocomplete="name" @class(['field', 'field-error' => $errors->has('full_name')])>
                    @error('full_name')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="phone" class="block font-extrabold">{{ __('Mobile number') }}</label>
                    <input wire:model.live.blur="phone" id="phone" type="tel" autocomplete="tel" inputmode="tel" placeholder="0712 345 678" aria-describedby="phone-help" @class(['field', 'field-error' => $errors->has('phone')])>
                    @error('phone')
                        <p class="{{ $errorText }}" role="alert">{{ $message }}</p>
                    @else
                        <p id="phone-help" class="mt-2 text-slate">{{ __('You will use this number to sign in.') }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block font-extrabold">{{ __('Email') }} <span class="font-semibold text-slate">({{ __('optional') }})</span></label>
                    <input wire:model.live.blur="email" id="email" type="email" autocomplete="email" @class(['field', 'field-error' => $errors->has('email')])>
                    @error('email')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                </div>
            </fieldset>

            <fieldset class="space-y-5">
                <legend class="text-sm font-black text-slate">{{ __('Where do you study?') }}</legend>

                <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="{{ __('Where do you study?') }}">
                    @foreach ($categories as $value => $label)
                        <label @class(['flex min-h-14 cursor-pointer items-center justify-center rounded-xl border-2 px-2 py-2 text-center font-extrabold leading-tight transition-colors has-[:focus-visible]:outline has-[:focus-visible]:outline-3 has-[:focus-visible]:outline-ink', 'border-ink bg-brand' => $user_type === $value, 'border-line bg-white hover:border-ink' => $user_type !== $value])>
                            <input type="radio" wire:model.live="user_type" value="{{ $value }}" class="sr-only">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('user_type')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror

                <div>
                    <label for="institution_search" class="block font-extrabold">{{ __('Your institution') }}</label>
                    @if ($this->selectedInstitution)
                        <div class="mt-1.5 flex items-center justify-between gap-3 rounded-xl border-2 border-ink bg-brand-wash px-4 py-3">
                            <span>
                                <span class="block font-extrabold">{{ $this->selectedInstitution->name }}</span>
                                <span class="block text-slate">{{ collect([$this->selectedInstitution->district, $this->selectedInstitution->region])->filter()->implode(', ') }}</span>
                            </span>
                            <button type="button" wire:click="clearInstitution" class="link shrink-0">{{ __('Change') }}</button>
                        </div>
                    @else
                        <div class="relative">
                            <svg viewBox="0 0 20 20" class="pointer-events-none absolute left-4 top-[calc(50%+3px)] size-5 -translate-y-1/2 text-slate" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 3.4 9.82l3.64 3.64a.75.75 0 1 0 1.06-1.06l-3.64-3.64A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" clip-rule="evenodd"/></svg>
                            <input wire:model.live.debounce.300ms="institution_search" id="institution_search" type="search" autocomplete="off"
                                   placeholder="{{ __('Type at least 2 letters of the name') }}"
                                   @class(['field pl-12', 'field-error' => $errors->has('institution_id')]) aria-controls="institution-results">
                        </div>
                        @if (mb_strlen(trim($institution_search)) >= 2)
                            <ul id="institution-results" class="mt-2 divide-y divide-line overflow-hidden rounded-xl border-2 border-ink bg-white" wire:loading.class="opacity-60" wire:target="institution_search">
                                @forelse ($this->institutions as $institution)
                                    <li>
                                        <button type="button" wire:click="selectInstitution('{{ $institution->institution_id }}')" class="w-full px-4 py-3 text-left hover:bg-brand-wash focus:bg-brand-wash">
                                            <span class="block font-extrabold">{{ $institution->name }}</span>
                                            <span class="block text-slate">{{ collect([$institution->district, $institution->region])->filter()->implode(', ') }}</span>
                                        </button>
                                    </li>
                                @empty
                                    <li class="px-4 py-3 text-slate">{{ __('No registered institution matches. Ask your patron to register your institution with TRA.') }}</li>
                                @endforelse
                            </ul>
                        @endif
                    @endif
                    @error('institution_id')<p class="{{ $errorText }}" role="alert">{{ __('Choose your institution from the list.') }}</p>@enderror
                </div>
            </fieldset>

            <fieldset class="space-y-5" x-data="{ show: false }">
                <legend class="text-sm font-black text-slate">{{ __('Create a password') }}</legend>

                <div>
                    <label for="password" class="block font-extrabold">{{ __('Password') }}</label>
                    <div class="relative">
                        <input wire:model="password" id="password" :type="show ? 'text' : 'password'" type="password" autocomplete="new-password" aria-describedby="password-help" @class(['field pr-24', 'field-error' => $errors->has('password')])>
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
                    <label for="password_confirmation" class="block font-extrabold">{{ __('Repeat password') }}</label>
                    <input wire:model="password_confirmation" id="password_confirmation" :type="show ? 'text' : 'password'" type="password" autocomplete="new-password" class="field">
                </div>
            </fieldset>

            <div>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-mist p-4">
                    <input type="checkbox" wire:model="accepted_terms" class="mt-1 size-5 shrink-0 accent-ink">
                    <span class="leading-relaxed">{{ __('I agree that TRA may use my details to manage my Tax Club membership. If I am under 18, my parent or guardian knows I am registering.') }}</span>
                </label>
                @error('accepted_terms')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="btn-brand w-full text-lg" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="register">{{ __('Submit registration') }}</span>
                <span wire:loading wire:target="register">{{ __('Submitting…') }}</span>
            </button>
        </form>

        <p class="mt-8 text-center text-lg text-slate">
            {{ __('Already a member?') }}
            <a href="{{ route('login') }}" class="link">{{ __('Sign in') }}</a>
        </p>
    @endif
</div>
