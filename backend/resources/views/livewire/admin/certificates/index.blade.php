@php($kinds = \App\Support\Certificates::kindLabels())
<div>
    <a wire:navigate href="{{ route('admin.competitions.index') }}" class="link">← {{ __('Competitions') }}</a>
    <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ __('Certificates') }}</h1>
    <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Certificates are issued automatically for completed modules, attended events and competition results. Anyone can check one with its QR code or number.') }}</p>

    @if ($statusMessage)<p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>@endif

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_22rem]">
        <section class="min-w-0">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search by number, member or subject') }}" class="field mt-0" aria-label="{{ __('Search') }}">
            <div class="mt-4 overflow-x-auto rounded-2xl border-2 border-line bg-white">
                <table class="w-full min-w-[40rem] text-left">
                    <thead class="border-b-2 border-line bg-mist text-sm"><tr><th scope="col" class="px-4 py-3 font-extrabold">{{ __('Certificate') }}</th><th scope="col" class="px-4 py-3 font-extrabold">{{ __('Member') }}</th><th scope="col" class="px-4 py-3 font-extrabold">{{ __('Status') }}</th><th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($certificates as $certificate)
                            <tr wire:key="cert-{{ $certificate->certificate_id }}">
                                <td class="px-4 py-3"><span class="font-bold">{{ $certificate->subject }}</span><span class="block text-sm text-slate"><span class="font-receipt">{{ $certificate->number }}</span> · {{ $kinds[$certificate->kind] ?? '' }} · {{ $certificate->issued_at->translatedFormat('j M Y') }}</span></td>
                                <td class="px-4 py-3">{{ $certificate->user?->full_name }}</td>
                                <td class="px-4 py-3 font-bold">{{ $certificate->isValid() ? __('Valid') : __('Withdrawn') }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($certificate->isValid())
                                        @if ($revoking === $certificate->certificate_id)
                                            <form wire:submit="revoke" class="flex flex-wrap justify-end gap-2"><input wire:model="reason" type="text" class="field mt-0 w-48" placeholder="{{ __('Reason') }}" aria-label="{{ __('Reason') }}"><button type="submit" class="btn-ghost">{{ __('Withdraw') }}</button></form>
                                            @error('reason')<p class="mt-1 font-semibold text-danger">{{ $message }}</p>@enderror
                                        @else
                                            <button type="button" wire:click="$set('revoking', '{{ $certificate->certificate_id }}')" class="link">{{ __('Withdraw') }}</button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-10 text-center font-bold text-slate">{{ __('No certificates yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $certificates->links() }}</div>
        </section>
        <form wire:submit="issue" class="h-fit space-y-3 rounded-3xl border-4 border-ink bg-white p-6">
            <h2 class="text-xl font-black">{{ __('Issue a certificate') }}</h2>
            <input wire:model="number" type="text" class="field mt-0 font-receipt" placeholder="{{ __('Membership no.') }}" aria-label="{{ __('Membership no.') }}">
            @error('number')<p class="font-semibold text-danger">{{ $message }}</p>@enderror
            <input wire:model="titleSw" type="text" class="field mt-0" aria-label="{{ __('Title in Kiswahili') }}">
            <input wire:model="titleEn" type="text" class="field mt-0" aria-label="{{ __('Title in English') }}">
            <input wire:model="subject" type="text" class="field mt-0" placeholder="{{ __('For example: Best Tax Club patron helper 2026') }}" aria-label="{{ __('What it is for') }}">
            @error('subject')<p class="font-semibold text-danger">{{ $message }}</p>@enderror
            <button type="submit" class="btn-primary w-full">{{ __('Issue') }}</button>
        </form>
    </div>
</div>
