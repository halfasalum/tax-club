@php
    use App\Models\User;
    use App\Support\Locales;

    $user = auth()->user();
    $inAdmin = request()->routeIs('admin.*');
    $can = fn (string ...$controls) => collect($controls)->contains(fn ($control) => $user->hasControl($control));
    $unread = $user->unreadNotifications()->count();

    // Sections of the side menu: [heading => [items]]. Empty sections are hidden.
    $sections = $inAdmin
        ? [
            null => [
                ['route' => 'admin.dashboard', 'label' => __('Overview'), 'icon' => 'grid'],
            ],
            __('Membership') => [
                $can('view_registrations') ? ['route' => 'admin.registrations', 'label' => __('Registrations'), 'icon' => 'check',
                    'badge' => User::where('account_status', User::STATUS_PENDING_VERIFICATION)->when($user->institutionScope() !== null, fn ($query) => $query->whereIn('institution_id', $user->institutionScope()))->count()] : null,
                $can('view_members') ? ['route' => 'admin.members.index', 'match' => 'admin.members.*', 'label' => __('Members'), 'icon' => 'users'] : null,
                $can('assign_coordinator') ? ['route' => 'admin.coordinators', 'label' => __('Coordinators'), 'icon' => 'map'] : null,
            ],
            __('Institutions') => [
                $can('view_institutions') ? ['route' => 'admin.institutions.index', 'match' => 'admin.institutions.*', 'label' => __('Institutions'), 'icon' => 'school'] : null,
                $can('view_statistics') ? ['route' => 'admin.statistics', 'label' => __('Statistics'), 'icon' => 'chart'] : null,
            ],
            __('Learning') => [
                $can('upload_content', 'edit_content', 'review_content', 'publish_content') ? ['route' => 'admin.learning.index', 'match' => ['admin.learning.index', 'admin.learning.create', 'admin.learning.show', 'admin.learning.edit', 'admin.learning.categories'], 'label' => __('Lessons'), 'icon' => 'learn'] : null,
                $can('upload_content', 'edit_content', 'review_content', 'publish_content') ? ['route' => 'admin.learning.modules', 'label' => __('Modules'), 'icon' => 'layers'] : null,
                $can('create_quiz', 'edit_quiz', 'review_quiz', 'publish_quiz', 'view_results') ? ['route' => 'admin.quizzes.index', 'match' => 'admin.quizzes.*', 'label' => __('Quizzes'), 'icon' => 'quiz'] : null,
                $can('manage_gamification') ? ['route' => 'admin.gamification', 'label' => __('Points and badges'), 'icon' => 'trophy'] : null,
            ],
            __('Community and safety') => [
                $can('moderate_community', 'handle_safeguarding_concerns') ? ['route' => 'admin.moderation.index', 'match' => 'admin.moderation.*', 'label' => __('Moderation'), 'icon' => 'shield',
                    'badge' => \App\Models\ModerationCase::visibleTo($user)->where('status', '!=', 'closed')->count()] : null,
                $can('manage_community_spaces') ? ['route' => 'admin.community.spaces', 'match' => 'admin.community.*', 'label' => __('Community spaces'), 'icon' => 'chat'] : null,
                $can('triage_questions', 'answer_questions', 'review_answers') ? ['route' => 'admin.questions.index', 'match' => 'admin.questions.*', 'label' => __('Ask TRA'), 'icon' => 'help',
                    'badge' => \App\Models\Question::where('status', 'submitted')->count()] : null,
            ],
            __('Engagement') => [
                $can('add_event', 'edit_event', 'review_event', 'publish_event', 'view_event_attendees', 'check_in_attendees') ? ['route' => 'admin.events.index', 'match' => 'admin.events.*', 'label' => __('Events'), 'icon' => 'calendar'] : null,
                $can('add_opportunity', 'edit_opportunity', 'review_opportunity', 'publish_opportunity', 'view_opportunity_reports') ? ['route' => 'admin.opportunities.index', 'match' => 'admin.opportunities.*', 'label' => __('Opportunities'), 'icon' => 'briefcase'] : null,
                $can('create_competition', 'review_competition', 'publish_competition', 'judge_competition', 'approve_results') ? ['route' => 'admin.competitions.index', 'match' => ['admin.competitions.*', 'admin.rewards', 'admin.certificates'], 'label' => __('Competitions'), 'icon' => 'medal'] : null,
                ! $can('create_competition', 'review_competition', 'publish_competition', 'judge_competition', 'approve_results') && $can('manage_rewards', 'award_rewards', 'approve_rewards') ? ['route' => 'admin.rewards', 'label' => __('Rewards'), 'icon' => 'award'] : null,
                $can('manage_mentorships', 'review_mentors') ? ['route' => 'admin.mentorship.index', 'match' => 'admin.mentorship.*', 'label' => __('Mentorship'), 'icon' => 'handshake'] : null,
                $can('review_alumni_stories') ? ['route' => 'admin.alumni.stories', 'label' => __('Alumni stories'), 'icon' => 'graduate'] : null,
                $can('view_feedback') ? ['route' => 'admin.feedback.index', 'match' => 'admin.feedback.*', 'label' => __('Feedback'), 'icon' => 'feedback',
                    'badge' => \App\Models\Feedback::visibleTo($user)->where('status', \App\Models\Feedback::SUBMITTED)->count()] : null,
            ],
            __('Communication') => [
                $can('create_broadcast', 'approve_broadcast') ? ['route' => 'admin.broadcasts.index', 'match' => ['admin.broadcasts.*', 'admin.reminders'], 'label' => __('Broadcasts'), 'icon' => 'megaphone'] : null,
                $can('manage_reminders') && ! $can('create_broadcast', 'approve_broadcast') ? ['route' => 'admin.reminders', 'label' => __('Automatic reminders'), 'icon' => 'bell'] : null,
                $can('manage_notification_templates') ? ['route' => 'admin.notifications', 'label' => __('Messages'), 'icon' => 'message'] : null,
                $can('view_sms_log') ? ['route' => 'admin.sms', 'label' => __('SMS delivery'), 'icon' => 'bell'] : null,
            ],
            __('Security') => [
                $can('view_audit_log') ? ['route' => 'admin.audit', 'label' => __('Audit log'), 'icon' => 'shield'] : null,
            ],
        ]
        : [
            null => [
                ['route' => 'member.dashboard', 'label' => __('My Tax Club'), 'icon' => 'home'],
                ['route' => 'member.learn', 'match' => 'member.learn*', 'label' => __('Learn'), 'icon' => 'learn'],
                ['route' => 'member.quizzes', 'match' => 'member.quizzes*', 'label' => __('Quizzes'), 'icon' => 'quiz'],
                ['route' => 'member.competitions', 'match' => 'member.competitions*', 'label' => __('Competitions'), 'icon' => 'medal'],
                ['route' => 'member.events', 'match' => 'member.events*', 'label' => __('Events'), 'icon' => 'calendar'],
                ['route' => 'member.opportunities', 'match' => 'member.opportunities*', 'label' => __('Opportunities'), 'icon' => 'briefcase'],
                ['route' => 'member.leaderboard', 'label' => __('Points'), 'icon' => 'trophy'],
            ],
            __('Connect') => [
                ['route' => 'member.community', 'match' => 'member.community*', 'label' => __('Community'), 'icon' => 'chat'],
                ['route' => 'member.ask', 'match' => 'member.ask*', 'label' => __('Ask TRA'), 'icon' => 'help'],
                ['route' => 'member.alumni', 'match' => 'member.alumni*', 'label' => __('Alumni'), 'icon' => 'graduate'],
                ['route' => 'member.mentorship', 'match' => 'member.mentorship*', 'label' => __('Mentorship'), 'icon' => 'handshake'],
            ],
            __('My account') => [
                ['route' => 'member.card', 'label' => __('My card'), 'icon' => 'card'],
                ['route' => 'member.achievements', 'match' => ['member.achievements', 'member.certificates.*'], 'label' => __('My achievements'), 'icon' => 'award'],
                ['route' => 'member.notifications', 'label' => __('Notifications'), 'icon' => 'inbox', 'badge' => $unread],
                ['route' => 'member.profile', 'label' => __('My profile'), 'icon' => 'user'],
                ['route' => 'member.feedback', 'match' => 'member.feedback*', 'label' => __('Feedback'), 'icon' => 'feedback'],
            ],
        ];
    $sections = collect($sections)->map(fn ($items) => array_values(array_filter($items)))->filter();

    $initials = collect(explode(' ', trim($user->full_name)))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    $roleLabel = $inAdmin
        ? ($user->roles()->where('is_active', true)->value('name') ?? __('Staff'))
        : ($user->membership_id ?? __('Member'));
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
<body class="min-h-screen bg-mist" x-data="{ menu: false }" x-on:keydown.escape.window="menu = false">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-brand focus:px-4 focus:py-3 focus:font-extrabold focus:text-ink">{{ __('Skip to content') }}</a>

    {{-- Dimmed backdrop behind the menu on small screens --}}
    <div x-show="menu" x-transition.opacity x-on:click="menu = false" class="fixed inset-0 z-40 bg-ink/60 lg:hidden print:hidden" x-cloak></div>

    {{-- Side navigation --}}
    <aside :style="menu ? 'translate: 0; box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.5)' : ''"
           class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-ink text-white transition-[translate] duration-200 lg:translate-x-0 print:hidden"
           aria-label="{{ __('Main menu') }}">
        <div class="flex items-center justify-between gap-3 border-b border-white/10 px-5 py-5">
            @include('partials.brand', ['inverse' => true])
            <button type="button" x-on:click="menu = false" class="grid size-10 place-items-center rounded-xl text-white/70 hover:bg-white/10 hover:text-white lg:hidden" aria-label="{{ __('Close menu') }}">
                <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        @if ($inAdmin)
            <p class="mx-5 mt-4 inline-flex w-fit items-center gap-2 rounded-full bg-brand px-3 py-1 text-xs font-black text-ink">
                <span class="size-2 rounded-full bg-ink" aria-hidden="true"></span>{{ __('Administration') }}
            </p>
        @endif

        <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="{{ __('Sections') }}" wire:scroll>
            @foreach ($sections as $heading => $items)
                <div @class(['mt-6' => ! $loop->first])>
                    @if ($heading)
                        <p class="px-3 pb-2 text-xs font-black uppercase tracking-[0.14em] text-white/40">{{ $heading }}</p>
                    @endif
                    <ul class="space-y-1">
                        @foreach ($items as $item)
                            @php($active = request()->routeIs(...(array) ($item['match'] ?? $item['route'])))
                            <li>
                                <a href="{{ route($item['route']) }}" wire:navigate.hover @if ($active) aria-current="page" @endif
                                   @class([
                                       'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 font-bold transition-colors',
                                       'bg-brand text-ink shadow-[0_6px_20px_-8px_rgb(249_229_15/0.7)]' => $active,
                                       'text-white/75 hover:bg-white/10 hover:text-white' => ! $active,
                                   ])>
                                    <x-nav-icon :name="$item['icon']" @class(['text-ink' => $active, 'text-brand/80 group-hover:text-brand' => ! $active]) />
                                    <span class="flex-1">{{ $item['label'] }}</span>
                                    @if (($item['badge'] ?? 0) > 0)
                                        <span @class(['min-w-6 rounded-full px-2 py-0.5 text-center text-xs font-black', 'bg-ink text-brand' => $active, 'bg-danger text-white' => ! $active])>{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="border-t border-white/10 p-3">
            @if ($inAdmin)
                <a wire:navigate href="{{ route('member.dashboard') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-bold text-white/75 hover:bg-white/10 hover:text-white">
                    <x-nav-icon name="switch" class="text-brand/80" />{{ __('Member view') }}
                </a>
            @elseif ($user->canAccessAdmin())
                <a wire:navigate href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-bold text-white/75 hover:bg-white/10 hover:text-white">
                    <x-nav-icon name="switch" class="text-brand/80" />{{ __('Administration') }}
                </a>
            @endif
            @foreach (Locales::SUPPORTED as $code => $label)
                @continue($code === app()->getLocale())
                <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" lang="{{ $code }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-bold text-white/75 hover:bg-white/10 hover:text-white">
                    <x-nav-icon name="globe" class="text-brand/80" />{{ $label }}
                </a>
            @endforeach

            <div class="mt-3 flex items-center gap-3 rounded-2xl bg-white/5 p-3">
                @if ($user->photo_path)
                    <img src="{{ route('members.photo', ['member' => $user, 'v' => md5($user->photo_path)]) }}" alt="" width="40" height="40" class="size-10 shrink-0 rounded-full object-cover ring-2 ring-brand">
                @else
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand font-black text-ink" aria-hidden="true">{{ $initials }}</span>
                @endif
                <div class="min-w-0 flex-1">
                    <p class="truncate font-extrabold">{{ $user->full_name }}</p>
                    <p class="truncate font-receipt text-xs text-white/60">{{ $roleLabel }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="grid size-10 place-items-center rounded-xl text-white/70 hover:bg-white/10 hover:text-white" title="{{ __('Sign out') }}" aria-label="{{ __('Sign out') }}">
                        <x-nav-icon name="logout" />
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="lg:pl-72">
        {{-- Top bar --}}
        <header class="sticky top-0 z-30 border-b-4 border-brand bg-white/95 backdrop-blur print:hidden">
            <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6">
                <button type="button" x-on:click="menu = true" class="grid size-11 place-items-center rounded-xl border-2 border-line hover:border-ink lg:hidden" aria-label="{{ __('Open menu') }}" :aria-expanded="menu.toString()">
                    <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
                </button>
                <a href="{{ route($inAdmin ? 'admin.dashboard' : 'member.dashboard') }}" wire:navigate class="flex items-center gap-2 lg:hidden">
                    <img src="{{ asset('images/tra-logo.webp') }}" alt="" width="208" height="242" class="h-8 w-auto">
                    <span class="font-black">Tax Club</span>
                </a>
                <p class="hidden truncate text-lg font-black lg:block">{{ $title ?? '' }}</p>

                <div class="ml-auto flex items-center gap-2">
                    <a wire:navigate href="{{ route('member.notifications') }}" class="relative grid size-11 place-items-center rounded-xl hover:bg-mist" title="{{ __('Notifications') }}">
                        <x-nav-icon name="bell" class="size-6" />
                        <span class="sr-only">{{ trans_choice('{0} Notifications|{1} Notifications, :count unread|[2,*] Notifications, :count unread', $unread, ['count' => $unread]) }}</span>
                        @if ($unread > 0)
                            <span class="absolute right-1 top-1 grid min-w-5 place-items-center rounded-full bg-danger px-1 text-xs font-black text-white" aria-hidden="true">{{ $unread > 9 ? '9+' : $unread }}</span>
                        @endif
                    </a>
                    <a wire:navigate href="{{ route('member.profile') }}" class="lg:hidden" aria-label="{{ __('My profile') }}">
                        <span class="grid size-10 place-items-center rounded-full bg-brand font-black text-ink" aria-hidden="true">{{ $initials }}</span>
                    </a>
                </div>
            </div>
        </header>

        <main id="main" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
