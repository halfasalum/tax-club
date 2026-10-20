@php
    use App\Support\EventLabels;

    $regions = array_values(config('tanzania.regions'));
    sort($regions);
    $errorText = 'mt-2 font-semibold text-danger';
@endphp
<div class="mx-auto max-w-4xl">
    <a wire:navigate href="{{ $event ? route('admin.events.show', $event) : route('admin.events.index') }}" class="link">← {{ $event ? __('Back to the event') : __('Events') }}</a>
    <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ $event ? __('Edit event') : __('New event') }}</h1>
    @if ($isLive)
        <p class="mt-3 rounded-xl bg-brand-wash p-4 font-bold">{{ __('This event is live. Members who registered are told when the date, time or place changes.') }}</p>
    @endif

    <form wire:submit="save" class="mt-8 space-y-6" novalidate>
        <section class="space-y-5 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
            <h2 class="text-xl font-black">{{ __('About the event') }}</h2>
            <div>
                <label for="title" class="block font-extrabold">{{ __('Title') }}</label>
                <input wire:model="title" id="title" type="text" @class(['field', 'field-error' => $errors->has('title')])>
                @error('title')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="type" class="block font-extrabold">{{ __('Type') }}</label>
                    <select wire:model="type" id="type" class="field">
                        @foreach (EventLabels::types() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="organizer" class="block font-extrabold">{{ __('Organizer') }}</label>
                    <input wire:model="organizer" id="organizer" type="text" placeholder="{{ __('For example: TRA Mwanza Region') }}" class="field">
                </div>
            </div>
            <div>
                <label for="description" class="block font-extrabold">{{ __('Description') }}</label>
                <textarea wire:model="description" id="description" rows="6" @class(['field', 'field-error' => $errors->has('description')])></textarea>
                @error('description')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="objective" class="block font-extrabold">{{ __('Objective') }} <span class="font-semibold text-slate">({{ __('what participants will gain') }})</span></label>
                <textarea wire:model="objective" id="objective" rows="2" class="field"></textarea>
            </div>
        </section>

        <section class="space-y-5 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
            <h2 class="text-xl font-black">{{ __('When and where') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="starts_at" class="block font-extrabold">{{ __('Starts') }}</label>
                    <input wire:model="starts_at" id="starts_at" type="datetime-local" @class(['field', 'field-error' => $errors->has('starts_at')])>
                    @error('starts_at')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="ends_at" class="block font-extrabold">{{ __('Ends') }}</label>
                    <input wire:model="ends_at" id="ends_at" type="datetime-local" @class(['field', 'field-error' => $errors->has('ends_at')])>
                    @error('ends_at')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                </div>
            </div>
            <fieldset>
                <legend class="font-extrabold">{{ __('How people take part') }}</legend>
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach (EventLabels::modes() as $value => $label)
                        <label class="flex items-center gap-2 rounded-xl border-2 border-line px-3 py-2 font-bold has-[:checked]:border-ink has-[:checked]:bg-brand-wash">
                            <input type="radio" wire:model.live="mode" value="{{ $value }}" class="size-4 accent-ink"> {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            @if ($mode !== 'online')
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="location" class="block font-extrabold">{{ __('Venue') }}</label>
                        <input wire:model="location" id="location" type="text" @class(['field', 'field-error' => $errors->has('location')])>
                        @error('location')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="region" class="block font-extrabold">{{ __('Region') }}</label>
                        <select wire:model="region" id="region" class="field">
                            <option value="">{{ __('Choose a region') }}</option>
                            @foreach ($regions as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                        </select>
                    </div>
                </div>
            @endif
            @if ($mode !== 'in_person')
                <div>
                    <label for="online_url" class="block font-extrabold">{{ __('Online meeting link') }} <span class="font-semibold text-slate">({{ __('shown only to registered members') }})</span></label>
                    <input wire:model="online_url" id="online_url" type="url" placeholder="https://" @class(['field', 'field-error' => $errors->has('online_url')])>
                    @error('online_url')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                </div>
            @endif
        </section>

        <section class="space-y-5 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
            <h2 class="text-xl font-black">{{ __('Registration') }}</h2>
            <label class="flex items-start gap-3 rounded-xl bg-mist p-4">
                <input type="checkbox" wire:model.live="registration_required" class="mt-1 size-5 accent-ink">
                <span><span class="font-extrabold">{{ __('Members must register') }}</span><span class="block text-slate">{{ __('Untick for open campaigns where anyone can come along. Attendance can still be recorded.') }}</span></span>
            </label>
            @if ($registration_required)
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="capacity" class="block font-extrabold">{{ __('Places') }} <span class="font-semibold text-slate">({{ __('empty = no limit') }})</span></label>
                        <input wire:model="capacity" id="capacity" type="number" min="1" @class(['field', 'field-error' => $errors->has('capacity')])>
                        @error('capacity')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="registration_closes_at" class="block font-extrabold">{{ __('Registration closes') }} <span class="font-semibold text-slate">({{ __('empty = when it starts') }})</span></label>
                        <input wire:model="registration_closes_at" id="registration_closes_at" type="datetime-local" @class(['field', 'field-error' => $errors->has('registration_closes_at')])>
                        @error('registration_closes_at')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                    </div>
                </div>
                <label class="flex items-center gap-3 font-bold">
                    <input type="checkbox" wire:model="waitlist_enabled" class="size-5 accent-ink"> {{ __('When full, put new registrations on a waitlist and offer free places automatically') }}
                </label>
                <div>
                    <label for="registration_question" class="block font-extrabold">{{ __('Question for registrants') }} <span class="font-semibold text-slate">({{ __('optional') }})</span></label>
                    <input wire:model="registration_question" id="registration_question" type="text" placeholder="{{ __('For example: Which topic would you like us to cover?') }}" class="field">
                </div>
            @endif
        </section>

        <section class="space-y-5 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
            <x-audience-fields :help="__('Only these members see the event and can register. Leave empty for all members.')" />
            <label class="flex items-start gap-3 rounded-xl bg-mist p-4">
                <input type="checkbox" wire:model="is_public" class="mt-1 size-5 accent-ink">
                <span><span class="font-extrabold">{{ __('Also show on the public website') }}</span><span class="block text-slate">{{ __('Anyone can see the date and venue. Meeting links stay private.') }}</span></span>
            </label>
        </section>

        <section class="space-y-4 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
            @error('submit')<p class="rounded-xl bg-[#fef3f2] p-3 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn-ghost" wire:loading.attr="disabled">{{ $isLive ? __('Save changes') : __('Save draft') }}</button>
                @if (! $isLive)
                    <button type="button" wire:click="save(true)" class="btn-primary" wire:loading.attr="disabled">{{ __('Save and send for approval') }}</button>
                @endif
            </div>
        </section>
    </form>
</div>
