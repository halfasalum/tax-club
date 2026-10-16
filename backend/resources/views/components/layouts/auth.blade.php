@php use App\Support\Locales; @endphp
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
    <div class="mx-auto flex min-h-screen max-w-md flex-col px-4 py-10">
        <a href="{{ Locales::route('home') }}" class="flex items-center gap-2.5 self-start" aria-label="TRA Tax Club — {{ __('Home') }}">
            <svg viewBox="0 0 32 32" class="size-8" aria-hidden="true">
                <rect width="32" height="32" rx="7" fill="#14284b"/>
                <path d="M9 7h14v18l-2.33-1.6L18.33 25 16 23.4 13.67 25l-2.34-1.6L9 25z" fill="#f2b705"/>
                <path d="M12 12h8M12 15.5h8M12 19h5" stroke="#14284b" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
            <span class="text-lg font-extrabold tracking-tight">Tax Club</span>
        </a>

        <main class="mt-10 flex-1">
            {{ $slot }}
        </main>

        <p class="mt-10 text-sm text-slate">
            @foreach (Locales::SUPPORTED as $code => $label)
                @continue($code === app()->getLocale())
                <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" class="font-semibold text-ink underline decoration-flag decoration-2 underline-offset-4" lang="{{ $code }}">{{ $label }}</a>
            @endforeach
        </p>
    </div>
</body>
</html>
