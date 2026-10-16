@php use App\Support\Locales; @endphp
<article class="group relative grid grid-cols-[4.75rem_1fr] items-center gap-5 rounded-2xl border-2 border-line bg-white p-4 transition hover:border-ink hover:shadow-[6px_6px_0_var(--color-brand)] sm:grid-cols-[5.5rem_1fr_auto] sm:gap-6 sm:p-5">
    @if ($event->starts_at)
        <time datetime="{{ $event->starts_at->toIso8601String() }}" class="flex flex-col items-center justify-center rounded-xl bg-ink py-3 leading-none text-white">
            <span class="text-sm font-extrabold uppercase text-brand">{{ $event->starts_at->translatedFormat('M') }}</span>
            <span class="mt-1 text-3xl font-black">{{ $event->starts_at->format('j') }}</span>
        </time>
    @else
        <span class="grid aspect-square place-items-center rounded-xl bg-mist" aria-hidden="true"></span>
    @endif
    <div class="min-w-0">
        <h3 class="text-lg font-black leading-snug sm:text-xl">
            <a href="{{ Locales::route('events.show', ['slug' => $event->slug]) }}" class="after:absolute after:inset-0 after:rounded-2xl">{{ $event->title }}</a>
        </h3>
        <p class="mt-1 font-semibold text-slate">
            @if ($event->starts_at){{ $event->starts_at->translatedFormat('l, j F Y · H:i') }}@endif
            @if ($event->location)<span class="block">{{ $event->location }}</span>@endif
        </p>
    </div>
    <span class="hidden rounded-xl bg-brand px-4 py-2 font-extrabold text-ink sm:inline-block" aria-hidden="true">{{ __('Details') }}</span>
</article>
