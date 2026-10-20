@php
    $regions = array_values(config('tanzania.regions'));
    sort($regions);
    $errorText = 'mt-2 font-semibold text-danger';
    $methods = ['link' => __('Online application link'), 'email' => __('By email'), 'in_person' => __('In person or other instructions')];
    $workModes = ['on_site' => __('On site'), 'remote' => __('Remote'), 'hybrid' => __('Hybrid')];
@endphp
<div class="mx-auto max-w-4xl">
    <a wire:navigate href="{{ $opportunity ? route('admin.opportunities.show', $opportunity) : route('admin.opportunities.index') }}" class="link">← {{ $opportunity ? __('Back to the opportunity') : __('Opportunities') }}</a>
    <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ $opportunity ? __('Edit opportunity') : __('New opportunity') }}</h1>

    <form wire:submit="save" class="mt-8 space-y-6" novalidate>
        <section class="space-y-5 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
            <div>
                <label for="title" class="block font-extrabold">{{ __('Title') }}</label>
                <input wire:model="title" id="title" type="text" @class(['field', 'field-error' => $errors->has('title')])>
                @error('title')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="organization" class="block font-extrabold">{{ __('Organization') }}</label>
                    <input wire:model="organization" id="organization" type="text" @class(['field', 'field-error' => $errors->has('organization')])>
                    @error('organization')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="type" class="block font-extrabold">{{ __('Type') }}</label>
                    <select wire:model="type" id="type" class="field">
                        @foreach (\App\Support\OpportunityBoard::typeLabels() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div>
                <label for="description" class="block font-extrabold">{{ __('Description') }}</label>
                <textarea wire:model="description" id="description" rows="7" @class(['field', 'field-error' => $errors->has('description')])></textarea>
                @error('description')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="eligibility" class="block font-extrabold">{{ __('Who can apply') }} <span class="font-semibold text-slate">({{ __('requirements, documents, age') }})</span></label>
                <textarea wire:model="eligibility" id="eligibility" rows="3" class="field"></textarea>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="location" class="block font-extrabold">{{ __('Location') }}</label>
                    <input wire:model="location" id="location" type="text" class="field">
                </div>
                <div>
                    <label for="region" class="block font-extrabold">{{ __('Region') }}</label>
                    <select wire:model="region" id="region" class="field">
                        <option value="">{{ __('Any region') }}</option>
                        @foreach ($regions as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="work_mode" class="block font-extrabold">{{ __('Work mode') }}</label>
                    <select wire:model="work_mode" id="work_mode" class="field">
                        <option value="">—</option>
                        @foreach ($workModes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
            </div>
        </section>

        <section class="space-y-5 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
            <h2 class="text-xl font-black">{{ __('Deadline and how to apply') }}</h2>
            <div>
                <label for="deadline" class="block font-extrabold">{{ __('Application deadline') }} <span class="font-semibold text-slate">({{ __('it closes automatically after this day') }})</span></label>
                <input wire:model="deadline" id="deadline" type="date" @class(['field sm:max-w-xs', 'field-error' => $errors->has('deadline')])>
                @error('deadline')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
            </div>
            <fieldset>
                <legend class="font-extrabold">{{ __('Application channel') }}</legend>
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach ($methods as $value => $label)
                        <label class="flex items-center gap-2 rounded-xl border-2 border-line px-3 py-2 font-bold has-[:checked]:border-ink has-[:checked]:bg-brand-wash">
                            <input type="radio" wire:model.live="apply_method" value="{{ $value }}" class="size-4 accent-ink"> {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            @if ($apply_method === 'link')
                <div>
                    <label for="apply_url" class="block font-extrabold">{{ __('Application link') }}</label>
                    <input wire:model="apply_url" id="apply_url" type="url" placeholder="https://" @class(['field', 'field-error' => $errors->has('apply_url')])>
                    @error('apply_url')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                </div>
            @elseif ($apply_method === 'email')
                <div>
                    <label for="apply_email" class="block font-extrabold">{{ __('Email address for applications') }}</label>
                    <input wire:model="apply_email" id="apply_email" type="email" @class(['field', 'field-error' => $errors->has('apply_email')])>
                    @error('apply_email')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                </div>
            @endif
            <div>
                <label for="apply_instructions" class="block font-extrabold">{{ __('Application instructions') }} @if ($apply_method !== 'in_person')<span class="font-semibold text-slate">({{ __('optional') }})</span>@endif</label>
                <textarea wire:model="apply_instructions" id="apply_instructions" rows="3" @class(['field', 'field-error' => $errors->has('apply_instructions')])></textarea>
                @error('apply_instructions')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <p class="font-extrabold">{{ __('Advert or guide') }} <span class="font-semibold text-slate">({{ __('PDF or image, up to 5 MB') }})</span></p>
                @if ($opportunity?->attachment_name)<p class="mt-1 text-slate">{{ __('Current file') }}: {{ $opportunity->attachment_name }}</p>@endif
                <input type="file" wire:model="attachment" accept="application/pdf,image/jpeg,image/png" class="mt-2 block w-full rounded-xl border-2 border-dashed border-line bg-mist p-4 font-bold">
                @error('attachment')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="space-y-5 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
            <x-audience-fields :legend="__('Who sees it')" :help="__('Show it only to members who are eligible. Leave empty for all members.')" />
        </section>

        <section class="space-y-4 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
            @error('submit')<p class="rounded-xl bg-[#fef3f2] p-3 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn-ghost" wire:loading.attr="disabled">{{ $isLive ? __('Save changes') : __('Save draft') }}</button>
                @if (! $isLive)
                    <button type="button" wire:click="save(true)" class="btn-primary" wire:loading.attr="disabled">{{ __('Save and send for review') }}</button>
                @endif
            </div>
        </section>
    </form>
</div>
