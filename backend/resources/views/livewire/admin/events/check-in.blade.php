@php
    $styles = [
        'checked_in' => ['bg-savanna text-white', __('Checked in')],
        'already' => ['bg-ink text-brand', __('Already checked in')],
        'not_registered' => ['bg-brand text-ink', __('Not registered for this event')],
        'not_valid' => ['bg-danger text-white', __('Membership not valid')],
        'not_found' => ['bg-danger text-white', __('Card not recognised')],
    ];
@endphp
<div class="mx-auto max-w-3xl">
    <a wire:navigate href="{{ route('admin.events.show', $event) }}" class="link">← {{ __('Back to the event') }}</a>
    <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="font-receipt text-slate">{{ $event->code }}</p>
            <h1 class="text-3xl font-black tracking-tight">{{ __('Check-in') }}</h1>
            <p class="mt-1 text-lg font-bold">{{ $event->title }}</p>
        </div>
        <p class="rounded-2xl bg-ink px-5 py-3 text-white" aria-live="polite">
            <span class="text-3xl font-black tabular-nums text-brand">{{ number_format($attended) }}</span>
            <span class="font-bold">{{ __('of :count expected', ['count' => number_format($expected)]) }}</span>
        </p>
    </div>

    @unless ($open)
        <p class="mt-6 rounded-2xl bg-[#fef3f2] p-5 font-bold text-danger">{{ __('Check-in is open from :hours hours before the event starts until it ends.', ['hours' => \App\Support\EventManager::CHECK_IN_OPENS_HOURS]) }}</p>
    @endunless

    <section class="mt-6 rounded-3xl border-4 border-ink bg-white p-6"
             x-data="cardScanner($wire)" x-on:checked.window="beep($event.detail.result)">
        <form wire:submit="check()" class="flex flex-wrap items-end gap-3">
            <div class="min-w-0 flex-1">
                <label for="input" class="block font-extrabold">{{ __('Scan the card QR code or type the membership number') }}</label>
                <input wire:model="input" id="input" type="text" autocomplete="off" autofocus x-ref="input"
                       placeholder="TC-26-DSM-000123-4" @class(['field font-receipt text-lg', 'field-error' => $errors->has('input')]) @disabled(! $open)>
            </div>
            <button type="submit" class="btn-primary" @disabled(! $open)>{{ __('Check in') }}</button>
            <button type="button" x-show="supported" x-cloak x-on:click="toggle()" class="btn-ghost" @disabled(! $open)>
                <span x-text="scanning ? '{{ __('Stop camera') }}' : '{{ __('Use camera') }}'"></span>
            </button>
        </form>
        @error('input')<p class="mt-3 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
        <video x-ref="video" x-show="scanning" x-cloak class="mt-4 aspect-video w-full rounded-2xl bg-ink object-cover" playsinline muted></video>
        <p x-show="! supported" class="mt-3 text-sm text-slate">{{ __('Tip: a handheld QR scanner works like a keyboard. Click the box and scan.') }}</p>
    </section>

    @if ($outcome)
        @php
            [$classes, $label] = $styles[$outcome['result']] ?? ['bg-mist', $outcome['result']];
        @endphp
        <section class="mt-6 overflow-hidden rounded-3xl border-4 border-ink bg-white" role="status" aria-live="assertive">
            <p class="{{ $classes }} px-6 py-4 text-2xl font-black">{{ $label }}</p>
            @if ($outcome['name'])
                <div class="flex items-center gap-4 p-6">
                    @if ($outcome['photo'])
                        <img src="{{ $outcome['photo'] }}" alt="" class="size-20 rounded-2xl border-2 border-ink object-cover">
                    @endif
                    <div>
                        <p class="text-2xl font-black">{{ $outcome['name'] }}</p>
                        <p class="font-receipt text-slate">{{ $outcome['number'] }}</p>
                        <p class="font-bold text-ink-soft">{{ $outcome['institution'] }}</p>
                        @if ($outcome['minor'])<p class="mt-1 inline-block rounded-full bg-brand px-2.5 py-0.5 text-sm font-extrabold">{{ __('Under 18') }}</p>@endif
                    </div>
                </div>
            @endif
            @if ($pending)
                <div class="flex flex-wrap gap-3 border-t-2 border-line p-6">
                    <button type="button" wire:click="admitWalkIn" class="btn-brand">{{ __('Admit as walk-in') }}</button>
                    <button type="button" wire:click="$set('pending', null)" class="btn-ghost">{{ __('Do not admit') }}</button>
                </div>
            @endif
        </section>
    @endif

    <section class="mt-6 rounded-3xl border-2 border-line bg-white p-6">
        <h2 class="text-xl font-black">{{ __('Latest check-ins') }}</h2>
        <ul class="mt-3 divide-y divide-line">
            @forelse ($recent as $registration)
                <li class="flex flex-wrap justify-between gap-2 py-2">
                    <span class="font-bold">{{ $registration->user?->full_name }} <span class="font-receipt text-sm font-normal text-slate">{{ $registration->user?->membership_id }}</span></span>
                    <span class="text-slate">{{ $registration->checked_in_at?->format('H:i') }}</span>
                </li>
            @empty
                <li class="py-2 text-slate">{{ __('Nobody checked in yet.') }}</li>
            @endforelse
        </ul>
    </section>
</div>

@script
<script>
    Alpine.data('cardScanner', (wire) => ({
        supported: 'BarcodeDetector' in window && !!navigator.mediaDevices?.getUserMedia,
        scanning: false,
        stream: null,
        detector: null,
        busy: false,
        async toggle() {
            this.scanning ? this.stop() : await this.start();
        },
        async start() {
            try {
                this.detector ??= new BarcodeDetector({ formats: ['qr_code'] });
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                this.scanning = true;
                this.loop();
            } catch (error) {
                this.scanning = false;
            }
        },
        stop() {
            this.stream?.getTracks().forEach((track) => track.stop());
            this.stream = null;
            this.scanning = false;
        },
        async loop() {
            if (!this.scanning) return;
            if (!this.busy) {
                try {
                    const codes = await this.detector.detect(this.$refs.video);
                    if (codes.length) {
                        this.busy = true;
                        wire.input = codes[0].rawValue;
                        await wire.check();
                        setTimeout(() => (this.busy = false), 1500);
                    }
                } catch (error) {}
            }
            requestAnimationFrame(() => this.loop());
        },
        beep(result) {
            try {
                const audio = new AudioContext();
                const tone = audio.createOscillator();
                tone.frequency.value = result === 'checked_in' ? 880 : 220;
                tone.connect(audio.destination);
                tone.start();
                tone.stop(audio.currentTime + 0.15);
            } catch (error) {}
            this.$refs.input?.focus();
        },
        destroy() {
            this.stop();
        },
    }));
</script>
@endscript
