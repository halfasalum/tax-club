<div class="mx-auto max-w-4xl">
    <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <a wire:navigate href="{{ route('member.achievements') }}" class="link">← {{ __('My achievements') }}</a>
        <button type="button" onclick="window.print()" class="btn-primary">{{ __('Print or save as PDF') }}</button>
    </div>

    <article class="relative mt-6 overflow-hidden rounded-3xl border-[6px] border-ink bg-white p-8 text-center shadow-[10px_10px_0_var(--color-brand)] print:mt-0 print:shadow-none sm:p-12">
        <img src="{{ asset('images/tra-logo.webp') }}" alt="" class="pointer-events-none absolute -bottom-10 -right-10 h-64 w-auto opacity-[0.06]" aria-hidden="true">
        <img src="{{ asset('images/tra-logo.webp') }}" alt="TRA" class="mx-auto h-20 w-auto">
        <p class="mt-3 text-sm font-extrabold uppercase tracking-[0.2em]">Tanzania Revenue Authority · TRA Tax Club</p>
        <h1 class="mt-6 text-4xl font-black tracking-tight sm:text-5xl">{{ $certificate->title('sw') }}</h1>
        <p class="text-xl font-bold text-ink-soft">{{ $certificate->title('en') }}</p>
        <p class="mt-8 text-lg">{{ __('This is to certify that') }}</p>
        <p class="mt-2 border-b-4 border-brand pb-2 text-3xl font-black sm:text-4xl">{{ $member->full_name }}</p>
        <p class="mt-2 font-bold text-slate">{{ $member->institution?->name }} · <span class="font-receipt">{{ $member->membership_id }}</span></p>
        <p class="mt-6 text-lg">{{ __('has been recognised for') }}</p>
        <p class="mt-1 text-2xl font-black">{{ $certificate->subject }}</p>
        <div class="mt-10 flex flex-wrap items-end justify-between gap-6 text-left">
            <div>
                <p class="text-sm font-extrabold text-slate">{{ __('Issued') }}</p>
                <p class="text-lg font-black">{{ $certificate->issued_at->translatedFormat('j F Y') }}</p>
                <p class="mt-2 text-sm font-extrabold text-slate">{{ __('Certificate number') }}</p>
                <p class="font-receipt text-lg">{{ $certificate->number }}</p>
                @unless ($certificate->isValid())<p class="mt-2 text-lg font-black text-danger">{{ __('Withdrawn') }}</p>@endunless
            </div>
            <div class="text-center">
                <div class="inline-block rounded-xl border-2 border-ink bg-white p-2 [&_svg]:size-32" role="img" aria-label="{{ __('QR code to check this certificate') }}">{!! $qr !!}</div>
                <p class="mt-1 text-xs font-bold">{{ __('Scan to check') }}</p>
            </div>
        </div>
    </article>
</div>
