@php
    use App\Support\Locales;

    $description = \Illuminate\Support\Str::limit(strip_tags($question->public_answer), 160);
    $schema = [
        '@type' => 'FAQPage',
        'mainEntity' => [[
            '@type' => 'Question',
            'name' => $question->public_question,
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $question->public_answer],
        ]],
    ];
@endphp
<x-layouts.public :title="$question->public_question" :description="$description" :json-ld="[$schema]">
    <header class="sun-hatch border-b-4 border-ink">
        <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
            <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm font-bold"><a href="{{ Locales::route('answers.index') }}" class="link">{{ __('Questions and answers') }}</a></nav>
            <h1 class="mt-5 text-[2rem] font-black leading-[1.1] tracking-tight sm:text-4xl">{{ $question->public_question }}</h1>
            @if ($question->category)<p class="mt-3 font-bold text-ink-soft">{{ $question->category->name() }}</p>@endif
        </div>
    </header>
    <article class="mx-auto max-w-3xl px-4 pt-10 sm:px-6">
        <div class="max-w-[65ch] whitespace-pre-line text-lg leading-relaxed text-ink-soft">{{ $question->public_answer }}</div>
        <p class="mt-6 text-sm text-slate">{{ __('Answered by TRA · :date', ['date' => $question->published_at->translatedFormat('j M Y')]) }}</p>
        <aside class="mt-12 rounded-3xl bg-ink p-8 text-white">
            <p class="text-2xl font-black text-brand">{{ __('Have a tax question?') }}</p>
            <p class="mt-2 text-lg text-white/80">{{ __('Tax Club members can ask TRA experts directly.') }}</p>
            <a href="{{ route('login') }}" class="btn-brand mt-6">{{ __('Sign in to ask') }}</a>
        </aside>
    </article>
</x-layouts.public>
