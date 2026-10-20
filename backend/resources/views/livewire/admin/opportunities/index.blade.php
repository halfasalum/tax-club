@php
    $views = ['open' => __('Open now'), 'review' => __('Waiting for review'), 'drafts' => __('Drafts'), 'ready' => __('Ready to publish'), 'closed' => __('Closed and archived'), 'all' => __('All')];
    $types = \App\Support\OpportunityBoard::typeLabels();
@endphp
<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Opportunities') }}</h1>
            <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Jobs, internships, scholarships and other chances for members. Each one is reviewed before it is published and closes at its deadline.') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canReport)<a wire:navigate href="{{ route('admin.opportunities.report') }}" class="btn-ghost">{{ __('Engagement report') }}</a>@endif
            @if ($canCreate)<a wire:navigate href="{{ route('admin.opportunities.create') }}" class="btn-brand">{{ __('New opportunity') }}</a>@endif
        </div>
    </div>

    <div class="mt-6 flex flex-wrap gap-1 rounded-2xl bg-white p-1.5 shadow-sm" role="tablist" aria-label="{{ __('Status') }}">
        @foreach ($views as $value => $label)
            <button type="button" role="tab" wire:click="$set('view', '{{ $value }}')" aria-selected="{{ $view === $value ? 'true' : 'false' }}"
                    @class(['rounded-xl px-4 py-2 font-extrabold transition-colors', 'bg-ink text-brand' => $view === $value, 'text-slate hover:bg-brand-wash hover:text-ink' => $view !== $value])>
                {{ $label }}@if ($value === 'review' && $waiting > 0)<span class="ml-1 rounded-full bg-brand px-2 text-sm text-ink">{{ $waiting }}</span>@endif
            </button>
        @endforeach
    </div>
    <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search by title, code or organization') }}" class="field mt-5 md:max-w-md" aria-label="{{ __('Search by title, code or organization') }}">

    <div class="mt-6 overflow-x-auto rounded-2xl border-2 border-line bg-white" wire:loading.class="opacity-60">
        <table class="w-full min-w-[48rem] text-left">
            <thead class="border-b-2 border-line bg-mist text-sm">
                <tr>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Opportunity') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Deadline') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Status') }}</th>
                    <th scope="col" class="px-4 py-3 text-right font-extrabold">{{ __('Saved') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($this->opportunities as $opportunity)
                    <tr wire:key="op-{{ $opportunity->opportunity_id }}" class="hover:bg-brand-wash/50">
                        <td class="px-4 py-3">
                            <a wire:navigate href="{{ route('admin.opportunities.show', $opportunity) }}" class="font-black hover:underline">{{ $opportunity->title }}</a>
                            <span class="block text-sm text-slate"><span class="font-receipt">{{ $opportunity->code }}</span> · {{ $types[$opportunity->type] ?? $opportunity->type }} · {{ $opportunity->organization }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $opportunity->deadline?->translatedFormat('j M Y') ?? __('Open until filled') }}</td>
                        <td class="px-4 py-3">
                            <x-editorial-status :status="$opportunity->editorial_status" />
                            @if ($opportunity->publication_status !== 'not_published')<x-editorial-status :status="$opportunity->displayStatus()" />@endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($opportunity->saves_count) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-lg font-bold text-slate">{{ __('No opportunities match these filters.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $this->opportunities->links() }}</div>
</div>
