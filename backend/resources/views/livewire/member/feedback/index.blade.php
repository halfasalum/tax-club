@php
    use App\Support\FeedbackDesk;

    $categories = FeedbackDesk::categoryLabels();
    $statuses = FeedbackDesk::statusLabels();
@endphp
<div class="mx-auto max-w-4xl">
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Feedback') }}</h1>
    <p class="mt-1 text-lg text-slate">{{ __('Tell us what works, what does not and what you would like to see. Every message gets a reference number so you can follow it.') }}</p>

    <form wire:submit="send" class="mt-8 space-y-5 rounded-3xl border-2 border-line bg-white p-6 sm:p-8" novalidate>
        @if ($relatedLabel)
            <p class="rounded-xl bg-brand-wash p-3 font-bold">{{ __('About: :item', ['item' => $relatedLabel]) }}</p>
        @endif
        <fieldset>
            <legend class="font-extrabold">{{ __('What is it about?') }}</legend>
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                @foreach ($categories as $value => $label)
                    <label class="flex items-center gap-2 rounded-xl border-2 border-line px-3 py-2 font-bold has-[:checked]:border-ink has-[:checked]:bg-brand-wash">
                        <input type="radio" wire:model.live="category" value="{{ $value }}" class="size-4 accent-ink"> {{ $label }}
                    </label>
                @endforeach
            </div>
        </fieldset>
        @if ($category === 'concern')
            <p class="rounded-xl bg-[#fef3f2] p-4 font-bold text-danger">{{ __('Safety concerns go straight to a trained safeguarding officer and are kept confidential. If someone is in immediate danger, call 112 or tell a teacher or patron now.') }}</p>
        @endif
        <div>
            <label for="subject" class="block font-extrabold">{{ __('Subject') }}</label>
            <input wire:model="subject" id="subject" type="text" maxlength="200" @class(['field', 'field-error' => $errors->has('subject')])>
            @error('subject')<p class="mt-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="message" class="block font-extrabold">{{ __('Your message') }}</label>
            <textarea wire:model="message" id="message" rows="5" maxlength="5000" @class(['field', 'field-error' => $errors->has('message')])></textarea>
            @error('message')<p class="mt-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
        </div>
        @error('send')<p class="rounded-xl bg-[#fef3f2] p-3 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
        <button type="submit" class="btn-primary" wire:loading.attr="disabled">{{ __('Send feedback') }}</button>
    </form>

    <h2 class="mt-12 text-2xl font-black">{{ __('Feedback you sent') }}</h2>
    <ul class="mt-4 divide-y divide-line rounded-2xl border-2 border-line bg-white">
        @forelse ($feedback as $item)
            <li>
                <a wire:navigate href="{{ route('member.feedback.show', $item->reference) }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 hover:bg-brand-wash/50">
                    <span>
                        <span class="block font-bold">{{ $item->subject }}</span>
                        <span class="text-sm text-slate"><span class="font-receipt">{{ $item->reference }}</span> · {{ $item->created_at->translatedFormat('j M Y') }}</span>
                    </span>
                    <span @class(['rounded-full px-2.5 py-0.5 text-sm font-extrabold', 'bg-savanna text-white' => $item->status === 'responded', 'bg-mist' => $item->status !== 'responded'])>{{ $statuses[$item->status] ?? $item->status }}</span>
                </a>
            </li>
        @empty
            <li class="px-5 py-6 text-slate">{{ __('You have not sent any feedback yet.') }}</li>
        @endforelse
    </ul>
</div>
