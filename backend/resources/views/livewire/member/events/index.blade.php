@php
    use App\Support\EventLabels;

    $types = EventLabels::types();
    $statusLabels = EventLabels::registrationStatuses();
@endphp
<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Events') }}</h1>
    <p class="mt-1 text-lg text-slate">{{ __('Workshops, competitions and campaigns for Tax Club members. Bring your membership card to check in.') }}</p>

    <div class="mt-6 flex flex-wrap items-center gap-3">
        <div class="flex gap-1 rounded-2xl bg-white p-1.5 shadow-sm" role="tablist" aria-label="{{ __('Events') }}">
            @foreach (['upcoming' => __('Upcoming'), 'mine' => __('My events')] as $value => $label)
                <button type="button" role="tab" wire:click="$set('tab', '{{ $value }}')" aria-selected="{{ $tab === $value ? 'true' : 'false' }}"
                        @class(['rounded-xl px-4 py-2 font-extrabold', 'bg-ink text-brand' => $tab === $value, 'text-slate hover:bg-brand-wash' => $tab !== $value])>{{ $label }}</button>
            @endforeach
        </div>
        @if ($tab === 'upcoming')
            <label for="type" class="sr-only">{{ __('Type') }}</label>
            <select wire:model.live="type" id="type" class="field mt-0 w-auto">
                <option value="">{{ __('All types') }}</option>
                @foreach ($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
        @endif
    </div>

    @php($list = $tab === 'mine' ? $mine : $upcoming)
    <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($list as $event)
            @php($registration = $registrations->get($event->event_id))
            <li wire:key="event-{{ $event->event_id }}">
                <a wire:navigate href="{{ route('member.events.show', $event->slug) }}" class="flex h-full flex-col overflow-hidden rounded-2xl border-2 border-line bg-white transition hover:border-ink">
                    <div class="flex items-center gap-4 bg-ink p-4 text-white">
                        <span class="grid size-14 shrink-0 place-items-center rounded-xl bg-brand text-center leading-none text-ink">
                            <span><span class="block text-xl font-black">{{ $event->starts_at?->format('j') }}</span><span class="block text-xs font-extrabold uppercase">{{ $event->starts_at?->translatedFormat('M') }}</span></span>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-extrabold text-brand">{{ $types[$event->type] ?? $event->type }}</span>
                            <span class="block text-sm text-white/80">{{ $event->starts_at?->translatedFormat('l, H:i') }}</span>
                        </span>
                    </div>
                    <div class="flex flex-1 flex-col p-5">
                        <span class="text-lg font-black leading-snug">{{ $event->title }}</span>
                        <span class="mt-1 text-sm text-slate">{{ $event->mode === 'online' ? __('Online') : $event->location }}</span>
                        <span class="mt-auto flex flex-wrap gap-2 pt-3 text-sm font-extrabold">
                            @if ($registration)
                                <span @class(['rounded-full px-2.5 py-0.5', 'bg-savanna text-white' => $registration->status !== 'waitlisted', 'bg-brand text-ink' => $registration->status === 'waitlisted'])>{{ $statusLabels[$registration->status] ?? '' }}</span>
                            @elseif ($event->publication_status === 'postponed')
                                <span class="rounded-full bg-[#fff7ed] px-2.5 py-0.5 text-[#9a3412]">{{ __('Postponed') }}</span>
                            @elseif ($event->capacity && $event->placesLeft() === 0)
                                <span class="rounded-full bg-mist px-2.5 py-0.5">{{ $event->waitlist_enabled ? __('Full · waitlist open') : __('Full') }}</span>
                            @elseif ($event->capacity)
                                <span class="rounded-full bg-mist px-2.5 py-0.5">{{ trans_choice(':count place left|:count places left', $event->placesLeft(), ['count' => $event->placesLeft()]) }}</span>
                            @endif
                        </span>
                    </div>
                </a>
            </li>
        @empty
            <li class="rounded-2xl bg-white p-8 text-center text-lg font-bold text-slate sm:col-span-2 lg:col-span-3">
                {{ $tab === 'mine' ? __('You have not registered for any events yet.') : __('No upcoming events right now. Check back soon.') }}
            </li>
        @endforelse
    </ul>
</div>
