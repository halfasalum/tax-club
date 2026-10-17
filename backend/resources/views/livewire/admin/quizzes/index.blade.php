@php
    $views = ['all' => __('All'), 'review' => __('Waiting for review'), 'drafts' => __('Drafts'), 'open' => __('Open now'), 'scheduled' => __('Scheduled'), 'closed' => __('Closed and archived')];
@endphp
<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Quizzes') }}</h1>
            <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Build quizzes, send them for review and open them to members for a set period.') }}</p>
        </div>
        @if ($canCreate)<a href="{{ route('admin.quizzes.create') }}" class="btn-brand">{{ __('New quiz') }}</a>@endif
    </div>

    <div class="mt-6 flex flex-wrap gap-1 rounded-2xl bg-white p-1.5 shadow-sm" role="tablist" aria-label="{{ __('Status') }}">
        @foreach ($views as $value => $label)
            <button type="button" role="tab" wire:click="$set('view', '{{ $value }}')" aria-selected="{{ $view === $value ? 'true' : 'false' }}"
                    @class(['rounded-xl px-4 py-2 font-extrabold transition-colors', 'bg-ink text-brand' => $view === $value, 'text-slate hover:bg-brand-wash hover:text-ink' => $view !== $value])>
                {{ $label }}@if ($value === 'review' && $waiting > 0)<span class="ml-1 rounded-full bg-brand px-2 text-sm text-ink">{{ $waiting }}</span>@endif
            </button>
        @endforeach
    </div>
    <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search by title or code') }}" class="field mt-5 md:max-w-md" aria-label="{{ __('Search by title or code') }}">

    <div class="mt-6 overflow-x-auto rounded-2xl border-2 border-line bg-white" wire:loading.class="opacity-60">
        <table class="w-full min-w-[48rem] text-left">
            <thead class="border-b-2 border-line bg-mist text-sm">
                <tr>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Quiz') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Review') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Members') }}</th>
                    <th scope="col" class="px-4 py-3 text-right font-extrabold">{{ __('Questions') }}</th>
                    <th scope="col" class="px-4 py-3 text-right font-extrabold">{{ __('Attempts') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($this->quizzes as $quiz)
                    <tr wire:key="quiz-{{ $quiz->quiz_id }}" class="hover:bg-brand-wash/50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.quizzes.show', $quiz) }}" class="font-black hover:underline">{{ $quiz->title }}</a>
                            <span class="block text-sm text-slate"><span class="font-receipt">{{ $quiz->code }}</span> · {{ $quiz->isPractice() ? __('Practice') : __('Scored') }}@if ($quiz->category) · {{ $quiz->category->name() }}@endif</span>
                        </td>
                        <td class="px-4 py-3"><x-editorial-status :status="$quiz->editorial_status" /></td>
                        <td class="px-4 py-3">
                            <x-editorial-status :status="$quiz->displayStatus()" />
                            @if ($quiz->closes_at && $quiz->isOpen())<span class="mt-1 block text-sm text-slate">{{ __('until :date', ['date' => $quiz->closes_at->translatedFormat('j M')]) }}</span>@endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $quiz->questions_count }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($quiz->attempts_count) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-lg font-bold text-slate">{{ __('No quizzes match these filters.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $this->quizzes->links() }}</div>
</div>
