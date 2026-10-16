@php use App\Support\Locales; @endphp
<article class="grid gap-2 py-6 md:grid-cols-[9rem_1fr] md:gap-8">
    <p class="text-sm text-slate">
        <span class="font-semibold text-ink">{{ __('content_type.'.$lesson->content_type) }}</span><br class="hidden md:block">
        <time datetime="{{ $lesson->published_at?->toDateString() }}" class="md:mt-1 md:inline-block">{{ $lesson->published_at?->translatedFormat('j M Y') }}</time>
    </p>
    <div>
        <h3 class="text-xl font-bold leading-snug">
            <a href="{{ Locales::route('learn.show', ['slug' => $lesson->slug]) }}" class="hover:underline hover:decoration-flag hover:decoration-2 hover:underline-offset-4">{{ $lesson->title }}</a>
        </h3>
        @if ($lesson->summary)
            <p class="mt-2 max-w-[65ch] leading-relaxed text-slate">{{ $lesson->summary }}</p>
        @endif
    </div>
</article>
