@php
    use App\Support\Locales;

    $navigation = [
        ['route' => 'learn.index', 'label' => __('Learn'), 'active' => 'learn.*'],
        ['route' => 'events.index', 'label' => __('Events'), 'active' => 'events.*'],
        ['route' => 'answers.index', 'label' => __('Questions'), 'active' => 'answers.*'],
        ['route' => 'about', 'label' => __('About Tax Club'), 'active' => 'about'],
    ];
    $currentName = preg_replace('/^(sw|en)\./', '', (string) request()->route()?->getName());
    $otherLocale = app()->getLocale() === 'sw' ? 'en' : 'sw';
    $switchUrl = $alternates[$otherLocale] ?? Locales::route('home', [], $otherLocale);
    $accountUrl = auth()->check() ? (auth()->user()->canAccessAdmin() ? route('admin.dashboard') : route('member.dashboard')) : null;
@endphp

{{-- Utility bar, as on tra.go.tz --}}
<div class="bg-ink text-sm text-white">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-2 sm:px-6">
        <a href="https://www.tra.go.tz" class="font-semibold text-white/80 hover:text-white" rel="noopener">{{ __('An official programme of the Tanzania Revenue Authority') }}</a>
        <a href="{{ $switchUrl }}" hreflang="{{ $otherLocale }}" lang="{{ $otherLocale }}" class="flex shrink-0 items-center gap-1.5 font-bold text-brand hover:text-brand-bright">
            <svg viewBox="0 0 20 20" class="size-4" fill="currentColor" aria-hidden="true"><path d="M10 1.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17Zm5.9 7.75h-2.92a13.6 13.6 0 0 0-1.06-4.86 7.02 7.02 0 0 1 3.98 4.86ZM10 3.1c.73.9 1.73 2.97 1.96 6.15H8.04C8.27 6.07 9.27 4 10 3.1ZM8.08 4.39a13.6 13.6 0 0 0-1.06 4.86H4.1a7.02 7.02 0 0 1 3.98-4.86ZM4.1 10.75h2.92c.1 1.84.48 3.5 1.06 4.86a7.02 7.02 0 0 1-3.98-4.86ZM10 16.9c-.73-.9-1.73-2.97-1.96-6.15h3.92c-.23 3.18-1.23 5.25-1.96 6.15Zm1.92-1.29c.58-1.36.96-3.02 1.06-4.86h2.92a7.02 7.02 0 0 1-3.98 4.86Z"/></svg>
            {{ Locales::SUPPORTED[$otherLocale] }}
        </a>
    </div>
</div>

<header class="sticky top-0 z-40 border-b-4 border-brand bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/85">
    <div class="mx-auto flex max-w-7xl items-center gap-6 px-4 py-3 sm:px-6">
        @include('partials.brand')

        <nav class="ml-6 hidden items-center gap-1 lg:flex" aria-label="{{ __('Main') }}">
            @foreach ($navigation as $item)
                @php($isActive = \Illuminate\Support\Str::is($item['active'], $currentName))
                <a href="{{ Locales::route($item['route']) }}" @if ($isActive) aria-current="page" @endif
                   @class(['rounded-lg px-4 py-2.5 text-[0.98rem] font-extrabold transition-colors', 'bg-brand text-ink' => $isActive, 'text-ink-soft hover:bg-brand-wash' => ! $isActive])>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="ml-auto hidden items-center gap-2 sm:flex">
            @if ($accountUrl)
                <a href="{{ $accountUrl }}" class="btn-primary">{{ __('My account') }}</a>
            @else
                <a href="{{ route('login') }}" class="btn px-4 text-ink hover:bg-brand-wash">{{ __('Sign in') }}</a>
                <a href="{{ route('register') }}" class="btn-brand">{{ __('Join Tax Club') }}</a>
            @endif
        </div>

        {{-- Mobile menu: a native disclosure, so it works without JavaScript --}}
        <details class="group ml-auto lg:hidden sm:ml-2">
            <summary class="grid size-12 cursor-pointer list-none place-items-center rounded-xl border-2 border-ink [&::-webkit-details-marker]:hidden" aria-label="{{ __('Menu') }}">
                <svg viewBox="0 0 24 24" class="size-6 group-open:hidden" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                <svg viewBox="0 0 24 24" class="hidden size-6 group-open:block" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </summary>
            <nav class="absolute inset-x-0 top-full border-b-4 border-brand bg-white px-4 pb-6 pt-2 shadow-xl" aria-label="{{ __('Main') }}">
                <ul class="space-y-1">
                    @foreach ($navigation as $item)
                        @php($isActive = \Illuminate\Support\Str::is($item['active'], $currentName))
                        <li>
                            <a href="{{ Locales::route($item['route']) }}" @if ($isActive) aria-current="page" @endif
                               @class(['block rounded-xl px-4 py-3.5 text-lg font-extrabold', 'bg-brand' => $isActive, 'hover:bg-brand-wash' => ! $isActive])>{{ $item['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-4 grid gap-2">
                    @if ($accountUrl)
                        <a href="{{ $accountUrl }}" class="btn-primary">{{ __('My account') }}</a>
                    @else
                        <a href="{{ route('register') }}" class="btn-brand">{{ __('Join Tax Club') }}</a>
                        <a href="{{ route('login') }}" class="btn-ghost">{{ __('Sign in') }}</a>
                    @endif
                </div>
            </nav>
        </details>
    </div>
</header>
