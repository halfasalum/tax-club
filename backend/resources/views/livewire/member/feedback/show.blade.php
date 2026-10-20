@php
    use App\Support\FeedbackDesk;

    $statuses = FeedbackDesk::statusLabels();
    $steps = ['submitted' => __('Received'), 'responded' => __('Answered'), 'closed' => __('Closed'), 'member_message' => __('You added a message')];
    $order = ['submitted' => 0, 'in_review' => 1, 'responded' => 2, 'closed' => 3];
    $current = $order[$feedback->status] ?? 0;
@endphp
<div class="mx-auto max-w-3xl">
    <a wire:navigate href="{{ route('member.feedback') }}" class="link">← {{ __('Feedback') }}</a>
    <p class="mt-4 font-receipt text-slate">{{ $feedback->reference }}</p>
    <h1 class="text-3xl font-black tracking-tight">{{ $feedback->subject }}</h1>
    <p class="mt-1 text-slate">{{ FeedbackDesk::categoryLabels()[$feedback->category] ?? '' }}@if ($relatedLabel) · {{ $relatedLabel }}@endif · {{ $feedback->created_at->translatedFormat('j M Y') }}</p>

    @if ($statusMessage)
        <p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>
    @endif

    <ol class="mt-6 grid grid-cols-4 gap-2" aria-label="{{ __('Status') }}">
        @foreach (['submitted', 'in_review', 'responded', 'closed'] as $index => $status)
            <li @class(['rounded-xl px-2 py-2 text-center text-sm font-extrabold', 'bg-ink text-brand' => $index === $current, 'bg-savanna text-white' => $index < $current, 'bg-mist text-slate' => $index > $current]) @if ($index === $current) aria-current="step" @endif>{{ $statuses[$status] }}</li>
        @endforeach
    </ol>

    <section class="mt-6 rounded-3xl border-2 border-line bg-white p-6">
        <h2 class="font-black">{{ __('Your message') }}</h2>
        <p class="mt-2 whitespace-pre-line">{{ $feedback->message }}</p>
    </section>

    @if ($feedback->response)
        <section class="mt-6 rounded-3xl border-4 border-savanna bg-white p-6">
            <h2 class="font-black">{{ __('Response from TRA Tax Club') }}</h2>
            <p class="mt-2 whitespace-pre-line">{{ $feedback->response }}</p>
            <p class="mt-2 text-sm text-slate">{{ $feedback->responded_at?->translatedFormat('j M Y, H:i') }}</p>
        </section>
    @endif

    <section class="mt-6 rounded-3xl border-2 border-line bg-white p-6">
        <h2 class="font-black">{{ __('History') }}</h2>
        <ol class="mt-3 space-y-3 border-l-4 border-brand pl-4">
            @foreach ($history as $step)
                <li>
                    <p class="font-extrabold">{{ $steps[$step->action] ?? $step->action }}</p>
                    <p class="text-sm text-slate">{{ $step->created_at->translatedFormat('j M Y H:i') }}</p>
                    @if ($step->action === 'member_message')<p class="text-sm">{{ $step->note }}</p>@endif
                </li>
            @endforeach
        </ol>
    </section>

    @if ($feedback->isOpen())
        <form wire:submit="sendReply" class="mt-6 rounded-3xl border-2 border-line bg-white p-6">
            <label for="reply" class="block font-extrabold">{{ __('Add more information') }}</label>
            <textarea wire:model="reply" id="reply" rows="3" class="field"></textarea>
            @error('reply')<p class="mt-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
            <button type="submit" class="btn-ghost mt-3">{{ __('Send') }}</button>
        </form>
    @endif
</div>
