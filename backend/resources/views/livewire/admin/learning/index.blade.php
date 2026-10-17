@php
    use App\Models\Content;

    $views = [
        'all' => __('All'),
        'review' => __('Waiting for review'),
        'drafts' => __('Drafts'),
        'live' => __('Live'),
        'scheduled' => __('Scheduled'),
        'offline' => __('Unpublished and archived'),
        'review_due' => __('Review due'),
    ];
    $types = ['article' => __('Article'), 'video' => __('Video'), 'infographic' => __('Infographic'), 'document' => __('Document')];
@endphp
<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Learning content') }}</h1>
            <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Write lessons, send them for review, and publish approved versions. Members always see the latest published version.') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a wire:navigate href="{{ route('admin.learning.modules') }}" class="btn-ghost">{{ __('Modules') }}</a>
            @if ($canManageCategories)
                <a wire:navigate href="{{ route('admin.learning.categories') }}" class="btn-ghost">{{ __('Categories') }}</a>
            @endif
            @if ($canCreate)
                <a wire:navigate href="{{ route('admin.learning.create') }}" class="btn-brand">{{ __('New lesson') }}</a>
            @endif
        </div>
    </div>

    <div class="mt-6 flex flex-wrap gap-1 rounded-2xl bg-white p-1.5 shadow-sm" role="tablist" aria-label="{{ __('Status') }}">
        @foreach ($views as $value => $label)
            <button type="button" role="tab" wire:click="$set('view', '{{ $value }}')" aria-selected="{{ $view === $value ? 'true' : 'false' }}"
                    @class(['rounded-xl px-4 py-2 font-extrabold transition-colors', 'bg-ink text-brand' => $view === $value, 'text-slate hover:bg-brand-wash hover:text-ink' => $view !== $value])>
                {{ $label }}
                @if ($value === 'review' && $this->waitingForReview > 0)
                    <span class="ml-1 rounded-full bg-brand px-2 text-sm text-ink">{{ $this->waitingForReview }}</span>
                @endif
            </button>
        @endforeach
    </div>

    <div class="mt-5 grid gap-3 md:grid-cols-[2fr_1fr_1fr]">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search by title or code') }}" class="field mt-0" aria-label="{{ __('Search by title or code') }}">
        <select wire:model.live="category" class="field mt-0" aria-label="{{ __('Category') }}">
            <option value="">{{ __('All categories') }}</option>
            @foreach ($categories as $option)
                <option value="{{ $option->id }}">{{ $option->name() }}</option>
            @endforeach
        </select>
        <select wire:model.live="type" class="field mt-0" aria-label="{{ __('Type') }}">
            <option value="">{{ __('All types') }}</option>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="mt-6 overflow-x-auto rounded-2xl border-2 border-line bg-white" wire:loading.class="opacity-60">
        <table class="w-full min-w-[50rem] text-left">
            <thead class="border-b-2 border-line bg-mist text-sm">
                <tr>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Lesson') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Category') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Live version') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Working revision') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Updated') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($this->items as $item)
                    @php($revision = $item->versions->first())
                    <tr wire:key="content-{{ $item->content_id }}" class="hover:bg-brand-wash/50">
                        <td class="px-4 py-3">
                            <a wire:navigate href="{{ route('admin.learning.show', $item) }}" class="font-black hover:underline">{{ $revision?->field('title') ?? $item->title }}</a>
                            <span class="block text-sm text-slate"><span class="font-receipt">{{ $item->code }}</span> · {{ $types[$item->content_type] ?? $item->content_type }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $item->category?->name() ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($item->current_version)
                                <x-editorial-status :status="$item->isLive() ? 'published' : $item->publication_status" />
                                <span class="mt-1 block text-sm text-slate">v{{ $item->current_version }}</span>
                            @else
                                <x-editorial-status status="not_published" />
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($revision)
                                <x-editorial-status :status="$revision->status" />
                                <span class="mt-1 block text-sm text-slate">v{{ $revision->version }}</span>
                            @else
                                <span class="text-slate">—</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-slate">{{ $item->updated_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-lg font-bold text-slate">{{ __('No lessons match these filters.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $this->items->links() }}</div>
</div>
