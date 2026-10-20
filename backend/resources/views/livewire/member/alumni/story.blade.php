<div class="mx-auto max-w-3xl">
    <a wire:navigate href="{{ route('member.alumni') }}" class="link">← {{ __('Alumni') }}</a>
    <article class="mt-4 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
        <p class="text-sm font-extrabold text-slate">{{ $story->kind === 'career_tip' ? __('Career tip') : __('Alumni story') }}</p>
        <h1 class="mt-1 text-3xl font-black leading-tight">{{ $story->title }}</h1>
        <p class="mt-2 font-bold text-ink-soft">{{ $story->author?->publicName() }}@if ($story->author?->institution) · {{ $story->author->institution->name }}@endif</p>
        <div class="mt-6 max-w-[65ch] whitespace-pre-line text-lg leading-relaxed">{{ $story->body }}</div>
        <p class="mt-6 flex flex-wrap items-center gap-4 text-sm text-slate">
            <span>{{ $story->published_at->translatedFormat('j M Y') }}</span>
            @if ($story->user_id !== auth()->id())<livewire:safety.report-button type="alumni_story" :subject-id="$story->story_id" />@endif
        </p>
    </article>
</div>
