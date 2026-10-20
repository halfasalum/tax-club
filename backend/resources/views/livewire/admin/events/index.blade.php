@php
    $views = ['upcoming' => __('Upcoming'), 'review' => __('Waiting for review'), 'drafts' => __('Drafts'), 'ready' => __('Ready to publish'), 'past' => __('Ended, not closed'), 'closed' => __('Closed and archived'), 'all' => __('All')];
    $types = \App\Support\EventLabels::types();
@endphp
<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Events and campaigns') }}</h1>
            <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Plan events, send them for approval, take registrations and check members in with their card.') }}</p>
        </div>
        @if ($canCreate)<a wire:navigate href="{{ route('admin.events.create') }}" class="btn-brand">{{ __('New event') }}</a>@endif
    </div>

    <div class="mt-6 flex flex-wrap gap-1 rounded-2xl bg-white p-1.5 shadow-sm" role="tablist" aria-label="{{ __('Status') }}">
        @foreach ($views as $value => $label)
            <button type="button" role="tab" wire:click="$set('view', '{{ $value }}')" aria-selected="{{ $view === $value ? 'true' : 'false' }}"
                    @class(['rounded-xl px-4 py-2 font-extrabold transition-colors', 'bg-ink text-brand' => $view === $value, 'text-slate hover:bg-brand-wash hover:text-ink' => $view !== $value])>
                {{ $label }}@if ($value === 'review' && $waiting > 0)<span class="ml-1 rounded-full bg-brand px-2 text-sm text-ink">{{ $waiting }}</span>@endif
            </button>
        @endforeach
    </div>
    <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search by title, code or venue') }}" class="field mt-5 md:max-w-md" aria-label="{{ __('Search by title, code or venue') }}">

    <div class="mt-6 overflow-x-auto rounded-2xl border-2 border-line bg-white" wire:loading.class="opacity-60">
        <table class="w-full min-w-[52rem] text-left">
            <thead class="border-b-2 border-line bg-mist text-sm">
                <tr>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Event') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('When') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Status') }}</th>
                    <th scope="col" class="px-4 py-3 text-right font-extrabold">{{ __('Registered') }}</th>
                    <th scope="col" class="px-4 py-3 text-right font-extrabold">{{ __('Attended') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($this->events as $event)
                    <tr wire:key="event-{{ $event->event_id }}" class="hover:bg-brand-wash/50">
                        <td class="px-4 py-3">
                            <a wire:navigate href="{{ route('admin.events.show', $event) }}" class="font-black hover:underline">{{ $event->title }}</a>
                            <span class="block text-sm text-slate"><span class="font-receipt">{{ $event->code }}</span> · {{ $types[$event->type] ?? $event->type }}@if ($event->location) · {{ $event->location }}@endif</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $event->starts_at?->translatedFormat('j M Y, H:i') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <x-editorial-status :status="$event->editorial_status" />
                            @if ($event->publication_status !== 'not_published')<x-editorial-status :status="$event->displayStatus()" />@endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ number_format($event->registered_count) }}@if ($event->capacity)<span class="text-slate"> / {{ number_format($event->capacity) }}</span>@endif
                            @if ($event->waitlisted_count)<span class="block text-sm text-slate">{{ __('+:count waiting', ['count' => $event->waitlisted_count]) }}</span>@endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($event->attended_count) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-lg font-bold text-slate">{{ __('No events match these filters.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $this->events->links() }}</div>
</div>
