@php
    use App\Support\Locales;

    $user = auth()->user();
    $inAdmin = request()->routeIs('admin.*');
    $navigation = $inAdmin
        ? array_filter([
            ['route' => 'admin.dashboard', 'label' => __('Overview')],
            $user->hasControl('view_registrations') ? ['route' => 'admin.registrations', 'label' => __('Registrations')] : null,
        ])
        : [
            ['route' => 'member.dashboard', 'label' => __('My Tax Club')],
        ];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ ($title ?? null) ? $title.' | ' : '' }}TRA Tax Club</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=schibsted-grotesk:400,500,600,700,800|ibm-plex-mono:400,500,600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-ink focus:px-4 focus:py-2 focus:text-white">{{ __('Skip to content') }}</a>

    <header class="bg-ink text-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3 sm:px-6">
            <a href="{{ Locales::route('home') }}" class="flex items-center gap-2" aria-label="TRA Tax Club — {{ __('Home') }}">
                <svg viewBox="0 0 32 32" class="size-7" aria-hidden="true">
                    <rect width="32" height="32" rx="7" fill="#f2b705"/>
                    <path d="M9 7h14v18l-2.33-1.6L18.33 25 16 23.4 13.67 25l-2.34-1.6L9 25z" fill="#14284b"/>
                    <path d="M12 12h8M12 15.5h8M12 19h5" stroke="#f2b705" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                <span class="font-extrabold">Tax Club</span>
                @if ($inAdmin)
                    <span class="rounded bg-white/10 px-2 py-0.5 text-xs font-semibold text-white/85">{{ __('Administration') }}</span>
                @endif
            </a>

            <nav class="flex flex-wrap gap-1" aria-label="{{ __('Sections') }}">
                @foreach ($navigation as $item)
                    @php($active = request()->routeIs($item['route']))
                    <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                       class="rounded-md px-3 py-1.5 text-sm font-medium {{ $active ? 'bg-white text-ink' : 'text-white/80 hover:text-white' }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>

            <div class="ml-auto flex items-center gap-3 text-sm">
                @if ($inAdmin)
                    <a href="{{ route('member.dashboard') }}" class="text-white/80 hover:text-white">{{ __('Member view') }}</a>
                @elseif ($user->canAccessAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="text-white/80 hover:text-white">{{ __('Administration') }}</a>
                @endif
                @foreach (Locales::SUPPORTED as $code => $label)
                    @continue($code === app()->getLocale())
                    <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" class="text-white/80 hover:text-white" lang="{{ $code }}">{{ $label }}</a>
                @endforeach
                <span class="hidden text-white/60 sm:inline">{{ $user->full_name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-white/30 px-3 py-1.5 font-semibold hover:bg-white/10">{{ __('Sign out') }}</button>
                </form>
            </div>
        </div>
    </header>

    <main id="main" class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        @if (session('status'))
            <div role="status" class="mb-6 rounded-md border border-savanna/30 bg-savanna/10 px-4 py-3 font-medium text-savanna">{{ session('status') }}</div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
