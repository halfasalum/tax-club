@php
    $types = \App\Support\OpportunityBoard::typeLabels();
@endphp
<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Opportunities') }}</h1>
    <p class="mt-1 text-lg text-slate">{{ __('Internships, jobs, scholarships and training checked by TRA Tax Club. Save the ones you like and apply before the deadline.') }}</p>

    <div class="mt-6 flex flex-wrap gap-1 rounded-2xl bg-white p-1.5 shadow-sm sm:w-fit" role="tablist" aria-label="{{ __('Opportunities') }}">
        @foreach (['open' => __('Open now'), 'saved' => __('Saved')] as $value => $label)
            <button type="button" role="tab" wire:click="$set('tab', '{{ $value }}')" aria-selected="{{ $tab === $value ? 'true' : 'false' }}"
                    @class(['rounded-xl px-4 py-2 font-extrabold', 'bg-ink text-brand' => $tab === $value, 'text-slate hover:bg-brand-wash' => $tab !== $value])>{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'open')
        <div class="mt-5 grid gap-3 md:grid-cols-[1fr_14rem_auto]">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search opportunities') }}" class="field mt-0" aria-label="{{ __('Search opportunities') }}">
            <select wire:model.live="type" class="field mt-0" aria-label="{{ __('Type') }}">
                <option value="">{{ __('All types') }}</option>
                @foreach ($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
            <label class="flex items-center gap-2 rounded-xl border-2 border-line bg-white px-3 font-bold"><input type="checkbox" wire:model.live="closingSoon" class="size-4 accent-ink"> {{ __('Closing soon') }}</label>
        </div>
    @endif

    <ul class="mt-6 grid gap-4 md:grid-cols-2" wire:loading.class="opacity-60">
        @forelse ($opportunities as $opportunity)
            <li wire:key="op-{{ $opportunity->opportunity_id }}">
                <a wire:navigate href="{{ route('member.opportunities.show', $opportunity->slug) }}" class="flex h-full flex-col rounded-2xl border-2 border-line bg-white p-5 transition hover:border-ink">
                    <span class="flex flex-wrap gap-2 text-sm font-extrabold">
                        <span class="rounded-full bg-brand px-2.5 py-0.5">{{ $types[$opportunity->type] ?? $opportunity->type }}</span>
                        @if ($opportunity->isClosingSoon())<span class="rounded-full bg-danger px-2.5 py-0.5 text-white">{{ __('Closing soon') }}</span>@endif
                        @unless ($opportunity->isOpen())<span class="rounded-full bg-mist px-2.5 py-0.5">{{ __('Closed') }}</span>@endunless
                    </span>
                    <span class="mt-3 text-lg font-black leading-snug">{{ $opportunity->title }}</span>
                    <span class="mt-1 font-bold text-ink-soft">{{ $opportunity->organization }}@if ($opportunity->region) · {{ $opportunity->region }}@endif</span>
                    <span class="mt-auto pt-3 text-sm text-slate">{{ $opportunity->deadline ? __('Apply by :date', ['date' => $opportunity->deadline->translatedFormat('j M Y')]) : __('Open until filled') }}</span>
                </a>
            </li>
        @empty
            <li class="rounded-2xl bg-white p-8 text-center text-lg font-bold text-slate md:col-span-2">
                {{ $tab === 'saved' ? __('You have not saved any opportunities yet.') : __('No open opportunities match. Check back soon.') }}
            </li>
        @endforelse
    </ul>

    @if ($resources->isNotEmpty())
        <section class="mt-12" aria-labelledby="career-heading">
            <h2 id="career-heading" class="text-2xl font-black">{{ __('Career guidance') }}</h2>
            <p class="mt-1 text-slate">{{ __('Get ready to apply: CVs, interviews and careers in tax.') }}</p>
            <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($resources as $lesson)
                    <li>
                        <a wire:navigate href="{{ route('member.learn.lesson', $lesson) }}" class="flex h-full flex-col rounded-2xl bg-ink p-5 text-white transition hover:bg-ink-soft">
                            <span class="text-lg font-black leading-snug">{{ $lesson->title }}</span>
                            @if ($lesson->summary)<span class="mt-2 text-sm text-white/80">{{ \Illuminate\Support\Str::limit($lesson->summary, 120) }}</span>@endif
                            <span class="mt-auto pt-3 text-sm font-extrabold text-brand">{{ __('Read') }} →</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
