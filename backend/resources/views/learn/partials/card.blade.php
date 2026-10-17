@php use App\Support\Locales; @endphp
<article class="group relative flex flex-col rounded-2xl border-2 border-line bg-white p-6 transition hover:-translate-y-0.5 hover:border-ink hover:shadow-[6px_6px_0_var(--color-brand)]">
    <p class="flex items-center gap-3 text-sm">
        <span class="rounded-full bg-brand px-3 py-1 font-extrabold text-ink">{{ __('content_type.'.$lesson->content_type) }}</span>
        @if ($lesson->published_at)
            <time datetime="{{ $lesson->published_at->toDateString() }}" class="font-semibold text-slate">{{ $lesson->published_at->translatedFormat('j M Y') }}</time>
        @endif
    </p>
    <h3 class="mt-4 text-xl font-black leading-snug">
        <a href="{{ Locales::route('learn.show', ['slug' => $lesson->slug]) }}" class="after:absolute after:inset-0 after:rounded-2xl">{{ $lesson->title }}</a>
    </h3>
    @if ($lesson->summary)
        <p class="mt-2 flex-1 leading-relaxed text-slate">{{ $lesson->summary }}</p>
    @endif
    <p class="mt-5 font-extrabold text-ink underline decoration-brand decoration-[3px] underline-offset-4 group-hover:decoration-ink" aria-hidden="true">{{ __('Read lesson') }}</p>
</article>
