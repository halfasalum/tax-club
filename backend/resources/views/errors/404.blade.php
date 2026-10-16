@php use App\Support\Locales; @endphp
<x-layouts.public :title="__('Page not found')" :noindex="true">
    <div class="mx-auto max-w-3xl px-4 pt-20 sm:px-6">
        <p class="font-receipt text-slate">404</p>
        <h1 class="mt-2 text-4xl font-extrabold tracking-tight">{{ __('Page not found') }}</h1>
        <p class="mt-4 max-w-[55ch] text-lg text-slate">{{ __('The page may have moved, or the lesson or event is no longer published.') }}</p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ Locales::route('home') }}" class="rounded-md bg-ink px-5 py-3 font-semibold text-white hover:bg-ink-soft">{{ __('Go to the home page') }}</a>
            <a href="{{ Locales::route('learn.index') }}" class="rounded-md border border-ink/20 px-5 py-3 font-semibold text-ink hover:border-ink">{{ __('Browse lessons') }}</a>
        </div>
    </div>
</x-layouts.public>
