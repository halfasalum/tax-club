@php
    $views = ['new' => __('New'), 'mine' => __('Assigned to me'), 'assigned' => __('With experts'), 'review' => __('Answers to check'), 'answered' => __('Answered'), 'published' => __('Published'), 'declined' => __('Declined')];
    $statuses = \App\Support\AskTra::statusLabels();
@endphp
<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Ask TRA') }}</h1>
    <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Questions from members. Assign each to an expert; a second officer checks the answer before the member gets it.') }}</p>

    <div class="mt-6 flex flex-wrap gap-1 rounded-2xl bg-white p-1.5 shadow-sm" role="tablist" aria-label="{{ __('Status') }}">
        @foreach ($views as $value => $label)
            <button type="button" role="tab" wire:click="$set('view', '{{ $value }}')" aria-selected="{{ $view === $value ? 'true' : 'false' }}"
                    @class(['rounded-xl px-4 py-2 font-extrabold transition-colors', 'bg-ink text-brand' => $view === $value, 'text-slate hover:bg-brand-wash hover:text-ink' => $view !== $value])>
                {{ $label }}@if (($counts[$value] ?? 0) > 0)<span class="ml-1 rounded-full bg-brand px-2 text-sm text-ink">{{ $counts[$value] }}</span>@endif
            </button>
        @endforeach
    </div>
    <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search by reference or subject') }}" class="field mt-5 md:max-w-md" aria-label="{{ __('Search by reference or subject') }}">

    <div class="mt-6 overflow-x-auto rounded-2xl border-2 border-line bg-white" wire:loading.class="opacity-60">
        <table class="w-full min-w-[48rem] text-left">
            <thead class="border-b-2 border-line bg-mist text-sm">
                <tr>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Question') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Topic') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Expert') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($this->questions as $question)
                    <tr wire:key="q-{{ $question->question_id }}" class="hover:bg-brand-wash/50">
                        <td class="px-4 py-3">
                            <a wire:navigate href="{{ route('admin.questions.show', $question) }}" class="font-black hover:underline">{{ $question->subject }}</a>
                            <span class="block text-sm text-slate"><span class="font-receipt">{{ $question->reference }}</span> · {{ $question->created_at->diffForHumans() }} · {{ strtoupper($question->language) }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $question->category?->name() ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $question->expert?->full_name ?? '—' }}</td>
                        <td class="px-4 py-3 font-bold">{{ $statuses[$question->status] ?? $question->status }}@if ($question->published_at)<span class="block text-xs text-savanna">{{ __('Published') }}</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-lg font-bold text-slate">{{ __('No questions here.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $this->questions->links() }}</div>
</div>
