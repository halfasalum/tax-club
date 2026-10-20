@php
    $types = \App\Support\OpportunityBoard::typeLabels();
    $workModes = ['on_site' => __('On site'), 'remote' => __('Remote'), 'hybrid' => __('Hybrid')];
@endphp
<div class="mx-auto max-w-4xl">
    <a wire:navigate href="{{ route('member.opportunities') }}" class="link">← {{ __('Opportunities') }}</a>
    <header class="mt-4 rounded-3xl border-4 border-ink bg-white p-6 sm:p-8">
        <p class="flex flex-wrap gap-2 text-sm font-extrabold">
            <span class="rounded-full bg-brand px-2.5 py-0.5">{{ $types[$opportunity->type] ?? $opportunity->type }}</span>
            @if ($opportunity->isClosingSoon())<span class="rounded-full bg-danger px-2.5 py-0.5 text-white">{{ __('Closing soon') }}</span>@endif
        </p>
        <h1 class="mt-3 text-3xl font-black leading-tight sm:text-4xl">{{ $opportunity->title }}</h1>
        <p class="mt-1 text-lg font-bold text-ink-soft">{{ $opportunity->organization }}</p>
        <dl class="mt-5 grid gap-4 sm:grid-cols-3">
            <div><dt class="text-sm font-extrabold text-slate">{{ __('Deadline') }}</dt><dd class="text-lg font-black">{{ $opportunity->deadline?->translatedFormat('j F Y') ?? __('Open until filled') }}</dd></div>
            <div><dt class="text-sm font-extrabold text-slate">{{ __('Location') }}</dt><dd class="text-lg font-black">{{ trim($opportunity->location.($opportunity->region ? ', '.$opportunity->region : '')) ?: '—' }}</dd></div>
            @if ($opportunity->work_mode)<div><dt class="text-sm font-extrabold text-slate">{{ __('Work mode') }}</dt><dd class="text-lg font-black">{{ $workModes[$opportunity->work_mode] ?? '' }}</dd></div>@endif
        </dl>
    </header>

    @unless ($opportunity->isOpen())
        <p class="mt-6 rounded-2xl bg-mist p-5 font-bold">{{ __('This opportunity is closed to new applications.') }}</p>
    @endunless

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_18rem]">
        <div class="space-y-6">
            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <div class="max-w-[65ch] whitespace-pre-line text-lg leading-relaxed text-ink-soft">{{ $opportunity->description }}</div>
            </section>
            @if ($opportunity->eligibility)
                <section class="rounded-3xl bg-brand-wash p-6">
                    <h2 class="text-xl font-black">{{ __('Who can apply') }}</h2>
                    <p class="mt-2 whitespace-pre-line">{{ $opportunity->eligibility }}</p>
                </section>
            @endif
            @if ($opportunity->apply_instructions)
                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('How to apply') }}</h2>
                    <p class="mt-2 whitespace-pre-line">{{ $opportunity->apply_instructions }}</p>
                </section>
            @endif
        </div>
        <aside class="space-y-4">
            @if ($opportunity->isOpen() && $opportunity->applyTarget())
                <a href="{{ route('member.opportunities.apply', $opportunity->slug) }}" target="_blank" rel="noopener" class="btn-brand w-full">{{ $opportunity->apply_method === 'email' ? __('Apply by email') : __('Apply now') }}</a>
                <p class="text-sm text-slate">{{ __('You will leave TRA Tax Club. Never pay anyone to apply for an opportunity.') }}</p>
            @endif
            <button type="button" wire:click="toggleSave" class="btn-ghost w-full" aria-pressed="{{ $saved ? 'true' : 'false' }}">{{ $saved ? '★ '.__('Saved') : '☆ '.__('Save for later') }}</button>
            @error('save')<p class="font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
            @if ($saved && $opportunity->deadline)<p class="text-sm text-slate">{{ __('We will remind you before it closes.') }}</p>@endif
            @if ($opportunity->attachment_path)
                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($opportunity->attachment_path) }}" target="_blank" rel="noopener" class="link block">{{ __('Download: :file', ['file' => $opportunity->attachment_name]) }}</a>
            @endif
            <a wire:navigate href="{{ route('member.feedback', ['about' => 'opportunity:'.$opportunity->opportunity_id]) }}" class="link block text-sm">{{ __('Report a problem with this listing') }}</a>
        </aside>
    </div>
</div>
