@php($statuses = \App\Support\AskTra::statusLabels())
<div class="mx-auto max-w-3xl">
    <a wire:navigate href="{{ route('member.ask') }}" class="link">← {{ __('Ask TRA') }}</a>
    <p class="mt-4 font-receipt text-slate">{{ $question->reference }} · {{ $question->created_at->translatedFormat('j M Y') }}</p>
    <h1 class="text-3xl font-black tracking-tight">{{ $question->subject }}</h1>
    <p class="mt-2"><span class="rounded-full bg-ink px-3 py-1 text-sm font-extrabold text-brand">{{ $statuses[$question->status] ?? $question->status }}</span></p>

    @if ($statusMessage)<p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>@endif

    <section class="mt-6 rounded-3xl border-2 border-line bg-white p-6">
        <h2 class="font-black">{{ __('Your question') }}</h2>
        <p class="mt-2 whitespace-pre-line">{{ $question->body }}</p>
    </section>

    @if ($question->status === 'answered')
        <section class="mt-6 rounded-3xl border-4 border-savanna bg-white p-6">
            <h2 class="text-xl font-black">{{ __('Answer from TRA') }}</h2>
            <div class="mt-2 whitespace-pre-line text-lg leading-relaxed">{{ $question->answer }}</div>
            <p class="mt-3 text-sm text-slate">{{ $question->delivered_at?->translatedFormat('j M Y') }}@if ($question->published_at) · {{ __('Also published, without your name, to help other members.') }}@endif</p>
        </section>
    @elseif ($question->status === 'declined')
        <section class="mt-6 rounded-3xl border-2 border-line bg-mist p-6">
            <h2 class="font-black">{{ __('Why we could not answer') }}</h2>
            <p class="mt-2 whitespace-pre-line">{{ $question->decline_reason }}</p>
        </section>
    @else
        <p class="mt-6 rounded-2xl bg-brand-wash p-5 font-bold">{{ __('A TRA expert will answer. We will tell you by SMS and in your notifications.') }}</p>
    @endif
</div>
