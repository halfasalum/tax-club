<select wire:model.live="language" {{ $attributes->class(['field mt-0']) }} aria-label="{{ __('Language') }}">
    <option value="sw">Kiswahili</option>
    <option value="en">English</option>
    <option value="all">{{ __('All languages') }}</option>
</select>
