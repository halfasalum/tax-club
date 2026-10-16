@php use App\Support\Locales; @endphp
<x-layouts.public
    :title="__('Tax lessons')"
    :description="__('Free lessons on how taxes work in Tanzania: VAT, income tax, receipts, customs and more, prepared by TRA for Tax Club members.')"
    :noindex="$search !== '' || $lessons->currentPage() > 1"
>
    <section class="sun-hatch border-b-4 border-ink">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16">
            <h1 class="text-4xl font-black tracking-tight sm:text-5xl">{{ __('Tax lessons') }}</h1>
            <p class="mt-3 max-w-[60ch] text-lg font-semibold text-ink-soft">{{ __('Short, practical lessons prepared by TRA. Members can track progress and take quizzes after signing in.') }}</p>

            <form method="get" action="{{ Locales::route('learn.index') }}" class="mt-8 flex max-w-2xl gap-2 rounded-2xl bg-white p-2 shadow-[6px_6px_0_var(--color-ink)]" role="search">
                <label for="q" class="sr-only">{{ __('Search lessons') }}</label>
                <input id="q" name="q" type="search" value="{{ $search }}" placeholder="{{ __('Search lessons, for example VAT') }}"
                       class="min-h-12 w-full rounded-xl px-4 text-lg font-semibold placeholder:font-normal placeholder:text-slate/60">
                <button type="submit" class="btn-primary shrink-0">{{ __('Search') }}</button>
            </form>
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 pt-12 sm:px-6">
        @if ($lessons->isEmpty())
            <div class="max-w-[60ch] rounded-2xl bg-mist p-8">
                @if ($search !== '')
                    <p class="text-xl font-black">{{ __('No lessons match “:term”.', ['term' => $search]) }}</p>
                    <p class="mt-2 text-lg text-slate">{{ __('Try a shorter word, or') }} <a class="link" href="{{ Locales::route('learn.index') }}">{{ __('see all lessons') }}</a>.</p>
                @else
                    <p class="text-lg text-slate">{{ __('Lessons will appear here as soon as TRA publishes them.') }}</p>
                @endif
            </div>
        @else
            @if ($search !== '')
                <p class="mb-6 font-bold text-slate">{{ trans_choice(':count lesson found|:count lessons found', $lessons->total(), ['count' => $lessons->total()]) }}</p>
            @endif
            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($lessons as $lesson)
                    @include('learn.partials.card', ['lesson' => $lesson])
                @endforeach
            </div>
            <div class="mt-10">{{ $lessons->links() }}</div>
        @endif
    </div>
</x-layouts.public>
