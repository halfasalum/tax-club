@props(['member', 'qr' => null])
@php
    use App\Support\MembershipCard;

    $categories = [
        'primary' => __('Primary school'), 'secondary' => __('Secondary school'),
        'college' => __('College'),
        'university' => __('University'),
        'alumni' => __('Alumni'),
    ];
    $valid = MembershipCard::isValid($member);
    $initials = collect(explode(' ', trim($member->full_name)))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
@endphp
<div {{ $attributes->class(['sun-hatch relative overflow-hidden rounded-3xl border-4 border-ink p-6 shadow-[8px_8px_0_var(--color-ink)] print:shadow-none']) }}>
    <img src="{{ asset('images/tra-logo.webp') }}" alt="" width="208" height="242" class="pointer-events-none absolute -bottom-6 -right-6 h-40 w-auto opacity-10" aria-hidden="true">
    <div class="relative flex items-center gap-3">
        <img src="{{ asset('images/tra-logo.webp') }}" alt="TRA" width="208" height="242" class="h-11 w-auto">
        <div class="leading-tight">
            <p class="font-black">TRA Tax Club</p>
            <p class="text-xs font-bold text-ink-soft">{{ __('Membership card') }}</p>
        </div>
    </div>

    <div class="relative mt-6 flex items-center gap-4">
        @if ($member->photo_path && \App\Support\MemberProfile::canSeePhoto(auth()->user(), $member))
            <img src="{{ route('members.photo', ['member' => $member, 'v' => md5($member->photo_path)]) }}" alt="" width="80" height="80" class="size-20 shrink-0 rounded-2xl border-2 border-ink object-cover">
        @else
            <span class="grid size-16 shrink-0 place-items-center rounded-2xl border-2 border-ink bg-white text-2xl font-black" aria-hidden="true">{{ $initials }}</span>
        @endif
        <div class="min-w-0">
            <p class="text-2xl font-black leading-tight">{{ $member->full_name }}</p>
            <p class="mt-1 font-bold text-ink-soft">{{ $member->institution?->name }}</p>
        </div>
    </div>

    <dl class="relative mt-6 grid grid-cols-2 gap-4">
        <div class="col-span-2">
            <dt class="text-xs font-extrabold text-ink-soft">{{ __('Membership no.') }}</dt>
            <dd class="mt-0.5 whitespace-nowrap font-receipt text-lg font-semibold">{{ $member->membership_id ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-extrabold text-ink-soft">{{ __('Category') }}</dt>
            <dd class="mt-0.5 text-lg font-black">{{ $categories[$member->user_type] ?? $member->user_type }}</dd>
        </div>
        @if ($member->membership_issued_at)
            <div>
                <dt class="text-xs font-extrabold text-ink-soft">{{ __('Member since') }}</dt>
                <dd class="mt-0.5 text-lg font-black">{{ $member->membership_issued_at->translatedFormat('M Y') }}</dd>
            </div>
        @endif
    </dl>

    <div class="relative mt-6 flex items-end justify-between gap-4">
        <p @class([
            'inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-extrabold',
            'bg-ink text-white' => $valid,
            'bg-danger text-white' => ! $valid,
        ])>
            <span @class(['size-2.5 rounded-full', 'bg-[#4ade80]' => $valid, 'bg-white' => ! $valid]) aria-hidden="true"></span>{{ MembershipCard::statusLabel($member->account_status) }}
        </p>
        @if ($qr && $valid)
            <div class="rounded-xl border-2 border-ink bg-white p-2 [&_svg]:size-32 sm:[&_svg]:size-36" role="img" aria-label="{{ __('QR code to verify this card') }}">{!! $qr !!}</div>
        @endif
    </div>
</div>
