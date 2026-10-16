@php
    use App\Support\Locales;

    $description = \Illuminate\Support\Str::limit(trim(strip_tags((string) $event->description)), 160);
    $schema = array_filter([
        '@type' => 'Event',
        'name' => $event->title,
        'description' => $description,
        'startDate' => $event->starts_at?->toIso8601String(),
        'endDate' => $event->ends_at?->toIso8601String(),
        'eventStatus' => 'https://schema.org/EventScheduled',
        'location' => $event->location ? ['@type' => 'Place', 'name' => $event->location, 'address' => $event->location] : null,
        'organizer' => ['@id' => url('/').'#organization'],
        'isAccessibleForFree' => true,
    ]);
@endphp
<x-layouts.public :title="$event->title" :description="$description" :json-ld="[$schema]">
    <article class="mx-auto max-w-3xl px-4 pt-12 sm:px-6">
        <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm text-slate">
            <a href="{{ Locales::route('events.index') }}" class="hover:text-ink">{{ __('Events') }}</a>
        </nav>
        <h1 class="mt-4 text-[2.25rem] font-extrabold leading-tight tracking-tight sm:text-5xl">{{ $event->title }}</h1>

        <dl class="mt-8 grid gap-6 border-y border-line py-6 sm:grid-cols-2">
            @if ($event->starts_at)
                <div>
                    <dt class="text-sm font-semibold text-slate">{{ __('When') }}</dt>
                    <dd class="mt-1 text-lg font-semibold">
                        <time datetime="{{ $event->starts_at->toIso8601String() }}">{{ $event->starts_at->translatedFormat('l, j F Y, H:i') }}</time>
                        @if ($event->ends_at)
                            – <time datetime="{{ $event->ends_at->toIso8601String() }}">{{ $event->ends_at->isSameDay($event->starts_at) ? $event->ends_at->format('H:i') : $event->ends_at->translatedFormat('j F Y, H:i') }}</time>
                        @endif
                    </dd>
                </div>
            @endif
            @if ($event->location)
                <div>
                    <dt class="text-sm font-semibold text-slate">{{ __('Where') }}</dt>
                    <dd class="mt-1 text-lg font-semibold">{{ $event->location }}</dd>
                </div>
            @endif
        </dl>

        @if ($event->description)
            <div class="mt-8 max-w-[65ch] whitespace-pre-line text-lg leading-relaxed">{{ $event->description }}</div>
        @endif

        <aside class="mt-12 rounded-lg border border-line bg-white p-6">
            <p class="font-bold">{{ __('Want to take part?') }}</p>
            <p class="mt-1 text-slate">{{ __('Sign in to register for this event. New students can join Tax Club for free.') }}</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('login') }}" class="rounded-md bg-ink px-5 py-2.5 font-semibold text-white hover:bg-ink-soft">{{ __('Sign in') }}</a>
                <a href="{{ route('register') }}" class="rounded-md bg-flag px-5 py-2.5 font-bold text-ink hover:bg-[#ffc81f]">{{ __('Join Tax Club') }}</a>
            </div>
        </aside>
    </article>
</x-layouts.public>
