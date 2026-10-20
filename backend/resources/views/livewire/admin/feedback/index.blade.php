@php
    use App\Support\FeedbackDesk;

    $views = ['open' => __('Open'), 'new' => __('New'), 'mine' => __('Assigned to me'), 'closed' => __('Closed'), 'all' => __('All')];
    $categories = FeedbackDesk::categoryLabels();
    $priorities = FeedbackDesk::priorityLabels();
    $statuses = FeedbackDesk::statusLabels();
    $areas = FeedbackDesk::areaLabels();
@endphp
<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Feedback') }}</h1>
    <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Suggestions, problems and concerns from members. Classify, assign, respond and close each one.') }}</p>

    <div class="mt-6 flex flex-wrap gap-1 rounded-2xl bg-white p-1.5 shadow-sm" role="tablist" aria-label="{{ __('Status') }}">
        @foreach ($views as $value => $label)
            <button type="button" role="tab" wire:click="$set('view', '{{ $value }}')" aria-selected="{{ $view === $value ? 'true' : 'false' }}"
                    @class(['rounded-xl px-4 py-2 font-extrabold transition-colors', 'bg-ink text-brand' => $view === $value, 'text-slate hover:bg-brand-wash hover:text-ink' => $view !== $value])>
                {{ $label }}@if (($counts[$value] ?? 0) > 0)<span class="ml-1 rounded-full bg-brand px-2 text-sm text-ink">{{ $counts[$value] }}</span>@endif
            </button>
        @endforeach
    </div>
    <div class="mt-5 grid gap-3 md:grid-cols-[1fr_14rem_12rem]">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search by reference or subject') }}" class="field mt-0" aria-label="{{ __('Search by reference or subject') }}">
        <select wire:model.live="category" class="field mt-0" aria-label="{{ __('Category') }}">
            <option value="">{{ __('All categories') }}</option>
            @foreach ($categories as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
        </select>
        <select wire:model.live="priority" class="field mt-0" aria-label="{{ __('Priority') }}">
            <option value="">{{ __('Any priority') }}</option>
            @foreach ($priorities as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
        </select>
    </div>

    <div class="mt-6 overflow-x-auto rounded-2xl border-2 border-line bg-white" wire:loading.class="opacity-60">
        <table class="w-full min-w-[52rem] text-left">
            <thead class="border-b-2 border-line bg-mist text-sm">
                <tr>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Feedback') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Priority') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Area') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Assigned to') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($this->feedback as $item)
                    <tr wire:key="fb-{{ $item->feedback_id }}" class="hover:bg-brand-wash/50">
                        <td class="px-4 py-3">
                            <a wire:navigate href="{{ route('admin.feedback.show', $item) }}" class="font-black hover:underline">{{ $item->subject }}</a>
                            <span class="block text-sm text-slate"><span class="font-receipt">{{ $item->reference }}</span> · {{ $categories[$item->category] ?? $item->category }} · {{ $item->created_at->translatedFormat('j M') }}</span>
                        </td>
                        <td class="px-4 py-3"><span @class(['rounded-full px-2.5 py-0.5 text-xs font-extrabold', 'bg-danger text-white' => in_array($item->priority, ['urgent', 'high'], true), 'bg-mist' => ! in_array($item->priority, ['urgent', 'high'], true)])>{{ $priorities[$item->priority] ?? $item->priority }}</span></td>
                        <td class="px-4 py-3">{{ $areas[$item->area] ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $item->assignee?->full_name ?? '—' }}</td>
                        <td class="px-4 py-3 font-bold">{{ $statuses[$item->status] ?? $item->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-lg font-bold text-slate">{{ __('No feedback matches these filters.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $this->feedback->links() }}</div>
</div>
