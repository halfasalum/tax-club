<div class="mx-auto max-w-4xl">
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Alumni stories') }}</h1>
    <p class="mt-2 text-lg text-slate">{{ __('Check that each story is true, kind and free of personal contact details before it goes to students.') }}</p>
    @if ($statusMessage)<p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>@endif
    @error('action')<p class="mt-6 rounded-2xl bg-[#fef3f2] px-5 py-4 font-bold text-danger" role="alert">{{ $message }}</p>@enderror

    <h2 class="mt-8 text-2xl font-black">{{ __('Waiting for review') }}</h2>
    <ul class="mt-4 space-y-4">
        @forelse ($waiting as $story)
            <li wire:key="st-{{ $story->story_id }}" class="rounded-3xl border-2 border-line bg-white p-6">
                <p class="text-sm text-slate">{{ $story->author?->full_name }} · <span class="font-receipt">{{ $story->author?->membership_id }}</span> · {{ $story->submitted_at?->translatedFormat('j M Y') }}</p>
                <h3 class="mt-1 text-xl font-black">{{ $story->title }}</h3>
                <p class="mt-2 whitespace-pre-line">{{ $story->body }}</p>
                <div class="mt-4 flex flex-wrap items-end gap-2">
                    <input wire:model="notes.{{ $story->story_id }}" type="text" class="field mt-0 min-w-0 flex-1" placeholder="{{ __('Notes for the author (needed to return)') }}" aria-label="{{ __('Notes for the author') }}">
                    <button type="button" wire:click="approve('{{ $story->story_id }}')" class="btn-primary">{{ __('Approve and publish') }}</button>
                    <button type="button" wire:click="returnStory('{{ $story->story_id }}')" class="btn-ghost">{{ __('Return') }}</button>
                </div>
            </li>
        @empty
            <li class="rounded-2xl bg-white p-6 font-bold text-slate">{{ __('Nothing waiting.') }}</li>
        @endforelse
    </ul>

    <h2 class="mt-10 text-2xl font-black">{{ __('Recently published') }}</h2>
    <ul class="mt-4 divide-y divide-line rounded-2xl border-2 border-line bg-white">
        @forelse ($published as $story)
            <li class="px-5 py-3"><span class="font-bold">{{ $story->title }}</span><span class="block text-sm text-slate">{{ $story->author?->full_name }} · {{ $story->published_at->translatedFormat('j M Y') }}</span></li>
        @empty
            <li class="px-5 py-4 text-slate">{{ __('None yet.') }}</li>
        @endforelse
    </ul>
</div>
