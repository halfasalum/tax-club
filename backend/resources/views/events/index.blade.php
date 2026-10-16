<x-layouts.public
    :title="__('Tax Club events')"
    :description="__('Upcoming Tax Club events, competitions and campaigns organised by TRA for students across Tanzania.')"
    :noindex="$events->currentPage() > 1"
>
    <div class="mx-auto max-w-6xl px-4 pt-14 sm:px-6">
        <h1 class="text-4xl font-extrabold tracking-tight">{{ __('Tax Club events') }}</h1>
        <p class="mt-3 max-w-[60ch] text-lg text-slate">{{ __('Competitions, school visits and campaigns. Members register for events after signing in.') }}</p>

        @if ($events->isEmpty())
            <p class="mt-12 max-w-[60ch] text-slate">{{ __('No events are scheduled yet. Check back soon or join to be notified.') }}</p>
        @else
            <div class="mt-10 divide-y divide-line border-y border-line">
                @foreach ($events as $event)
                    @include('events.partials.row', ['event' => $event])
                @endforeach
            </div>
            <div class="mt-8">{{ $events->links() }}</div>
        @endif
    </div>
</x-layouts.public>
