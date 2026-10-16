@props(['items' => [], 'number' => null])
{{-- An EFD-style receipt listing what membership includes. Decorative, so it is
     summarised for screen readers by the visually hidden list below it. --}}
<figure {{ $attributes->merge(['class' => 'receipt-shadow relative mx-auto w-full max-w-[22rem]']) }}>
    <div class="receipt receipt-print bg-white px-7 pt-7 font-receipt text-[0.82rem] leading-6 text-ink">
        <div class="text-center" aria-hidden="true">
            <p class="text-base font-semibold">TRA TAX CLUB</p>
            <p>{{ __('Membership receipt') }}</p>
            <p class="text-slate">{{ __('Receipt no.') }} {{ $number ?? 'TC-'.now()->format('y').'-000001' }}</p>
        </div>

        <div class="my-4 border-t border-dashed border-ink/40" aria-hidden="true"></div>

        <ul class="space-y-1.5" aria-hidden="true">
            @foreach ($items as $item)
                <li class="flex items-end gap-2">
                    <span>{{ $item }}</span>
                    <span class="receipt-leader mb-1.5 h-2 flex-1"></span>
                    <span>0.00</span>
                </li>
            @endforeach
        </ul>

        <div class="my-4 border-t border-dashed border-ink/40" aria-hidden="true"></div>

        <div class="flex items-baseline justify-between text-base font-semibold" aria-hidden="true">
            <span>{{ __('Total') }}</span>
            <span>TSh 0.00</span>
        </div>
        <p class="mt-1 text-slate" aria-hidden="true">{{ __('Membership is free for students.') }}</p>

        <div class="mt-5 flex h-10 items-stretch justify-center gap-[2px]" aria-hidden="true">
            @foreach ([3, 1, 2, 1, 1, 3, 2, 1, 3, 1, 2, 2, 1, 3, 1, 1, 2, 3, 1, 2, 1, 3, 2, 1, 1, 2, 3, 1] as $bar)
                <span class="bg-ink" style="width: {{ $bar }}px"></span>
            @endforeach
        </div>
        <p class="mt-3 text-center text-[0.75rem] text-slate" aria-hidden="true">{{ __('Ask for a receipt every time you buy.') }}</p>
    </div>

    <figcaption class="sr-only">
        {{ __('Tax Club membership is free and includes:') }}
        <ul>
            @foreach ($items as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </figcaption>
</figure>
