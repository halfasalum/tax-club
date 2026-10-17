{{-- Lesson media. Videos load only when tapped, to save mobile data (LRN-REQ05). --}}
@php
    $embed = $lesson->embedUrl();
    $file = $lesson->mediaUrl() ?? $lesson->file_url;
@endphp
@if ($lesson->content_type === 'video' && $embed)
    <div x-data="{ playing: false }" class="relative aspect-video overflow-hidden rounded-2xl bg-ink">
        <template x-if="playing">
            <iframe class="absolute inset-0 size-full" :src="'{{ $embed }}?autoplay=1'" title="{{ $lesson->title }}" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen loading="lazy"></iframe>
        </template>
        <button type="button" x-show="! playing" x-on:click="playing = true" class="absolute inset-0 grid place-items-center text-white">
            <span class="grid size-20 place-items-center rounded-full bg-brand text-ink shadow-lg"><svg viewBox="0 0 24 24" class="ml-1 size-10" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg></span>
            <span class="absolute bottom-4 left-0 right-0 text-center font-bold">{{ __('Play video (uses mobile data)') }}</span>
        </button>
    </div>
@elseif ($lesson->content_type === 'video' && $file)
    <video class="w-full rounded-2xl bg-ink" controls preload="none" src="{{ $file }}"></video>
@elseif ($lesson->content_type === 'infographic' && $file)
    <a href="{{ $file }}" target="_blank" rel="noopener"><img class="w-full rounded-2xl border-2 border-line" src="{{ $file }}" alt="{{ $lesson->title }}" loading="lazy"></a>
@elseif ($lesson->content_type === 'document' && $file)
    <a href="{{ $file }}" target="_blank" rel="noopener" class="flex items-center gap-4 rounded-2xl border-2 border-ink bg-brand-wash p-5 font-extrabold hover:bg-brand">
        <svg viewBox="0 0 24 24" class="size-10 shrink-0" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0-3-3m3 3 3-3M6 20h12a2 2 0 0 0 2-2V8l-5-5H6a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>
        <span>{{ __('Open the document (PDF)') }}</span>
    </a>
@endif
