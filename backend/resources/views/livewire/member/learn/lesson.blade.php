@php
    use App\Support\HtmlSanitizer;

    $types = ['article' => __('Article'), 'video' => __('Video'), 'infographic' => __('Infographic'), 'document' => __('Document')];
    $completed = $progress?->status === 'completed';
@endphp
<div class="mx-auto max-w-3xl">
    <nav class="flex flex-wrap gap-2 text-sm font-bold" aria-label="{{ __('Breadcrumb') }}">
        <a wire:navigate href="{{ route('member.learn') }}" class="link">{{ __('Learn') }}</a>
        @if ($module)<span aria-hidden="true">/</span><a wire:navigate href="{{ route('member.learn.module', $module) }}" class="link">{{ $module->title }}</a>@endif
    </nav>

    <h1 class="mt-4 text-3xl font-black leading-tight tracking-tight sm:text-4xl">{{ $lesson->title }}</h1>
    <p class="mt-2 flex flex-wrap gap-2 text-sm font-extrabold">
        <span class="rounded-full bg-brand px-2.5 py-0.5">{{ $types[$lesson->content_type] ?? '' }}</span>
        @if ($lesson->estimated_minutes)<span class="text-slate">{{ __(':minutes min', ['minutes' => $lesson->estimated_minutes]) }}</span>@endif
        <span class="text-slate">{{ __('Version :version', ['version' => $lesson->current_version]) }}</span>
    </p>

    @if ($updatedSinceCompleted)
        <p class="mt-4 rounded-xl bg-brand-wash p-4 font-bold">{{ __('This lesson was updated after you completed it. Read it again to stay up to date.') }}</p>
    @endif

    @if ($lesson->objectives)
        <div class="mt-6 rounded-2xl border-2 border-ink bg-brand-wash p-5">
            <p class="font-black">{{ __('In this lesson you will learn') }}</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach (preg_split('/\R/', $lesson->objectives) as $objective)
                    @if (trim($objective) !== '')<li>{{ $objective }}</li>@endif
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-8">@include('learn.partials.media')</div>

    <article class="prose prose-lg mt-8 max-w-none prose-headings:font-black prose-headings:text-ink prose-p:text-ink-soft prose-a:font-bold prose-a:text-ink prose-a:decoration-brand prose-a:decoration-[3px] prose-strong:text-ink prose-li:marker:text-ink">
        {!! HtmlSanitizer::clean($lesson->body_text) !!}
    </article>

    <div class="mt-12 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
        @if ($statusMessage)
            <p role="status" class="mb-4 rounded-xl bg-savanna px-4 py-3 font-extrabold text-white">{{ $statusMessage }}</p>
        @endif
        @if ($completed)
            <p class="flex items-center gap-3 text-xl font-black text-savanna">
                <svg viewBox="0 0 20 20" class="size-8 rounded-full bg-savanna p-1.5 text-white" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.58l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                {{ __('You completed this lesson') }}
            </p>
        @else
            <p class="text-lg font-bold">{{ __('Finished reading?') }}</p>
            <button type="button" wire:click="complete" class="btn-primary mt-3">{{ __('Mark as complete') }}</button>
        @endif

        <div class="mt-6 flex flex-wrap gap-3">
            @if ($quiz && $quiz->isAvailableTo(auth()->user()))
                <a wire:navigate href="{{ route('member.quizzes.show', $quiz) }}" class="btn-brand">{{ __('Take the quiz') }}</a>
            @endif
            @if ($next)
                <a wire:navigate href="{{ route('member.learn.lesson', $next) }}" class="btn-ghost">{{ __('Next lesson') }}: {{ \Illuminate\Support\Str::limit($next->title, 40) }}</a>
            @elseif ($module)
                <a wire:navigate href="{{ route('member.learn.module', $module) }}" class="btn-ghost">{{ __('Back to the module') }}</a>
            @else
                <a wire:navigate href="{{ route('member.learn') }}" class="btn-ghost">{{ __('More lessons') }}</a>
            @endif
        </div>
    </div>
</div>
