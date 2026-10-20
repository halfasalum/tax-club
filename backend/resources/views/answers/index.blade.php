@php use App\Support\Locales; @endphp
<x-layouts.public
    :title="__('Questions and answers')"
    :description="__('Tax questions from Tax Club members, answered by TRA experts.')"
    :noindex="$search !== '' || $answers->currentPage() > 1"
>
    <section class="sun-hatch border-b-4 border-ink">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16">
            <h1 class="text-4xl font-black tracking-tight sm:text-5xl">{{ __('Questions and answers') }}</h1>
            <p class="mt-3 max-w-[60ch] text-lg font-semibold text-ink-soft">{{ __('Real questions from Tax Club members, answered by TRA tax experts.') }}</p>
            <form method="get" action="{{ Locales::route('answers.index') }}" class="mt-8 flex max-w-2xl gap-2 rounded-2xl bg-white p-2 shadow-[6px_6px_0_var(--color-ink)]" role="search">
                <label for="q" class="sr-only">{{ __('Search answers') }}</label>
                <input id="q" name="q" type="search" value="{{ $search }}" placeholder="{{ __('Search answers, e.g. TIN or VAT') }}" class="min-h-12 w-full rounded-xl px-4 text-lg font-semibold placeholder:font-normal placeholder:text-slate/60">
                <button type="submit" class="btn-primary shrink-0">{{ __('Search') }}</button>
            </form>
        </div>
    </section>

    <div class="mx-auto max-w-4xl px-4 pt-12 sm:px-6">
        <ul class="space-y-4">
            @forelse ($answers as $answer)
                <li>
                    <a href="{{ Locales::route('answers.show', ['slug' => $answer->slug]) }}" class="block rounded-2xl border-2 border-line bg-white p-6 transition hover:border-ink hover:shadow-[6px_6px_0_var(--color-brand)]">
                        <h2 class="text-xl font-black leading-snug">{{ $answer->public_question }}</h2>
                        <p class="mt-2 text-lg text-slate">{{ \Illuminate\Support\Str::limit(strip_tags($answer->public_answer), 180) }}</p>
                    </a>
                </li>
            @empty
                <li class="rounded-2xl bg-mist p-8 text-lg text-slate">{{ __('Answers will appear here as TRA publishes them.') }}</li>
            @endforelse
        </ul>
        <div class="mt-10">{{ $answers->links() }}</div>

        <aside class="mt-12 rounded-3xl bg-ink p-8 text-white">
            <p class="text-2xl font-black text-brand">{{ __('Have a tax question?') }}</p>
            <p class="mt-2 text-lg text-white/80">{{ __('Tax Club members can ask TRA experts directly.') }}</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('login') }}" class="btn-brand">{{ __('Sign in') }}</a>
                <a href="{{ route('register') }}" class="btn border-2 border-white/40 text-white hover:border-white">{{ __('Join Tax Club') }}</a>
            </div>
        </aside>
    </div>
</x-layouts.public>
