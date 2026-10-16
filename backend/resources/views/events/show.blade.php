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
    <header class="sun-hatch border-b-4 border-ink">
        <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
            <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm font-bold">
                <a href="{{ Locales::route('events.index') }}" class="link">{{ __('Events') }}</a>
            </nav>
            <h1 class="mt-5 text-[2.25rem] font-black leading-[1.08] tracking-tight sm:text-5xl">{{ $event->title }}</h1>
        </div>
    </header>

    <article class="mx-auto max-w-3xl px-4 pt-10 sm:px-6">
        <dl class="grid gap-4 sm:grid-cols-2">
            @if ($event->starts_at)
                <div class="rounded-2xl border-2 border-line p-5">
                    <dt class="text-sm font-extrabold text-slate">{{ __('When') }}</dt>
                    <dd class="mt-1 text-lg font-black">
                        <time datetime="{{ $event->starts_at->toIso8601String() }}">{{ $event->starts_at->translatedFormat('l, j F Y, H:i') }}</time>
                        @if ($event->ends_at)
                            – <time datetime="{{ $event->ends_at->toIso8601String() }}">{{ $event->ends_at->isSameDay($event->starts_at) ? $event->ends_at->format('H:i') : $event->ends_at->translatedFormat('j F Y, H:i') }}</time>
                        @endif
                    </dd>
                </div>
            @endif
            @if ($event->location)
                <div class="rounded-2xl border-2 border-line p-5">
                    <dt class="text-sm font-extrabold text-slate">{{ __('Where') }}</dt>
                    <dd class="mt-1 text-lg font-black">{{ $event->location }}</dd>
                </div>
            @endif
        </dl>

        @if ($event->description)
            <div class="mt-8 max-w-[65ch] whitespace-pre-line text-lg leading-relaxed text-ink-soft">{{ $event->description }}</div>
        @endif

        <aside class="mt-12 rounded-3xl bg-ink p-8 text-white">
            <p class="text-2xl font-black text-brand">{{ __('Want to take part?') }}</p>
            <p class="mt-2 text-lg text-white/80">{{ __('Sign in to register for this event. New students can join Tax Club for free.') }}</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('login') }}" class="btn-brand">{{ __('Sign in') }}</a>
                <a href="{{ route('register') }}" class="btn border-2 border-white/40 text-white hover:border-white">{{ __('Join Tax Club') }}</a>
            </div>
        </aside>
    </article>
</x-layouts.public>
