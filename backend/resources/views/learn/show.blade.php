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
    <article class="mx-auto max-w-3xl px-4 pt-12 sm:px-6">
        <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm text-slate">
            <a href="{{ Locales::route('learn.index') }}" class="hover:text-ink">{{ __('Learn') }}</a>
            <span aria-hidden="true"> / </span>
            <span>{{ __('content_type.'.$lesson->content_type) }}</span>
        </nav>

        <h1 class="mt-4 text-[2.25rem] font-extrabold leading-tight tracking-tight sm:text-5xl">{{ $lesson->title }}</h1>
        @if ($lesson->published_at)
            <p class="mt-4 text-slate">{{ __('Published') }} <time datetime="{{ $lesson->published_at->toDateString() }}">{{ $lesson->published_at->translatedFormat('j F Y') }}</time></p>
        @endif

        @if ($lesson->content_type === 'video' && $lesson->file_url)
            <video class="mt-8 w-full rounded-lg bg-ink" controls preload="metadata" src="{{ $lesson->file_url }}"></video>
        @elseif ($lesson->content_type === 'infographic' && $lesson->file_url)
            <img class="mt-8 w-full rounded-lg border border-line" src="{{ $lesson->file_url }}" alt="{{ $lesson->title }}" loading="lazy">
        @endif

        <div class="prose prose-lg mt-8 max-w-none prose-headings:font-extrabold prose-headings:text-ink prose-a:text-ink prose-a:decoration-flag prose-strong:text-ink">
            {!! $body !!}
        </div>

        <aside class="mt-14 rounded-lg border border-line bg-white p-6">
            <p class="font-bold">{{ __('Test what you learned') }}</p>
            <p class="mt-1 text-slate">{{ __('Members can take quizzes, earn points and get certificates.') }}</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="rounded-md bg-flag px-5 py-2.5 font-bold text-ink hover:bg-[#ffc81f]">{{ __('Join Tax Club') }}</a>
                <a href="{{ route('login') }}" class="rounded-md px-5 py-2.5 font-semibold text-ink hover:bg-paper">{{ __('Sign in') }}</a>
            </div>
        </aside>
    </article>
</x-layouts.public>
