@php use App\Support\Locales; @endphp
<x-layouts.public :title="__('Page not found')" :noindex="true">
    <section class="sun-hatch border-b-4 border-ink">
        <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
            <p class="inline-block rounded-lg bg-ink px-3 py-1 font-receipt text-lg font-semibold text-brand">404</p>
            <h1 class="mt-4 text-4xl font-black tracking-tight sm:text-5xl">{{ __('Page not found') }}</h1>
            <p class="mt-4 max-w-[55ch] text-lg font-semibold text-ink-soft">{{ __('The page may have moved, or the lesson or event is no longer published.') }}</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ Locales::route('home') }}" class="btn-primary">{{ __('Go to the home page') }}</a>
                <a href="{{ Locales::route('learn.index') }}" class="btn-ghost">{{ __('Browse lessons') }}</a>
            </div>
        </div>
    </section>
</x-layouts.public>
