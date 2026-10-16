<x-layouts.public
    :title="__('Tax Club events')"
    :description="__('Upcoming Tax Club events, competitions and campaigns organised by TRA for students across Tanzania.')"
    :noindex="$events->currentPage() > 1"
>
    <section class="sun-hatch border-b-4 border-ink">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16">
            <h1 class="text-4xl font-black tracking-tight sm:text-5xl">{{ __('Tax Club events') }}</h1>
            <p class="mt-3 max-w-[60ch] text-lg font-semibold text-ink-soft">{{ __('Competitions, school visits and campaigns. Members register for events after signing in.') }}</p>
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 pt-12 sm:px-6">
        @if ($events->isEmpty())
            <p class="max-w-[60ch] rounded-2xl bg-mist p-8 text-lg text-slate">{{ __('No events are scheduled yet. Check back soon or join to be notified.') }}</p>
        @else
            <div class="grid gap-4">
                @foreach ($events as $event)
                    @include('events.partials.row', ['event' => $event])
                @endforeach
            </div>
            <div class="mt-10">{{ $events->links() }}</div>
        @endif
    </div>
</x-layouts.public>
