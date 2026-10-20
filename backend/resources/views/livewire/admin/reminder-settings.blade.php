@php
    $labels = [
        'event' => [__('Before an event'), __('Members who registered get a reminder with the time and place.'), __('hours before it starts')],
        'opportunity' => [__('Before an opportunity closes'), __('Members who saved it are reminded to apply.'), __('days before the deadline')],
        'quiz' => [__('Before a quiz closes'), __('Members who started the quiz but did not submit it are reminded.'), __('hours before it closes')],
    ];
@endphp
<div class="mx-auto max-w-3xl">
    <a wire:navigate href="{{ route('admin.broadcasts.index') }}" class="link">← {{ __('Broadcasts') }}</a>
    <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ __('Automatic reminders') }}</h1>
    <p class="mt-2 text-lg text-slate">{{ __('Reminders are checked every hour and each member gets each reminder once. Members can switch reminder types off in their profile.') }}</p>

    @if ($saved)<p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ __('Saved.') }}</p>@endif

    <form wire:submit="save" class="mt-6 space-y-4">
        @foreach ($labels as $kind => [$title, $help, $unit])
            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-black">{{ $title }}</h2>
                        <p class="text-slate">{{ $help }}</p>
                    </div>
                    <label class="flex items-center gap-2 font-bold"><input type="checkbox" wire:model="settings.{{ $kind }}.enabled" class="size-5 accent-ink"> {{ __('On') }}</label>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <label for="lead-{{ $kind }}" class="sr-only">{{ $unit }}</label>
                    <input wire:model="settings.{{ $kind }}.lead" id="lead-{{ $kind }}" type="number" min="1" class="field mt-0 w-24">
                    <span class="font-bold">{{ $unit }}</span>
                    <span class="ml-auto text-sm text-slate">{{ trans_choice(':count sent this week|:count sent this week', (int) ($sentThisWeek[$kind] ?? 0), ['count' => (int) ($sentThisWeek[$kind] ?? 0)]) }}</span>
                </div>
                @error("settings.{$kind}.lead")<p class="mt-2 font-semibold text-danger">{{ $message }}</p>@enderror
            </section>
        @endforeach
        <button type="submit" class="btn-primary">{{ __('Save') }}</button>
    </form>
</div>
