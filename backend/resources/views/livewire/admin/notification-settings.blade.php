@php use App\Support\NotificationCatalogue; @endphp
<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Notifications') }}</h1>
    <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Change the wording of each message and choose whether it goes by SMS, to the in-app inbox, or both. One SMS holds about 160 characters; longer messages cost more.') }}</p>
    @if (auth()->user()->hasControl('view_sms_log'))
        <a href="{{ route('admin.sms') }}" class="link mt-3 inline-block">{{ __('See SMS delivery') }}</a>
    @endif

    @if ($statusMessage)
        <p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>
    @endif

    <ul class="mt-8 space-y-4">
        @foreach ($notifications as $item)
            @php($class = $item['class'])
            <li class="rounded-2xl border-2 border-line bg-white p-5" wire:key="n-{{ $item['key'] }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-lg font-black">
                            {{ $item['label'] }}
                            @if ($item['template']?->body_sw || $item['template']?->body_en)<span class="ml-1 rounded-full bg-brand px-2 py-0.5 text-xs font-extrabold">{{ __('Custom wording') }}</span>@endif
                        </p>
                        <p class="mt-1 flex flex-wrap gap-2 text-sm font-extrabold">
                            @php($sms = $class::required() || ($item['template']?->sms_enabled ?? $class::smsByDefault()))
                            @php($inbox = ! $class::sensitive() && ($item['template']?->inbox_enabled ?? true))
                            <span @class(['rounded-full px-2.5 py-0.5', 'bg-ink text-brand' => $sms, 'bg-mist text-slate line-through' => ! $sms])>SMS</span>
                            <span @class(['rounded-full px-2.5 py-0.5', 'bg-ink text-brand' => $inbox, 'bg-mist text-slate line-through' => ! $inbox])>{{ __('Inbox') }}</span>
                            @if ($class::required())<span class="text-slate">{{ __('Security message: SMS always on') }}</span>@endif
                        </p>
                        @if ($editing !== $item['key'])
                            <p class="mt-3 rounded-xl bg-mist p-3 text-slate">{{ NotificationCatalogue::preview($item['text']) }}</p>
                        @endif
                    </div>
                    @if ($editing !== $item['key'])
                        <button type="button" wire:click="edit('{{ $item['key'] }}')" class="btn-ghost py-2">{{ __('Edit') }}</button>
                    @endif
                </div>

                @if ($editing === $item['key'])
                    <form wire:submit="save" class="mt-4 space-y-4">
                        <p class="text-sm text-slate">{{ __('Placeholders you can use:') }} @foreach ($class::placeholders() as $placeholder)<code class="mx-0.5 rounded bg-mist px-1.5 py-0.5 font-receipt">:{{ $placeholder }}</code>@endforeach</p>
                        @foreach (['bodySw' => 'Kiswahili', 'bodyEn' => 'English'] as $field => $language)
                            <div x-data>
                                <label for="{{ $field }}" class="block font-extrabold">{{ $language }}</label>
                                <textarea wire:model="{{ $field }}" id="{{ $field }}" rows="3" @class(['field', 'field-error' => $errors->has($field)])></textarea>
                                <p class="mt-1 text-sm text-slate"><span x-text="($wire.{{ $field }} ?? '').length"></span> {{ __('characters') }}</p>
                                @error($field)<p class="mt-1 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                        <div class="flex flex-wrap gap-4">
                            <label @class(['flex items-center gap-2 font-bold', 'opacity-60' => $class::required()])>
                                <input type="checkbox" wire:model="smsEnabled" class="size-4 accent-ink" @disabled($class::required())> {{ __('Send by SMS') }}
                            </label>
                            @unless ($class::sensitive())
                                <label class="flex items-center gap-2 font-bold">
                                    <input type="checkbox" wire:model="inboxEnabled" class="size-4 accent-ink"> {{ __('Show in the inbox') }}
                                </label>
                            @endunless
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="btn-primary py-2">{{ __('Save') }}</button>
                            <button type="button" wire:click="cancel" class="btn-ghost py-2">{{ __('Cancel') }}</button>
                            @if ($item['template'])
                                <button type="button" wire:click="restoreDefaults('{{ $item['key'] }}')" wire:confirm="{{ __('Restore the default wording and channels?') }}" class="link ml-auto">{{ __('Restore defaults') }}</button>
                            @endif
                        </div>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>
</div>
