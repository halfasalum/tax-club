@php
    $kinds = \App\Support\Certificates::kindLabels();
    $statuses = \App\Support\Rewards::statusLabels();
@endphp
<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('My achievements') }}</h1>
    <p class="mt-1 text-lg text-slate">{{ __('Your certificates and rewards. Points and badges are on the Points page.') }}</p>

    <h2 class="mt-8 text-2xl font-black">{{ __('Certificates') }}</h2>
    <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($certificates as $certificate)
            <li>
                <a wire:navigate href="{{ route('member.certificates.show', $certificate) }}" class="sun-hatch flex h-full flex-col rounded-2xl border-4 border-ink p-5 transition hover:-translate-y-0.5">
                    <span class="text-sm font-extrabold">{{ $kinds[$certificate->kind] ?? '' }}</span>
                    <span class="mt-2 text-lg font-black leading-snug">{{ $certificate->subject }}</span>
                    <span class="mt-auto pt-3 text-sm font-bold">{{ $certificate->issued_at->translatedFormat('j M Y') }}@unless ($certificate->isValid()) · {{ __('Withdrawn') }}@endunless</span>
                </a>
            </li>
        @empty
            <li class="rounded-2xl bg-white p-8 text-center font-bold text-slate sm:col-span-2 lg:col-span-3">{{ __('Finish a learning module, attend an event or enter a competition to earn your first certificate.') }}</li>
        @endforelse
    </ul>

    <h2 class="mt-10 text-2xl font-black">{{ __('Rewards') }}</h2>
    <ul class="mt-4 divide-y divide-line rounded-2xl border-2 border-line bg-white">
        @forelse ($rewards as $award)
            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <span><span class="font-bold">🏆 {{ $award->reward?->name() }}</span><span class="block text-sm text-slate">{{ $award->reason }}</span></span>
                <span class="rounded-full bg-mist px-3 py-1 text-sm font-extrabold">{{ $statuses[$award->status] ?? $award->status }}</span>
            </li>
        @empty
            <li class="px-5 py-6 text-slate">{{ __('No rewards yet. Win a competition to get one!') }}</li>
        @endforelse
    </ul>
</div>
