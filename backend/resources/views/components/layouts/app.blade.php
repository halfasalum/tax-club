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
            collect(['upload_content', 'edit_content', 'review_content', 'publish_content'])->contains(fn ($control) => $user->hasControl($control)) ? ['route' => 'admin.learning.index', 'match' => 'admin.learning.*', 'label' => __('Learning')] : null,
            collect(['create_quiz', 'edit_quiz', 'review_quiz', 'publish_quiz', 'view_results'])->contains(fn ($control) => $user->hasControl($control)) ? ['route' => 'admin.quizzes.index', 'match' => 'admin.quizzes.*', 'label' => __('Quizzes')] : null,
            $user->hasControl('view_statistics') ? ['route' => 'admin.statistics', 'label' => __('Statistics')] : null,
            $user->hasControl('assign_coordinator') ? ['route' => 'admin.coordinators', 'label' => __('Coordinators')] : null,
            $user->hasControl('manage_notification_templates') ? ['route' => 'admin.notifications', 'label' => __('Notifications')] : ($user->hasControl('view_sms_log') ? ['route' => 'admin.sms', 'label' => __('SMS delivery')] : null),
            $user->hasControl('manage_gamification') ? ['route' => 'admin.gamification', 'label' => __('Points')] : null,
            $user->hasControl('view_audit_log') ? ['route' => 'admin.audit', 'label' => __('Audit log')] : null,
        ]))
        : [
            ['route' => 'member.dashboard', 'label' => __('My Tax Club')],
            ['route' => 'member.learn', 'match' => 'member.learn*', 'label' => __('Learn')],
            ['route' => 'member.quizzes', 'match' => 'member.quizzes*', 'label' => __('Quizzes')],
            ['route' => 'member.leaderboard', 'label' => __('Points')],
            ['route' => 'member.card', 'label' => __('My card')],
            ['route' => 'member.profile', 'label' => __('My profile')],
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
                @php($unreadNotifications = $user->unreadNotifications()->count())
                <a href="{{ route('member.notifications') }}" class="relative grid size-10 place-items-center rounded-full hover:bg-white/10" title="{{ __('Notifications') }}">
                    <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/></svg>
                    <span class="sr-only">{{ trans_choice('{0} Notifications|{1} Notifications, :count unread|[2,*] Notifications, :count unread', $unreadNotifications, ['count' => $unreadNotifications]) }}</span>
                    @if ($unreadNotifications > 0)
                        <span class="absolute -right-0.5 -top-0.5 grid min-w-5 place-items-center rounded-full bg-danger px-1 text-xs font-black text-white" aria-hidden="true">{{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}</span>
                    @endif
                </a>
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
