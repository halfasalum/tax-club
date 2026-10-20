@php
    $modes = ['in_person' => __('In person'), 'phone' => __('Phone call'), 'video' => __('Video call'), 'at_club' => __('At the Tax Club')];
    $outcomes = \App\Support\MentorshipDesk::outcomeLabels();
@endphp
<div class="mx-auto max-w-4xl">
    <a wire:navigate href="{{ route('member.mentorship') }}" class="link">← {{ __('Mentorship') }}</a>
    <header class="mt-4 rounded-3xl bg-ink p-6 text-white">
        <p class="font-extrabold text-brand">{{ $mentorship->status === 'active' ? __('Active mentorship') : __('Ended') }}</p>
        <h1 class="mt-1 text-2xl font-black sm:text-3xl">{{ $mentorship->mentor?->full_name }} <span class="text-white/60">&</span> {{ $mentorship->mentee?->publicName() }}</h1>
        <p class="mt-2 text-white/80">{{ __('Since :date', ['date' => $mentorship->started_at->translatedFormat('j M Y')]) }}@if ($mentorship->supervisor) · {{ __('Supervised by :name', ['name' => $mentorship->supervisor->full_name]) }}@endif</p>
    </header>

    @if ($statusMessage)<p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>@endif
    @error('form')<p class="mt-6 rounded-2xl bg-[#fef3f2] px-5 py-4 font-bold text-danger" role="alert">{{ $message }}</p>@enderror

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_18rem]">
        <div class="space-y-6">
            @if ($isParticipant && $mentorship->status === 'active')
                <form wire:submit="logSession" class="space-y-3 rounded-3xl border-4 border-ink bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Record a session') }}</h2>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <input wire:model="held_on" type="date" class="field mt-0" aria-label="{{ __('Date') }}">
                        <select wire:model="mode" class="field mt-0" aria-label="{{ __('How you met') }}">@foreach ($modes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                        <input wire:model="duration_minutes" type="number" min="5" class="field mt-0" aria-label="{{ __('Minutes') }}" title="{{ __('Minutes') }}">
                    </div>
                    <input wire:model="topic" type="text" class="field mt-0" placeholder="{{ __('What you talked about') }}" aria-label="{{ __('Topic') }}">
                    @error('topic')<p class="font-semibold text-danger">{{ $message }}</p>@enderror
                    <textarea wire:model="summary" rows="3" class="field mt-0" placeholder="{{ __('Short summary and next steps (no private details)') }}" aria-label="{{ __('Summary') }}"></textarea>
                    <label class="flex items-center gap-2 font-bold"><input type="checkbox" wire:model="milestone" class="size-4 accent-ink"> {{ __('A goal was reached') }}</label>
                    <button type="submit" class="btn-primary">{{ __('Save session') }}</button>
                </form>
            @endif

            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ __('Sessions') }}</h2>
                <ol class="mt-3 space-y-3 border-l-4 border-brand pl-4">
                    @forelse ($sessions as $session)
                        <li>
                            <p class="font-extrabold">{{ $session->held_on->translatedFormat('j M Y') }} · {{ $session->topic }}@if ($session->milestone) <span class="rounded-full bg-savanna px-2 py-0.5 text-xs text-white">{{ __('Goal reached') }}</span>@endif</p>
                            <p class="text-sm text-slate">{{ $modes[$session->mode] ?? '' }}@if ($session->duration_minutes) · {{ __(':minutes min', ['minutes' => $session->duration_minutes]) }}@endif · {{ $session->logger?->full_name }}</p>
                            @if ($session->summary)<p class="whitespace-pre-line text-sm">{{ $session->summary }}</p>@endif
                        </li>
                    @empty
                        <li class="text-slate">{{ __('No sessions recorded yet.') }}</li>
                    @endforelse
                </ol>
            </section>

            @if ($mentorship->status !== 'active')
                <section class="rounded-3xl border-2 border-line bg-mist p-6">
                    <h2 class="font-black">{{ $outcomes[$mentorship->outcome] ?? '' }}</h2>
                    @if ($mentorship->closing_note)<p class="mt-1">{{ $mentorship->closing_note }}</p>@endif
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            <section class="space-y-3 rounded-3xl border-2 border-danger bg-white p-6">
                <h2 class="text-xl font-black">{{ __('Something wrong?') }}</h2>
                <p class="text-sm text-slate">{{ __('If anything makes you uncomfortable, tell us. A safeguarding officer will help, and the other person is not told who raised it.') }}</p>
                <textarea wire:model="concern" rows="3" class="field mt-0" aria-label="{{ __('Your concern') }}"></textarea>
                <button type="button" wire:click="raiseConcern" class="btn w-full border-2 border-danger text-danger hover:bg-[#fef3f2]">{{ __('Raise a concern') }}</button>
            </section>

            @if ($isParticipant && $mentorship->status === 'active')
                <section class="space-y-3 rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Finish the mentorship') }}</h2>
                    <select wire:model="outcome" class="field mt-0" aria-label="{{ __('How it went') }}">@foreach ($outcomes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    @if ($isMentee)
                        <select wire:model="rating" class="field mt-0" aria-label="{{ __('Rate your mentor') }}"><option value="">{{ __('Rate your mentor') }}</option>@for ($i = 5; $i >= 1; $i--)<option value="{{ $i }}">{{ str_repeat('★', $i) }}</option>@endfor</select>
                    @endif
                    <textarea wire:model="closingNote" rows="2" class="field mt-0" placeholder="{{ __('What was achieved?') }}" aria-label="{{ __('What was achieved?') }}"></textarea>
                    <button type="button" wire:click="close" wire:confirm="{{ __('End this mentorship?') }}" class="btn-ghost w-full">{{ __('Finish') }}</button>
                </section>
            @endif
        </aside>
    </div>
</div>
