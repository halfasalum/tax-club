@php use App\Support\Locales; @endphp
<article class="grid grid-cols-[4.5rem_1fr] gap-5 py-6 md:grid-cols-[6rem_1fr] md:gap-8">
    @if ($event->starts_at)
        <time datetime="{{ $event->starts_at->toIso8601String() }}" class="flex flex-col items-center justify-center rounded-md border-2 border-ink bg-white py-2 leading-none">
            <span class="text-sm font-semibold text-slate">{{ $event->starts_at->translatedFormat('M') }}</span>
            <span class="mt-1 text-3xl font-extrabold">{{ $event->starts_at->format('j') }}</span>
        </time>
    @else
        <span></span>
    @endif
    <div>
        <h3 class="text-xl font-bold leading-snug">
            <a href="{{ Locales::route('events.show', ['slug' => $event->slug]) }}" class="hover:underline hover:decoration-flag hover:decoration-2 hover:underline-offset-4">{{ $event->title }}</a>
        </h3>
        <p class="mt-1 text-slate">
            @if ($event->starts_at){{ $event->starts_at->translatedFormat('l, j F Y, H:i') }}@endif
            @if ($event->location)<br>{{ $event->location }}@endif
        </p>
    </div>
</article>
