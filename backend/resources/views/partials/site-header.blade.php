@php
    use App\Support\Locales;

    $navigation = [
        ['route' => 'learn.index', 'label' => __('Learn'), 'active' => 'learn.*'],
        ['route' => 'events.index', 'label' => __('Events'), 'active' => 'events.*'],
        ['route' => 'about', 'label' => __('About Tax Club'), 'active' => 'about'],
    ];
    $currentName = preg_replace('/^(sw|en)\./', '', (string) request()->route()?->getName());
    $otherLocale = app()->getLocale() === 'sw' ? 'en' : 'sw';
    $switchUrl = $alternates[$otherLocale] ?? Locales::route('home', [], $otherLocale);
@endphp
<header class="border-b border-line bg-white">
    <div class="mx-auto flex max-w-6xl items-center gap-6 px-4 py-4 sm:px-6">
        <a href="{{ Locales::route('home') }}" class="flex items-center gap-2.5" aria-label="TRA Tax Club — {{ __('Home') }}">
            <svg viewBox="0 0 32 32" class="size-8 shrink-0" aria-hidden="true">
                <rect width="32" height="32" rx="7" fill="#14284b"/>
                <path d="M9 7h14v18l-2.33-1.6L18.33 25 16 23.4 13.67 25l-2.34-1.6L9 25z" fill="#f2b705"/>
                <path d="M12 12h8M12 15.5h8M12 19h5" stroke="#14284b" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
            <span class="leading-none">
                <span class="block text-[0.7rem] font-semibold text-slate">TRA</span>
                <span class="block text-lg font-extrabold tracking-tight">Tax Club</span>
            </span>
        </a>

        <nav class="ml-4 hidden items-center gap-1 md:flex" aria-label="{{ __('Main') }}">
            @foreach ($navigation as $item)
                @php($isActive = \Illuminate\Support\Str::is($item['active'], $currentName))
                <a href="{{ Locales::route($item['route']) }}"
                   @if ($isActive) aria-current="page" @endif
                   class="rounded-md px-3 py-2 text-[0.95rem] font-medium {{ $isActive ? 'text-ink underline decoration-flag decoration-[3px] underline-offset-8' : 'text-slate hover:text-ink' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-2">
            <a href="{{ $switchUrl }}" hreflang="{{ $otherLocale }}" lang="{{ $otherLocale }}"
               class="rounded-md px-2.5 py-2 text-sm font-medium text-slate hover:text-ink">
                {{ Locales::SUPPORTED[$otherLocale] }}
            </a>
            @auth
                <a href="{{ auth()->user()->canAccessAdmin() ? route('admin.dashboard') : route('member.dashboard') }}"
                   class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-white hover:bg-ink-soft">{{ __('My account') }}</a>
            @else
                <a href="{{ route('login') }}" class="hidden rounded-md px-3 py-2 text-sm font-semibold text-ink hover:bg-paper sm:inline-block">{{ __('Sign in') }}</a>
                <a href="{{ route('register') }}" class="rounded-md bg-flag px-4 py-2 text-sm font-bold text-ink hover:bg-[#ffc81f]">{{ __('Join Tax Club') }}</a>
            @endauth
        </div>
    </div>

    <nav class="flex gap-1 overflow-x-auto border-t border-line px-2 md:hidden" aria-label="{{ __('Main') }}">
        @foreach ($navigation as $item)
            @php($isActive = \Illuminate\Support\Str::is($item['active'], $currentName))
            <a href="{{ Locales::route($item['route']) }}" @if ($isActive) aria-current="page" @endif
               class="whitespace-nowrap px-3 py-3 text-sm font-medium {{ $isActive ? 'text-ink underline decoration-flag decoration-[3px] underline-offset-8' : 'text-slate' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
        @guest
            <a href="{{ route('login') }}" class="whitespace-nowrap px-3 py-3 text-sm font-semibold text-ink sm:hidden">{{ __('Sign in') }}</a>
        @endguest
    </nav>
</header>
