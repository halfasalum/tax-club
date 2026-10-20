@php
    use App\Support\HtmlSanitizer;
    use App\Support\Locales;

    $body = HtmlSanitizer::clean($lesson->body_text);
    $description = $lesson->summary ?: \Illuminate\Support\Str::limit(trim(strip_tags($body)), 160);
@endphp
<x-layouts.public
    :title="$lesson->title"
    :description="$description"
    type="article"
    :json-ld="[[
        '@type' => 'Article',
        'headline' => $lesson->title,
        'description' => $description,
        'datePublished' => $lesson->published_at?->toIso8601String(),
        'inLanguage' => app()->getLocale(),
        'mainEntityOfPage' => url()->current(),
        'publisher' => ['@id' => url('/').'#organization'],
        'isAccessibleForFree' => true,
    ], [
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => __('Learn'), 'item' => Locales::route('learn.index')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $lesson->title, 'item' => url()->current()],
        ],
    ]]"
>
    <header class="sun-hatch border-b-4 border-ink">
        <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
            <nav aria-label="{{ __('Breadcrumb') }}" class="flex flex-wrap items-center gap-2 text-sm font-bold">
                <a href="{{ Locales::route('learn.index') }}" class="link">{{ __('Learn') }}</a>
                <span aria-hidden="true">/</span>
                <span class="rounded-full bg-ink px-3 py-0.5 text-brand">{{ __('content_type.'.$lesson->content_type) }}</span>
            </nav>
            <h1 class="mt-5 text-[2.25rem] font-black leading-[1.08] tracking-tight sm:text-5xl">{{ $lesson->title }}</h1>
            @if ($lesson->summary)
                <p class="mt-4 text-xl font-semibold leading-relaxed text-ink-soft">{{ $lesson->summary }}</p>
            @endif
            @if ($lesson->published_at)
                <p class="mt-5 text-sm font-bold text-ink-soft">{{ __('Published') }} <time datetime="{{ $lesson->published_at->toDateString() }}">{{ $lesson->published_at->translatedFormat('j F Y') }}</time></p>
            @endif
        </div>
    </header>

    <article class="mx-auto max-w-3xl px-4 pt-10 sm:px-6">

        @include('learn.partials.media')

        <div class="prose prose-lg mt-8 max-w-none prose-headings:font-black prose-headings:text-ink prose-p:text-ink-soft prose-a:font-bold prose-a:text-ink prose-a:decoration-brand prose-a:decoration-[3px] prose-strong:text-ink prose-blockquote:border-l-brand prose-li:marker:text-ink">
            {!! $body !!}
        </div>

        <aside class="mt-14 rounded-3xl bg-ink p-8 text-white">
            <p class="text-2xl font-black text-brand">{{ __('Test what you learned') }}</p>
            <p class="mt-2 text-lg text-white/80">{{ __('Members can take quizzes, earn points and get certificates.') }}</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="btn-brand">{{ __('Join Tax Club') }}</a>
                <a href="{{ route('login') }}" class="btn border-2 border-white/40 text-white hover:border-white">{{ __('Sign in') }}</a>
            </div>
        </aside>
    </article>
</x-layouts.public>
