@php
    use App\Support\Certificates;
    use App\Support\Locales;
@endphp
<x-layouts.public :title="__('Check a certificate')" :description="__('Check that a TRA Tax Club certificate is genuine.')" :noindex="true">
    <section class="sun-hatch border-b-4 border-ink">
        <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
            <h1 class="text-4xl font-black tracking-tight sm:text-5xl">{{ __('Check a certificate') }}</h1>
            <p class="mt-3 text-lg font-semibold text-ink-soft">{{ __('Scan the QR code on the certificate, or type its number.') }}</p>
            <form method="get" action="{{ Locales::route('certificates.verify') }}" class="mt-8 flex max-w-xl gap-2 rounded-2xl bg-white p-2 shadow-[6px_6px_0_var(--color-ink)]">
                <label for="number" class="sr-only">{{ __('Certificate number') }}</label>
                <input id="number" name="number" type="text" value="{{ $number }}" placeholder="TCC-26-AB12-CD34" class="min-h-12 w-full rounded-xl px-4 font-receipt text-lg">
                <button type="submit" class="btn-primary shrink-0">{{ __('Check') }}</button>
            </form>
        </div>
    </section>
    <div class="mx-auto max-w-3xl px-4 pt-10 sm:px-6">
        @if ($checked)
            @if (! $certificate)
                <p class="rounded-3xl border-4 border-danger bg-[#fef3f2] p-6 text-2xl font-black text-danger" role="status">{{ __('Certificate not found') }}</p>
            @else
                <section @class(['rounded-3xl border-4 p-6', 'border-savanna bg-white' => $certificate->isValid(), 'border-danger bg-[#fef3f2]' => ! $certificate->isValid()]) role="status">
                    <p @class(['text-2xl font-black', 'text-savanna' => $certificate->isValid(), 'text-danger' => ! $certificate->isValid()])>{{ $certificate->isValid() ? __('Genuine certificate') : __('This certificate was withdrawn') }}</p>
                    <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div><dt class="text-sm font-extrabold text-slate">{{ __('Awarded to') }}</dt><dd class="text-lg font-black">{{ $certificate->user?->publicName() }}</dd></div>
                        <div><dt class="text-sm font-extrabold text-slate">{{ __('For') }}</dt><dd class="text-lg font-black">{{ $certificate->title() }}: {{ $certificate->subject }}</dd></div>
                        <div><dt class="text-sm font-extrabold text-slate">{{ __('Issued') }}</dt><dd class="text-lg font-black">{{ $certificate->issued_at->translatedFormat('j F Y') }}</dd></div>
                        <div><dt class="text-sm font-extrabold text-slate">{{ __('Number') }}</dt><dd class="font-receipt text-lg">{{ $certificate->number }}</dd></div>
                    </dl>
                </section>
            @endif
        @endif
    </div>
</x-layouts.public>
