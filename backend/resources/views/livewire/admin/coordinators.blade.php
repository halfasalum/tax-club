<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Coordinators') }}</h1>
    <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('TRA Tax Club coordinators look after every institution, registration and member in their regions.') }}</p>

    @if ($statusMessage)
        <div role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">
            <p>{{ $statusMessage }}</p>
            @if ($temporaryPassword)
                <p class="mt-2 inline-block select-all rounded-lg bg-white px-3 py-1 font-receipt text-lg text-ink">{{ $temporaryPassword }}</p>
            @endif
        </div>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_24rem]">
        <section aria-labelledby="current-heading" class="space-y-4">
            <h2 id="current-heading" class="text-2xl font-black">{{ __('Current coordinators') }}</h2>
            @forelse ($this->assignmentsByRegion as $regionName => $assignments)
                <div class="rounded-2xl border-2 border-line bg-white p-5" wire:key="region-{{ $regionName }}">
                    <h3 class="text-lg font-black">{{ $regionName }}</h3>
                    <ul class="mt-3 divide-y divide-line">
                        @foreach ($assignments as $assignment)
                            <li class="py-3" wire:key="assignment-{{ $assignment->id }}">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-extrabold">{{ $assignment->user?->full_name }}</p>
                                        <p class="text-sm text-slate">{{ $assignment->user?->phone }} · {{ __('since :date', ['date' => $assignment->starts_on->translatedFormat('j M Y')]) }}</p>
                                    </div>
                                    @if ($endingId !== $assignment->id)
                                        <button type="button" wire:click="startEnding({{ $assignment->id }})" class="btn-ghost py-1.5 text-sm">{{ __('End assignment') }}</button>
                                    @endif
                                </div>
                                @if ($endingId === $assignment->id)
                                    <form wire:submit="endAssignment" class="mt-3 space-y-2">
                                        <label for="endReason" class="block font-bold">{{ __('Reason for ending') }}</label>
                                        <textarea wire:model="endReason" id="endReason" rows="2" @class(['field', 'field-error' => $errors->has('endReason')])></textarea>
                                        @error('endReason')<p class="font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
                                        <div class="flex gap-2">
                                            <button type="submit" class="btn-primary py-2">{{ __('End assignment') }}</button>
                                            <button type="button" wire:click="$set('endingId', null)" class="btn-ghost py-2">{{ __('Cancel') }}</button>
                                        </div>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="rounded-2xl bg-white p-8 text-center text-lg font-bold text-slate">{{ __('No coordinators yet. Assign the first one with the form.') }}</p>
            @endforelse
        </section>

        <section aria-labelledby="assign-heading" class="self-start rounded-3xl border-2 border-line bg-white p-6">
            <h2 id="assign-heading" class="text-xl font-black">{{ __('Assign a coordinator') }}</h2>
            <form wire:submit="assign" class="mt-4 space-y-4" novalidate>
                <div>
                    <label for="region" class="block font-bold">{{ __('Region') }}</label>
                    <select wire:model="region" id="region" @class(['field', 'field-error' => $errors->has('region')])>
                        <option value="">{{ __('Choose a region') }}</option>
                        @foreach ($regions as $regionName)
                            <option value="{{ $regionName }}">{{ $regionName }}</option>
                        @endforeach
                    </select>
                    @error('region')<p class="mt-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="phone" class="block font-bold">{{ __('Mobile number') }}</label>
                    <input wire:model="phone" id="phone" type="tel" inputmode="tel" placeholder="0712 345 678" @class(['field', 'field-error' => $errors->has('phone')])>
                    @error('phone')<p class="mt-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="fullName" class="block font-bold">{{ __('Full name') }} <span class="font-semibold text-slate">({{ __('for a new account') }})</span></label>
                    <input wire:model="fullName" id="fullName" type="text" @class(['field', 'field-error' => $errors->has('fullName')])>
                    @error('fullName')<p class="mt-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="block font-bold">{{ __('Email') }} <span class="font-semibold text-slate">({{ __('optional') }})</span></label>
                    <input wire:model="email" id="email" type="email" class="field">
                </div>
                <div>
                    <label for="startsOn" class="block font-bold">{{ __('Starts on') }}</label>
                    <input wire:model="startsOn" id="startsOn" type="date" class="field">
                </div>
                <button type="submit" class="btn-brand w-full">{{ __('Assign coordinator') }}</button>
            </form>
        </section>
    </div>
</div>
