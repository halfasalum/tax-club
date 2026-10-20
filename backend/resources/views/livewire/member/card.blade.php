@php use App\Support\MembershipCard; @endphp
<div class="mx-auto max-w-xl">
    <div class="print:hidden">
        <a wire:navigate href="{{ route('member.dashboard') }}" class="link">← {{ __('My Tax Club') }}</a>
        <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ __('My membership card') }}</h1>
        <p class="mt-2 text-lg text-slate">{{ __('Show this card at Tax Club events. Officers scan the QR code to check that your membership is active.') }}</p>
    </div>

    @if ($statusMessage)
        <p class="mt-6 rounded-xl bg-savanna px-4 py-3 font-extrabold text-white print:hidden" role="status">{{ $statusMessage }}</p>
    @endif

    <x-membership-card :member="$member" :qr="$qr" class="mt-8" />

    @if (MembershipCard::isValid($member))
        <div class="mt-8 flex flex-col gap-3 sm:flex-row print:hidden">
            <button type="button" x-data x-on:click="window.print()" class="btn-primary flex-1">{{ __('Print or save as PDF') }}</button>
            <a href="{{ MembershipCard::verifyUrl($member) }}" target="_blank" rel="noopener" class="btn-ghost flex-1">{{ __('Test my QR code') }}</a>
        </div>

        <details class="mt-8 rounded-2xl border-2 border-line bg-white p-5 print:hidden">
            <summary class="cursor-pointer text-lg font-extrabold">{{ __('Lost your card or shared a photo of it?') }}</summary>
            <p class="mt-3 text-slate">{{ __('Get a new QR code. Your membership number stays the same, but printouts and photos of your old card will stop verifying.') }}</p>
            <button type="button" wire:click="reissue" wire:confirm="{{ __('Replace your QR code? Old copies of your card will stop working.') }}" class="btn-ghost mt-4">{{ __('Get a new QR code') }}</button>
        </details>
    @else
        <p class="mt-8 rounded-2xl bg-brand-wash p-5 text-lg font-bold print:hidden">{{ __('Your card can be used once your membership is active. Contact your Tax Club patron if you think this is a mistake.') }}</p>
    @endif
</div>
