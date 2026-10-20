@php
    $views = array_filter(['requests' => __('Requests'), 'applications' => $can['review'] ? __('Mentor applications') : null, 'active' => $can['coordinate'] ? __('Active mentorships') : null]);
    $checks = ['not_required' => __('No check needed'), 'pending' => __('Background check pending'), 'cleared' => __('Background check cleared'), 'failed' => __('Background check failed')];
@endphp
<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Mentorship') }}</h1>
    <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Approve mentors, match them with members and follow each mentorship. Members under 18 need confirmed guardian consent and a mentor with a cleared background check.') }}</p>

    @if ($statusMessage)<p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>@endif
    @error('action')<p class="mt-6 rounded-2xl bg-[#fef3f2] px-5 py-4 font-bold text-danger" role="alert">{{ $message }}</p>@enderror

    <div class="mt-6 flex flex-wrap gap-1 rounded-2xl bg-white p-1.5 shadow-sm" role="tablist" aria-label="{{ __('Mentorship') }}">
        @foreach ($views as $value => $label)
            <button type="button" role="tab" wire:click="$set('view', '{{ $value }}')" aria-selected="{{ $view === $value ? 'true' : 'false' }}" @class(['rounded-xl px-4 py-2 font-extrabold', 'bg-ink text-brand' => $view === $value, 'text-slate hover:bg-brand-wash' => $view !== $value])>{{ $label }}</button>
        @endforeach
    </div>

    @if ($view === 'requests')
        <ul class="mt-6 space-y-4">
            @forelse ($requests as $request)
                <li wire:key="req-{{ $request->id }}" class="rounded-3xl border-2 border-line bg-white p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-lg font-black">{{ $request->mentee?->full_name }} @if ($request->mentee?->isMinor())<span class="ml-1 rounded-full bg-brand px-2 py-0.5 text-xs font-extrabold">{{ __('Under 18') }}</span>@endif</p>
                            <p class="text-sm text-slate">{{ $request->mentee?->institution?->name }} · {{ $request->created_at->diffForHumans() }}</p>
                            <p class="mt-2">{{ collect($request->interests)->map(fn ($area) => $areas[$area] ?? $area)->implode(', ') }}</p>
                            <p class="mt-1 whitespace-pre-line text-slate">{{ $request->goals }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($request->mentee?->isMinor() && ! $request->consent_confirmed_at)
                                <button type="button" wire:click="confirmConsent({{ $request->id }})" wire:confirm="{{ __('Confirm that you spoke with the parent or guardian and they agree?') }}" class="btn-brand">{{ __('Confirm guardian consent') }}</button>
                            @elseif ($request->consent_confirmed_at)
                                <span class="text-sm font-bold text-savanna">{{ __('Consent confirmed') }}</span>
                            @endif
                            @if ($can['coordinate'])<button type="button" wire:click="$set('matching', {{ $request->id }})" class="btn-primary">{{ __('Find a mentor') }}</button>@endif
                        </div>
                    </div>
                    @if ($matching === $request->id)
                        <div class="mt-4 rounded-2xl bg-mist p-4">
                            <ul class="divide-y divide-line">
                                @forelse ($suggestions as $row)
                                    <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                                        <span>
                                            <span class="font-bold">{{ $row['profile']->user?->full_name }}</span>
                                            <span class="block text-sm text-slate">{{ trans_choice(':count matching area|:count matching areas', $row['overlap'], ['count' => $row['overlap']]) }} · {{ $row['profile']->activeMentorships() }}/{{ $row['profile']->max_mentees }}@unless ($row['minors_ok']) · <span class="font-bold text-danger">{{ __('not cleared for under-18s') }}</span>@endunless</span>
                                        </span>
                                        <button type="button" wire:click="match({{ $request->id }}, {{ $row['profile']->id }})" class="btn-primary" @disabled(! $row['minors_ok'])>{{ __('Match') }}</button>
                                    </li>
                                @empty
                                    <li class="py-2 text-slate">{{ __('No approved mentor has room right now.') }}</li>
                                @endforelse
                            </ul>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <input wire:model="notes.{{ $request->id }}" type="text" class="field mt-0 min-w-0 flex-1" placeholder="{{ __('Reason, if declining') }}" aria-label="{{ __('Reason') }}">
                                <button type="button" wire:click="decline({{ $request->id }})" class="btn-ghost">{{ __('Decline request') }}</button>
                            </div>
                        </div>
                    @endif
                </li>
            @empty
                <li class="rounded-2xl bg-white p-8 text-center font-bold text-slate">{{ __('No open requests.') }}</li>
            @endforelse
        </ul>
    @elseif ($view === 'applications')
        <ul class="mt-6 space-y-4">
            @forelse ($applications as $profile)
                <li wire:key="app-{{ $profile->id }}" class="rounded-3xl border-2 border-line bg-white p-6">
                    <p class="text-lg font-black">{{ $profile->user?->full_name }} <span class="text-sm font-bold text-slate">· {{ $profile->user?->user_type === 'staff' ? __('TRA staff') : __('Alumni') }}</span></p>
                    <p class="mt-1">{{ collect($profile->expertise)->map(fn ($area) => $areas[$area] ?? $area)->implode(', ') }}</p>
                    <p class="mt-2 whitespace-pre-line text-slate">{{ $profile->bio }}</p>
                    <p class="mt-2 text-sm font-bold">{{ $profile->mentors_minors ? __('Wants to mentor members under 18') : __('Adults only') }} · {{ $checks[$profile->background_check] ?? '' }} · {{ __('Up to :count mentees', ['count' => $profile->max_mentees]) }}</p>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <input wire:model="notes.{{ $profile->id }}" type="text" class="field mt-0 min-w-0 flex-1" placeholder="{{ __('Note, reason or check reference') }}" aria-label="{{ __('Note') }}">
                        @if ($profile->status === 'submitted')
                            <button type="button" wire:click="decide({{ $profile->id }}, true)" class="btn-primary">{{ __('Approve') }}</button>
                            <button type="button" wire:click="decide({{ $profile->id }}, false)" class="btn-ghost">{{ __('Decline') }}</button>
                        @endif
                        @if ($profile->background_check === 'pending')
                            <button type="button" wire:click="backgroundCheck({{ $profile->id }}, true)" class="btn-ghost">{{ __('Check cleared') }}</button>
                            <button type="button" wire:click="backgroundCheck({{ $profile->id }}, false)" class="btn border-2 border-line hover:border-danger hover:text-danger">{{ __('Check failed') }}</button>
                        @endif
                    </div>
                </li>
            @empty
                <li class="rounded-2xl bg-white p-8 text-center font-bold text-slate">{{ __('No applications waiting.') }}</li>
            @endforelse
        </ul>
    @else
        <div class="mt-6 overflow-x-auto rounded-2xl border-2 border-line bg-white">
            <table class="w-full min-w-[44rem] text-left">
                <thead class="border-b-2 border-line bg-mist text-sm"><tr><th scope="col" class="px-4 py-3 font-extrabold">{{ __('Mentor & mentee') }}</th><th scope="col" class="px-4 py-3 font-extrabold">{{ __('Supervisor') }}</th><th scope="col" class="px-4 py-3 text-right font-extrabold">{{ __('Sessions') }}</th><th scope="col" class="px-4 py-3 font-extrabold">{{ __('Last activity') }}</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($active as $mentorship)
                        <tr wire:key="ms-{{ $mentorship->id }}">
                            <td class="px-4 py-3"><a wire:navigate href="{{ route('admin.mentorship.show', $mentorship) }}" class="font-black hover:underline">{{ $mentorship->mentor?->full_name }} & {{ $mentorship->mentee?->full_name }}</a>@if ($mentorship->mentee?->isMinor())<span class="ml-1 rounded-full bg-brand px-2 py-0.5 text-xs font-extrabold">{{ __('Under 18') }}</span>@endif</td>
                            <td class="px-4 py-3">{{ $mentorship->supervisor?->full_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $mentorship->sessions_count }}</td>
                            <td @class(['px-4 py-3', 'font-bold text-danger' => $mentorship->last_activity_at?->lt($inactiveBefore)])>{{ $mentorship->last_activity_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center font-bold text-slate">{{ __('No active mentorships.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
