@php use App\Support\Locales; @endphp
<x-layouts.public
    :title="__('Tax lessons')"
    :description="__('Free lessons on how taxes work in Tanzania: VAT, income tax, receipts, customs and more, prepared by TRA for Tax Club members.')"
    :noindex="$search !== '' || $lessons->currentPage() > 1"
>
    <div class="mx-auto max-w-6xl px-4 pt-14 sm:px-6">
        <h1 class="text-4xl font-extrabold tracking-tight">{{ __('Tax lessons') }}</h1>
        <p class="mt-3 max-w-[60ch] text-lg text-slate">{{ __('Short, practical lessons prepared by TRA. Members can track progress and take quizzes after signing in.') }}</p>

        <form method="get" action="{{ Locales::route('learn.index') }}" class="mt-8 flex max-w-xl gap-2" role="search">
            <label for="q" class="sr-only">{{ __('Search lessons') }}</label>
            <input id="q" name="q" type="search" value="{{ $search }}" placeholder="{{ __('Search lessons') }}"
                   class="w-full rounded-md border border-line bg-white px-4 py-3 text-ink placeholder:text-slate/70 focus:border-ink focus:outline-none">
            <button type="submit" class="rounded-md bg-ink px-5 py-3 font-semibold text-white hover:bg-ink-soft">{{ __('Search') }}</button>
        </form>

        @if ($lessons->isEmpty())
            <div class="mt-12 max-w-[60ch]">
                @if ($search !== '')
                    <p class="text-lg font-semibold">{{ __('No lessons match “:term”.', ['term' => $search]) }}</p>
                    <p class="mt-2 text-slate">{{ __('Try a shorter word, or') }} <a class="font-semibold text-ink underline" href="{{ Locales::route('learn.index') }}">{{ __('see all lessons') }}</a>.</p>
                @else
                    <p class="text-slate">{{ __('Lessons will appear here as soon as TRA publishes them.') }}</p>
                @endif
            </div>
        @else
            <div class="mt-10 divide-y divide-line border-y border-line">
                @foreach ($lessons as $lesson)
                    @include('learn.partials.row', ['lesson' => $lesson])
                @endforeach
            </div>
            <div class="mt-8">{{ $lessons->links() }}</div>
        @endif
    </div>
</x-layouts.public>
