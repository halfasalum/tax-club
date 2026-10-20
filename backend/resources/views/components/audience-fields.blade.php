@props(['legend' => null, 'help' => null])
@php
    $categories = \App\Support\Audience::categoryLabels();
    $regions = array_values(config('tanzania.regions'));
    sort($regions);
@endphp
<fieldset {{ $attributes->class(['space-y-4']) }}>
    <legend class="text-xl font-black">{{ $legend ?? __('Who it is for') }}</legend>
    <p class="text-slate">{{ $help ?? __('Leave everything empty to reach all members.') }}</p>
    <div>
        <p class="font-extrabold">{{ __('Membership categories') }}</p>
        <div class="mt-2 flex flex-wrap gap-3">
            @foreach ($categories as $value => $label)
                <label class="flex items-center gap-2 rounded-xl border-2 border-line px-3 py-2 font-bold">
                    <input type="checkbox" wire:model.live="audienceCategories" value="{{ $value }}" class="size-4 accent-ink"> {{ $label }}
                </label>
            @endforeach
        </div>
    </div>
    <details class="rounded-xl border-2 border-line p-3" @if ($this->audienceRegions) open @endif>
        <summary class="cursor-pointer font-extrabold">{{ __('Regions') }} @if ($this->audienceRegions)<span class="text-slate">({{ count($this->audienceRegions) }})</span>@endif</summary>
        <div class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 sm:grid-cols-3">
            @foreach ($regions as $region)
                <label class="flex items-center gap-2 font-semibold"><input type="checkbox" wire:model.live="audienceRegions" value="{{ $region }}" class="size-4 accent-ink"> {{ $region }}</label>
            @endforeach
        </div>
    </details>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="audienceAge" class="block font-extrabold">{{ __('Age') }}</label>
            <select wire:model.live="audienceAge" id="audienceAge" class="field">
                <option value="any">{{ __('Any age') }}</option>
                <option value="adults">{{ __('18 and over only') }}</option>
                <option value="minors">{{ __('Under 18 only') }}</option>
            </select>
        </div>
        <div>
            <label for="audienceInstitutions" class="block font-extrabold">{{ __('Only these institutions') }} <span class="font-semibold text-slate">({{ __('codes, optional') }})</span></label>
            <input wire:model.blur="audienceInstitutions" id="audienceInstitutions" type="text" placeholder="DSM-S-0042, MWZ-U-0003" @class(['field font-receipt', 'field-error' => $errors->has('audienceInstitutions')])>
            @error('audienceInstitutions')<p class="mt-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
        </div>
    </div>
</fieldset>
