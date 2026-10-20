@php
    use App\Models\Question;

    $statuses = \App\Support\AskTra::statusLabels();
    $open = in_array($question->status, Question::OPEN, true);
@endphp
<div>
    <a wire:navigate href="{{ route('admin.questions.index') }}" class="link">← {{ __('Ask TRA') }}</a>
    <div class="mt-4">
        <p class="font-receipt text-slate">{{ $question->reference }} · {{ $question->created_at->translatedFormat('j M Y H:i') }} · {{ strtoupper($question->language) }}</p>
        <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ $question->subject }}</h1>
        <p class="mt-2 flex flex-wrap items-center gap-2">
            <span class="rounded-full bg-ink px-2.5 py-0.5 text-xs font-extrabold text-brand">{{ $statuses[$question->status] ?? $question->status }}</span>
            @if ($question->published_at)<span class="rounded-full bg-savanna px-2.5 py-0.5 text-xs font-extrabold text-white">{{ __('Published') }}</span>@endif
            @unless ($question->allow_publish)<span class="rounded-full bg-mist px-2.5 py-0.5 text-xs font-extrabold">{{ __('Member asked not to publish') }}</span>@endunless
        </p>
    </div>

    @if ($statusMessage)<p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>@endif
    @error('action')<p class="mt-6 rounded-2xl bg-[#fef3f2] px-5 py-4 font-bold text-danger" role="alert">{{ $message }}</p>@enderror

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="min-w-0 space-y-6">
            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <p class="whitespace-pre-line text-lg">{{ $question->body }}</p>
                <p class="mt-4 rounded-xl bg-mist p-3 text-sm">{{ __('Asked by') }} <span class="font-bold">{{ $asker?->publicName() }}</span>@if ($asker?->institution) · {{ $asker->institution->name }}@endif @if ($asker?->isMinor()) · {{ __('Under 18') }}@endif</p>
            </section>

            @if ($can['answer'] && in_array($question->status, [Question::ASSIGNED, Question::RETURNED], true))
                <form wire:submit="saveAnswer" class="space-y-3 rounded-3xl border-4 border-ink bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Your answer') }}</h2>
                    @if ($question->review_note)<p class="rounded-xl bg-[#fef3f2] p-3"><span class="font-extrabold">{{ __('Reviewer notes') }}:</span> {{ $question->review_note }}</p>@endif
                    <p class="text-sm text-slate">{{ __('Write simply for a student. Name the law or TRA guide where it helps.') }}</p>
                    <textarea wire:model="answer" rows="10" class="field" aria-label="{{ __('Your answer') }}"></textarea>
                    @error('answer')<p class="font-semibold text-danger">{{ $message }}</p>@enderror
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="btn-ghost">{{ __('Save draft') }}</button>
                        <button type="button" wire:click="saveAnswer(true)" class="btn-primary">{{ __('Send for review') }}</button>
                    </div>
                </form>
            @elseif ($question->answer)
                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Answer') }}</h2>
                    <p class="mt-2 whitespace-pre-line text-lg">{{ $question->answer }}</p>
                    <p class="mt-2 text-sm text-slate">{{ $question->expert?->full_name }} · {{ $question->answered_at?->translatedFormat('j M Y H:i') }}</p>
                </section>
            @endif

            @if ($question->decline_reason)
                <section class="rounded-3xl border-2 border-line bg-mist p-6"><h2 class="font-black">{{ __('Closed without an answer') }}</h2><p class="mt-2">{{ $question->decline_reason }}</p></section>
            @endif

            @if ($can['review'] && ($question->status === Question::IN_REVIEW || ($question->status === Question::ANSWERED && $question->allow_publish)))
                <section class="space-y-3 rounded-3xl border-4 border-savanna bg-white p-6">
                    <h2 class="text-xl font-black">{{ $question->status === Question::IN_REVIEW ? __('Check the answer') : __('Knowledge base') }}</h2>
                    @if ($question->allow_publish)
                        @if ($question->status === Question::IN_REVIEW)
                            <label class="flex items-center gap-2 font-bold"><input type="checkbox" wire:model.live="publish" class="size-4 accent-ink"> {{ __('Also publish to the knowledge base, without the member\'s name') }}</label>
                        @endif
                        @if ($publish || $question->status === Question::ANSWERED)
                            <div>
                                <label for="publicQuestion" class="block font-bold">{{ __('Question as published') }}</label>
                                <input wire:model="publicQuestion" id="publicQuestion" type="text" class="field">
                            </div>
                            <div>
                                <label for="publicAnswer" class="block font-bold">{{ __('Answer as published') }}</label>
                                <textarea wire:model="publicAnswer" id="publicAnswer" rows="6" class="field"></textarea>
                                <p class="mt-1 text-sm text-slate">{{ __('Remove anything that could identify the member.') }}</p>
                            </div>
                        @endif
                    @endif
                    <div class="flex flex-wrap gap-2">
                        @if ($question->status === Question::IN_REVIEW)
                            <button type="button" wire:click="approve" class="btn-primary">{{ __('Approve and send') }}</button>
                        @elseif (! $question->published_at)
                            <button type="button" wire:click="publishNow" class="btn-primary">{{ __('Publish') }}</button>
                        @endif
                    </div>
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            @if ($can['triage'] && $open)
                <section class="space-y-3 rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Triage') }}</h2>
                    <label for="category_id" class="block font-bold">{{ __('Topic') }}</label>
                    <select wire:model="category_id" id="category_id" class="field mt-0">
                        <option value="">—</option>
                        @foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name() }}</option>@endforeach
                    </select>
                    <button type="button" wire:click="categorise" class="btn-ghost w-full">{{ __('Save topic') }}</button>
                    <label for="expert" class="block pt-2 font-bold">{{ __('Expert') }}</label>
                    <select wire:model="expert" id="expert" class="field mt-0">
                        <option value="">{{ __('Choose an expert') }}</option>
                        @foreach ($experts as $person)<option value="{{ $person->user_id }}">{{ $person->full_name }}</option>@endforeach
                    </select>
                    <button type="button" wire:click="assign" class="btn-primary w-full">{{ __('Assign') }}</button>
                </section>
            @endif
            @if ($can['takeIt'])
                <button type="button" wire:click="takeIt" class="btn-brand w-full">{{ __('I will answer this') }}</button>
            @endif

            @if (($can['triage'] || $can['review']) && ($open || $question->published_at))
                <section class="space-y-3 rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ $question->published_at ? __('Unpublish') : ($question->status === Question::IN_REVIEW ? __('Return or close') : __('Close without answering')) }}</h2>
                    <label for="note" class="block font-bold">{{ __('Reason or notes') }}</label>
                    <textarea wire:model="note" id="note" rows="3" class="field mt-0"></textarea>
                    @if ($question->published_at)
                        <button type="button" wire:click="unpublish" class="btn-ghost w-full">{{ __('Remove from the knowledge base') }}</button>
                    @else
                        @if ($question->status === Question::IN_REVIEW && $can['review'])<button type="button" wire:click="returnAnswer" class="btn-ghost w-full">{{ __('Return to the expert') }}</button>@endif
                        <button type="button" wire:click="decline" wire:confirm="{{ __('Close this question and tell the member why?') }}" class="btn w-full border-2 border-line hover:border-danger hover:text-danger">{{ __('Close without answering') }}</button>
                    @endif
                </section>
            @endif
        </aside>
    </div>
</div>
