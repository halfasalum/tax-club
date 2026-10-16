@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'type' => 'website',
    'jsonLd' => [],
    'noindex' => false,
])
@php
    use App\Support\Locales;

    $siteName = 'TRA Tax Club';
    $pageTitle = $title ? $title.' | '.$siteName : $siteName.' — '.__('Learn how taxes build Tanzania');
    $pageDescription = \Illuminate\Support\Str::limit(strip_tags($description ?? __('Tax Club brings students in secondary schools, colleges and universities together to learn how taxation works, test what they know and take part in national activities run by TRA.')), 160);
    $canonical = url()->current();
    $alternates = Locales::alternates();
    $locale = app()->getLocale();
    $ogImage = $image ?? asset('images/og-default.png');

    $graph = array_merge([
        [
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => $siteName,
            'url' => url('/'),
            'parentOrganization' => ['@type' => 'GovernmentOrganization', 'name' => 'Tanzania Revenue Authority', 'url' => 'https://www.tra.go.tz'],
        ],
        [
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'name' => $siteName,
            'url' => url('/'),
            'inLanguage' => ['sw', 'en'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => Locales::route('learn.index').'?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ],
    ], $jsonLd);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <link rel="canonical" href="{{ $canonical }}">
    @if ($noindex)
        <meta name="robots" content="noindex, nofollow">
    @endif
    @foreach ($alternates as $altLocale => $altUrl)
        <link rel="alternate" hreflang="{{ $altLocale }}" href="{{ $altUrl }}">
    @endforeach
    @isset($alternates[Locales::DEFAULT])
        <link rel="alternate" hreflang="x-default" href="{{ $alternates[Locales::DEFAULT] }}">
    @endisset

    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:title" content="{{ $title ?? $siteName }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:locale" content="{{ $locale === 'sw' ? 'sw_TZ' : 'en_GB' }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#14284b">

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=schibsted-grotesk:400,500,600,700,800|ibm-plex-mono:400,500,600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
</head>
<body class="min-h-screen flex flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-ink focus:px-4 focus:py-2 focus:text-white">{{ __('Skip to content') }}</a>

    @include('partials.site-header', ['alternates' => $alternates])

    <main id="main" class="flex-1">
        {{ $slot }}
    </main>

    @include('partials.site-footer', ['alternates' => $alternates])
</body>
</html>
