@php($statuses = \App\Support\AskTra::statusLabels())
<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Ask TRA') }}</h1>
    <p class="mt-1 max-w-[65ch] text-lg text-slate">{{ __('Ask a TRA tax expert anything about taxes. You get a private answer, usually within a few working days.') }}</p>

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_24rem]">
        <section class="min-w-0">
            <h2 class="text-2xl font-black">{{ __('Answers from TRA') }}</h2>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search answers, e.g. TIN or VAT') }}" class="field" aria-label="{{ __('Search answers') }}">
            <ul class="mt-4 space-y-3" wire:loading.class="opacity-60">
                @forelse ($answers as $answer)
                    <li wire:key="kb-{{ $answer->question_id }}">
                        <a wire:navigate href="{{ route('member.ask.answer', $answer->slug) }}" class="block rounded-2xl border-2 border-line bg-white p-5 transition hover:border-ink">
                            <span class="block text-lg font-black leading-snug">{{ $answer->public_question }}</span>
                            <span class="mt-1 block text-slate">{{ \Illuminate\Support\Str::limit(strip_tags($answer->public_answer), 160) }}</span>
                            @if ($answer->category)<span class="mt-2 inline-block rounded-full bg-mist px-2.5 py-0.5 text-xs font-extrabold">{{ $answer->category->name() }}</span>@endif
                        </a>
                    </li>
                @empty
                    <li class="rounded-2xl bg-white p-6 font-bold text-slate">{{ $search !== '' ? __('No answers match. Ask your own question!') : __('Published answers will appear here.') }}</li>
                @endforelse
            </ul>
            <div class="mt-4">{{ $answers->links() }}</div>
        </section>

        <aside class="space-y-6">
            <form wire:submit="ask" class="space-y-4 rounded-3xl border-4 border-ink bg-white p-6" novalidate>
                <h2 class="text-xl font-black">{{ __('Ask a question') }}</h2>
                <div>
                    <label for="subject" class="block font-bold">{{ __('Your question in one line') }}</label>
                    <input wire:model="subject" id="subject" type="text" maxlength="200" @class(['field', 'field-error' => $errors->has('subject')])>
                    @error('subject')<p class="mt-1 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="body" class="block font-bold">{{ __('Details') }}</label>
                    <textarea wire:model="body" id="body" rows="5" maxlength="3000" @class(['field', 'field-error' => $errors->has('body')])></textarea>
                    @error('body')<p class="mt-1 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="category_id" class="block font-bold">{{ __('Topic') }} <span class="font-semibold text-slate">({{ __('optional') }})</span></label>
                    <select wire:model="category_id" id="category_id" class="field">
                        <option value="">{{ __('Not sure') }}</option>
                        @foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name() }}</option>@endforeach
                    </select>
                </div>
                <label class="flex items-start gap-2"><input type="checkbox" wire:model="allow_publish" class="mt-1 size-4 accent-ink"> <span class="font-bold">{{ __('TRA may publish my question and the answer without my name, to help other members') }}</span></label>
                <p class="text-sm text-slate">{{ __('Do not include your TIN, phone number or other private details.') }}</p>
                <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">{{ __('Send to TRA') }}</button>
            </form>

            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ __('Your questions') }}</h2>
                <ul class="mt-3 divide-y divide-line">
                    @forelse ($mine as $question)
                        <li class="py-2">
                            <a wire:navigate href="{{ route('member.ask.show', $question->reference) }}" class="block hover:underline">
                                <span class="block font-bold">{{ $question->subject }}</span>
                                <span class="text-sm text-slate"><span class="font-receipt">{{ $question->reference }}</span> · {{ $statuses[$question->status] ?? $question->status }}</span>
                            </a>
                        </li>
                    @empty
                        <li class="py-2 text-slate">{{ __('You have not asked anything yet.') }}</li>
                    @endforelse
                </ul>
            </section>
        </aside>
    </div>
</div>
