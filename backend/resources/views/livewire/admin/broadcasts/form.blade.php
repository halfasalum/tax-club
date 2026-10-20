@php
    $errorText = 'mt-2 font-semibold text-danger';
    $max = \App\Support\Broadcasts::MAX_LENGTH;
@endphp
<div class="mx-auto max-w-4xl">
    <a wire:navigate href="{{ $broadcast ? route('admin.broadcasts.show', $broadcast) : route('admin.broadcasts.index') }}" class="link">← {{ $broadcast ? __('Back to the broadcast') : __('Broadcasts') }}</a>
    <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ $broadcast ? __('Edit broadcast') : __('New broadcast') }}</h1>

    <form wire:submit="save" class="mt-8 space-y-6" novalidate>
        <section class="space-y-5 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
            <div>
                <label for="title" class="block font-extrabold">{{ __('Title') }} <span class="font-semibold text-slate">({{ __('for officers only') }})</span></label>
                <input wire:model="title" id="title" type="text" @class(['field', 'field-error' => $errors->has('title')])>
                @error('title')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="message_sw" class="block font-extrabold">{{ __('Message in Kiswahili') }}</label>
                <textarea wire:model.live.debounce.500ms="message_sw" id="message_sw" rows="4" maxlength="{{ $max }}" @class(['field', 'field-error' => $errors->has('message_sw')])></textarea>
                <p class="mt-1 text-sm text-slate">{{ mb_strlen($message_sw) }} / {{ $max }} · {{ trans_choice(':count SMS part|:count SMS parts', $smsParts, ['count' => $smsParts]) }}</p>
                @error('message_sw')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="message_en" class="block font-extrabold">{{ __('Message in English') }} <span class="font-semibold text-slate">({{ __('optional, for members who chose English') }})</span></label>
                <textarea wire:model.live.debounce.500ms="message_en" id="message_en" rows="4" maxlength="{{ $max }}" class="field"></textarea>
            </div>
            <div>
                <label for="link_url" class="block font-extrabold">{{ __('Link in the inbox') }} <span class="font-semibold text-slate">({{ __('optional') }})</span></label>
                <input wire:model="link_url" id="link_url" type="url" placeholder="https://" @class(['field', 'field-error' => $errors->has('link_url')])>
                @error('link_url')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
            </div>
            <fieldset>
                <legend class="font-extrabold">{{ __('Send by') }}</legend>
                <div class="mt-2 flex flex-wrap gap-3">
                    <label class="flex items-center gap-2 rounded-xl border-2 border-line px-3 py-2 font-bold"><input type="checkbox" wire:model.live="send_sms" class="size-4 accent-ink"> SMS</label>
                    <label class="flex items-center gap-2 rounded-xl border-2 border-line px-3 py-2 font-bold"><input type="checkbox" wire:model.live="send_inbox" class="size-4 accent-ink"> {{ __('App inbox') }}</label>
                </div>
            </fieldset>
        </section>

        <section class="space-y-5 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
            <x-audience-fields :legend="__('Who receives it')" :help="__('Only active members receive broadcasts. Members who switched announcements off are skipped.')" />
            <p class="rounded-2xl bg-ink p-4 text-white" aria-live="polite">
                <span class="text-2xl font-black text-brand tabular-nums">{{ number_format($recipients) }}</span> {{ __('members match') }}
                @if ($send_sms)
                    <span class="block text-sm text-white/80">{{ __('About :count SMS (:parts per member). Institution codes are counted when you save.', ['count' => number_format($recipients * $smsParts), 'parts' => $smsParts]) }}</span>
                @endif
            </p>
        </section>

        <section class="space-y-4 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
            @error('submit')<p class="rounded-xl bg-[#fef3f2] p-3 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn-ghost" wire:loading.attr="disabled">{{ __('Save draft') }}</button>
                <button type="button" wire:click="save(true)" class="btn-primary" wire:loading.attr="disabled">{{ __('Save and send for approval') }}</button>
            </div>
        </section>
    </form>
</div>
