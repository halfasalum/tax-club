@php
    use App\Support\Locales;

    $user = auth()->user();
    $inAdmin = request()->routeIs('admin.*');
    $navigation = $inAdmin
        ? array_values(array_filter([
            ['route' => 'admin.dashboard', 'label' => __('Overview')],
            $user->hasControl('view_registrations') ? ['route' => 'admin.registrations', 'label' => __('Registrations')] : null,
            $user->hasControl('view_members') ? ['route' => 'admin.members.index', 'match' => 'admin.members.*', 'label' => __('Members')] : null,
            $user->hasControl('view_institutions') ? ['route' => 'admin.institutions.index', 'match' => 'admin.institutions.*', 'label' => __('Institutions')] : null,
            $user->hasControl('assign_coordinator') ? ['route' => 'admin.coordinators', 'label' => __('Coordinators')] : null,
            $user->hasControl('view_audit_log') ? ['route' => 'admin.audit', 'label' => __('Audit log')] : null,
        ]))
        : [
            ['route' => 'member.dashboard', 'label' => __('My Tax Club')],
            ['route' => 'member.card', 'label' => __('My card')],
        ];
    $initials = collect(explode(' ', trim($user->full_name)))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ ($title ?? null) ? $title.' | ' : '' }}TRA Tax Club</title>
    @include('partials.head-assets')
</head>
<body class="min-h-screen bg-mist">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-brand focus:px-4 focus:py-3 focus:font-extrabold focus:text-ink">{{ __('Skip to content') }}</a>

    <header class="sticky top-0 z-40 print:hidden border-b-4 border-brand bg-ink text-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3 sm:px-6">
            @include('partials.brand', ['inverse' => true])
            @if ($inAdmin)
                <span class="rounded-full bg-brand px-3 py-1 text-xs font-black text-ink">{{ __('Administration') }}</span>
            @endif

            <div class="ml-auto flex items-center gap-2 text-sm">
                @if ($inAdmin)
                    <a href="{{ route('member.dashboard') }}" class="hidden rounded-lg px-3 py-2 font-bold text-white/80 hover:bg-white/10 hover:text-white sm:inline-block">{{ __('Member view') }}</a>
                @elseif ($user->canAccessAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="hidden rounded-lg px-3 py-2 font-bold text-white/80 hover:bg-white/10 hover:text-white sm:inline-block">{{ __('Administration') }}</a>
                @endif
                @foreach (Locales::SUPPORTED as $code => $label)
                    @continue($code === app()->getLocale())
                    <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" lang="{{ $code }}" class="rounded-lg px-3 py-2 font-bold text-brand hover:bg-white/10">{{ $label }}</a>
                @endforeach
                <span class="grid size-10 place-items-center rounded-full bg-brand font-black text-ink" title="{{ $user->full_name }}" aria-hidden="true">{{ $initials }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg border-2 border-white/30 px-3 py-2 font-extrabold hover:border-white">{{ __('Sign out') }}</button>
                </form>
            </div>
        </div>

        <nav class="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-4 sm:px-6" aria-label="{{ __('Sections') }}">
            @foreach ($navigation as $item)
                @php($active = request()->routeIs($item['match'] ?? $item['route']))
                <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                   @class(['whitespace-nowrap rounded-t-xl px-5 py-3 font-extrabold transition-colors', 'bg-brand text-ink' => $active, 'text-white/75 hover:bg-white/10 hover:text-white' => ! $active])>{{ $item['label'] }}</a>
            @endforeach
        </nav>
    </header>

    <main id="main" class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        {{ $slot }}
    </main>
</body>
</html>
