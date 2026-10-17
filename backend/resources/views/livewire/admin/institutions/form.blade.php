@php
    $categories = ['secondary' => __('Secondary school'), 'college' => __('College'), 'university' => __('University')];
    $errorText = 'mt-2 font-semibold text-danger';
@endphp
<div class="max-w-3xl">
    <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm font-bold">
        <a wire:navigate href="{{ route('admin.institutions.index') }}" class="link">{{ __('Institutions') }}</a>
        @if ($institution)
            <span aria-hidden="true"> / </span>
            <a wire:navigate href="{{ route('admin.institutions.show', $institution) }}" class="link">{{ $institution->name }}</a>
        @endif
    </nav>
    <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ $institution ? __('Edit institution') : __('Register institution') }}</h1>
    @if ($institution)
        <p class="mt-2 text-lg text-slate">{{ __('Code') }} <span class="font-receipt font-semibold text-ink">{{ $institution->code }}</span>, {{ __('The code never changes.') }}</p>
    @else
        <p class="mt-2 text-lg text-slate">{{ __('A unique code is created when you save. The institution is active straight away.') }}</p>
    @endif

    <form wire:submit="save" class="mt-8 space-y-8 rounded-3xl border-2 border-line bg-white p-6 sm:p-8" novalidate>
        <fieldset class="space-y-5">
            <legend class="text-sm font-black text-slate">{{ __('Institution') }}</legend>

            <div>
                <label for="name" class="block font-extrabold">{{ __('Name') }}</label>
                <input wire:model.live.blur="name" id="name" type="text" autocomplete="off" @class(['field', 'field-error' => $errors->has('name')])>
                @error('name')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                @if ($duplicateWarning && ! $errors->has('name'))
                    <p class="mt-2 rounded-xl bg-brand-wash px-4 py-2 font-semibold" role="status">{{ $duplicateWarning }}</p>
                @endif
            </div>

            <div>
                <span class="block font-extrabold" id="type-label">{{ __('Category') }}</span>
                <div class="mt-1.5 grid grid-cols-3 gap-2" role="radiogroup" aria-labelledby="type-label">
                    @foreach ($categories as $value => $label)
                        <label @class(['flex min-h-12 cursor-pointer items-center justify-center rounded-xl border-2 px-2 text-center font-extrabold has-[:focus-visible]:outline has-[:focus-visible]:outline-3 has-[:focus-visible]:outline-ink', 'border-ink bg-brand' => $type === $value, 'border-line hover:border-ink' => $type !== $value])>
                            <input type="radio" wire:model.live="type" value="{{ $value }}" class="sr-only">{{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('type')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="region" class="block font-extrabold">{{ __('Region') }}</label>
                    <select wire:model.live="region" id="region" @class(['field', 'field-error' => $errors->has('region')])>
                        <option value="">{{ __('Choose a region') }}</option>
                        @foreach ($regions as $regionName)
                            <option value="{{ $regionName }}">{{ $regionName }}</option>
                        @endforeach
                    </select>
                    @error('region')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="district" class="block font-extrabold">{{ __('District') }}</label>
                    <input wire:model.live.blur="district" id="district" type="text" @class(['field', 'field-error' => $errors->has('district')])>
                    @error('district')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                </div>
            </div>
        </fieldset>

        <fieldset class="space-y-5">
            <legend class="text-sm font-black text-slate">{{ __('Contact person') }} <span class="font-semibold">({{ __('optional') }})</span></legend>
            <div>
                <label for="contact_name" class="block font-extrabold">{{ __('Name') }}</label>
                <input wire:model="contact_name" id="contact_name" type="text" class="field" placeholder="{{ __('For example the head of school') }}">
                @error('contact_name')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="contact_phone" class="block font-extrabold">{{ __('Mobile number') }}</label>
                    <input wire:model="contact_phone" id="contact_phone" type="tel" inputmode="tel" placeholder="0712 345 678" @class(['field', 'field-error' => $errors->has('contact_phone')])>
                    @error('contact_phone')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="contact_email" class="block font-extrabold">{{ __('Email') }}</label>
                    <input wire:model="contact_email" id="contact_email" type="email" @class(['field', 'field-error' => $errors->has('contact_email')])>
                    @error('contact_email')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                </div>
            </div>
        </fieldset>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">{{ $institution ? __('Save changes') : __('Register institution') }}</button>
            <a href="{{ $institution ? route('admin.institutions.show', $institution) : route('admin.institutions.index') }}" class="btn hover:bg-mist">{{ __('Cancel') }}</a>
        </div>
    </form>
</div>
