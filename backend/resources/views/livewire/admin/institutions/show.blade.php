@php
    $categories = ['secondary' => __('Secondary school'), 'college' => __('College'), 'university' => __('University')];
    $errorText = 'mt-2 font-semibold text-danger';
    $isActive = $institution->status === 'active';
    $activeClub = $clubs->firstWhere('status', 'active');
@endphp
<div>
    <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm font-bold">
        <a wire:navigate href="{{ route('admin.institutions.index') }}" class="link">{{ __('Institutions') }}</a>
    </nav>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ $institution->name }}</h1>
            <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-lg text-slate">
                <span class="rounded-lg bg-ink px-2.5 py-0.5 font-receipt text-base font-semibold text-brand">{{ $institution->code }}</span>
                <span>{{ $categories[$institution->type] ?? $institution->type }}</span>
                <span>{{ collect([$institution->district, $institution->region])->filter()->implode(', ') }}</span>
                <span @class(['rounded-full px-3 py-0.5 text-sm font-extrabold', 'bg-savanna text-white' => $isActive, 'bg-mist text-ink-soft' => ! $isActive])>{{ $isActive ? __('Active') : __('Inactive') }}</span>
            </p>
        </div>
        @if ($can['edit'])
            <a wire:navigate href="{{ route('admin.institutions.edit', $institution) }}" class="btn-ghost">{{ __('Edit details') }}</a>
        @endif
    </div>

    @if (session('status') || $statusMessage)
        <p role="status" class="mt-6 flex items-center gap-3 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">
            <svg viewBox="0 0 20 20" class="size-6 shrink-0" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.58l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
            {{ $statusMessage ?? session('status') }}
        </p>
    @endif
    @error('action')
        <p role="alert" class="mt-6 rounded-2xl border-2 border-danger bg-danger/5 px-5 py-4 font-extrabold text-danger">{{ $message }}</p>
    @enderror

    @if (! $isActive && $institution->status_reason)
        <p class="mt-6 rounded-2xl border-2 border-ink bg-brand-wash px-5 py-4"><span class="font-extrabold">{{ __('Deactivated') }}:</span> {{ $institution->status_reason }}</p>
    @endif

    <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-8">
            {{-- Tax Club --}}
            <section aria-labelledby="club-heading" class="rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
                <h2 id="club-heading" class="text-2xl font-black">{{ __('Tax Club') }}</h2>

                @if (! $activeClub)
                    <p class="mt-2 text-lg text-slate">{{ __('This institution has no active Tax Club yet. Establish one to assign a patron.') }}</p>
                    @if ($can['clubs'])
                        <form wire:submit="establishClub" class="mt-6 grid gap-5 sm:grid-cols-[2fr_1fr]">
                            <div>
                                <label for="clubName" class="block font-extrabold">{{ __('Club name') }}</label>
                                <input wire:model="clubName" id="clubName" type="text" @class(['field', 'field-error' => $errors->has('clubName')])>
                                @error('clubName')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="clubEstablishedOn" class="block font-extrabold">{{ __('Date established') }} <span class="font-semibold text-slate">({{ __('optional') }})</span></label>
                                <input wire:model="clubEstablishedOn" id="clubEstablishedOn" type="date" max="{{ now()->toDateString() }}" @class(['field', 'field-error' => $errors->has('clubEstablishedOn')])>
                                @error('clubEstablishedOn')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <button type="submit" class="btn-brand">{{ __('Establish Tax Club') }}</button>
                            </div>
                        </form>
                    @endif
                @else
                    <div class="mt-4 flex flex-wrap items-start justify-between gap-4 rounded-2xl bg-brand-wash p-5">
                        <div>
                            <p class="text-lg font-black">{{ $activeClub->name }}</p>
                            <p class="text-slate">
                                @if ($activeClub->established_on)
                                    {{ __('Established :date', ['date' => $activeClub->established_on->translatedFormat('j F Y')]) }}
                                @else
                                    {{ __('Establishment date not recorded') }}
                                @endif
                            </p>
                        </div>
                        @if ($can['clubs'] && ! $changingClubStatus)
                            <button type="button" wire:click="$set('changingClubStatus', true)" class="btn border-2 border-line bg-white px-4 hover:border-danger hover:text-danger">{{ __('Deactivate club') }}</button>
                        @endif
                    </div>
                    @if ($changingClubStatus)
                        <form wire:submit="changeClubStatus('{{ $activeClub->tax_club_id }}')" class="mt-4 rounded-2xl border-2 border-danger/40 bg-danger/5 p-5">
                            <label for="clubStatusReason" class="block font-extrabold">{{ __('Why is the club being deactivated?') }}</label>
                            <textarea wire:model="clubStatusReason" id="clubStatusReason" rows="2" class="field"></textarea>
                            @error('clubStatusReason')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="submit" class="btn bg-danger text-white hover:brightness-110">{{ __('Deactivate club') }}</button>
                                <button type="button" wire:click="$set('changingClubStatus', false)" class="btn hover:bg-mist">{{ __('Cancel') }}</button>
                            </div>
                        </form>
                    @endif

                    {{-- Patrons --}}
                    <h3 class="mt-8 text-xl font-black">{{ __('Patrons') }}</h3>
                    @php($assignments = $activeClub->patronAssignments)
                    @php($current = $assignments->filter->isCurrent())
                    @if ($current->isEmpty())
                        <p class="mt-2 rounded-xl bg-mist px-4 py-3 text-slate">{{ __('No patron is assigned. Until one is, registrations for this institution are confirmed by TRA coordinators.') }}</p>
                    @else
                        <ul class="mt-3 space-y-3">
                            @foreach ($current as $assignment)
                                <li wire:key="patron-{{ $assignment->id }}" class="rounded-2xl border-2 border-line p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <span class="grid size-11 place-items-center rounded-full bg-brand font-black" aria-hidden="true">{{ mb_substr($assignment->user->full_name, 0, 1) }}</span>
                                            <div>
                                                <p class="font-extrabold">{{ $assignment->user->full_name }}</p>
                                                <p class="text-sm text-slate">{{ $assignment->user->phone }}{{ $assignment->user->email ? ', '.$assignment->user->email : '' }}</p>
                                                <p class="text-sm text-slate">{{ __('Since :date', ['date' => $assignment->starts_on->translatedFormat('j M Y')]) }}</p>
                                            </div>
                                        </div>
                                        @if ($can['patrons'] && $endingAssignmentId !== $assignment->id)
                                            <button type="button" wire:click="startEndingAssignment({{ $assignment->id }})" class="btn border-2 border-line px-4 hover:border-danger hover:text-danger">
                                                {{ __('End assignment') }}<span class="sr-only">: {{ $assignment->user->full_name }}</span>
                                            </button>
                                        @endif
                                    </div>
                                    @if ($endingAssignmentId === $assignment->id)
                                        <form wire:submit="endAssignment" class="mt-4 rounded-xl border-2 border-danger/40 bg-danger/5 p-4">
                                            <label for="endReason" class="block font-extrabold">{{ __('Why is this assignment ending?') }}</label>
                                            <textarea wire:model="endReason" id="endReason" rows="2" class="field" placeholder="{{ __('For example: transferred to another school') }}"></textarea>
                                            @error('endReason')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                <button type="submit" class="btn bg-danger text-white hover:brightness-110">{{ __('End assignment') }}</button>
                                                <button type="button" wire:click="$set('endingAssignmentId', null)" class="btn hover:bg-mist">{{ __('Cancel') }}</button>
                                            </div>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($newPatronCredentials)
                        <div class="mt-5 rounded-2xl border-4 border-ink bg-brand p-5" role="alert">
                            <p class="text-lg font-black">{{ __('New patron account created') }}</p>
                            <p class="mt-1 font-semibold">{{ __('Give these sign-in details to :name in person. The password is shown only once.', ['name' => $newPatronCredentials['name']]) }}</p>
                            <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                                <div><dt class="text-sm font-extrabold">{{ __('Phone number') }}</dt><dd class="font-receipt text-lg font-semibold">{{ $newPatronCredentials['phone'] }}</dd></div>
                                <div><dt class="text-sm font-extrabold">{{ __('Temporary password') }}</dt><dd class="select-all font-receipt text-lg font-semibold">{{ $newPatronCredentials['password'] }}</dd></div>
                            </dl>
                            <button type="button" wire:click="dismissCredentials" class="btn-primary mt-4">{{ __('I have noted it') }}</button>
                        </div>
                    @endif

                    @if ($can['patrons'])
                        <form wire:submit="assignPatron('{{ $activeClub->tax_club_id }}')" class="mt-6 rounded-2xl bg-mist p-5">
                            <p class="font-black">{{ __('Assign a patron') }}</p>
                            <p class="mt-1 text-slate">{{ __('Enter the patron’s mobile number. If they have no account yet, add their name and one is created.') }}</p>
                            @error('patron')<p class="mt-3 rounded-xl border-2 border-danger bg-white px-4 py-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="patronPhone" class="block font-extrabold">{{ __('Mobile number') }}</label>
                                    <input wire:model="patronPhone" id="patronPhone" type="tel" inputmode="tel" placeholder="0712 345 678" @class(['field', 'field-error' => $errors->has('patronPhone')])>
                                    @error('patronPhone')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="patronStartsOn" class="block font-extrabold">{{ __('Start date') }}</label>
                                    <input wire:model="patronStartsOn" id="patronStartsOn" type="date" class="field">
                                    @error('patronStartsOn')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="patronName" class="block font-extrabold">{{ __('Full name') }} <span class="font-semibold text-slate">({{ __('new accounts only') }})</span></label>
                                    <input wire:model="patronName" id="patronName" type="text" class="field">
                                    @error('patronName')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="patronEmail" class="block font-extrabold">{{ __('Email') }} <span class="font-semibold text-slate">({{ __('optional') }})</span></label>
                                    <input wire:model="patronEmail" id="patronEmail" type="email" class="field">
                                    @error('patronEmail')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <button type="submit" class="btn-primary mt-5" wire:loading.attr="disabled">{{ __('Assign patron') }}</button>
                        </form>
                    @endif

                    @php($past = $assignments->reject->isCurrent())
                    @if ($past->isNotEmpty())
                        <details class="mt-6">
                            <summary class="cursor-pointer font-extrabold">{{ trans_choice(':count previous patron|:count previous patrons', $past->count(), ['count' => $past->count()]) }}</summary>
                            <ul class="mt-3 space-y-2">
                                @foreach ($past as $assignment)
                                    <li class="rounded-xl bg-mist px-4 py-3">
                                        <span class="font-extrabold">{{ $assignment->user->full_name }}</span>,
                                        {{ $assignment->starts_on->translatedFormat('j M Y') }} – {{ $assignment->ends_on?->translatedFormat('j M Y') }}
                                        @if ($assignment->end_reason)<span class="block text-slate">{{ $assignment->end_reason }}</span>@endif
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                @endif

                @if ($clubs->where('status', 'inactive')->isNotEmpty())
                    <details class="mt-6">
                        <summary class="cursor-pointer font-extrabold">{{ __('Inactive clubs') }}</summary>
                        <ul class="mt-3 space-y-2">
                            @foreach ($clubs->where('status', 'inactive') as $club)
                                <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-mist px-4 py-3">
                                    <span><span class="font-extrabold">{{ $club->name }}</span> <span class="block text-slate">{{ $club->status_reason }}</span></span>
                                    @if ($can['clubs'] && ! $activeClub)
                                        <button type="button" wire:click="changeClubStatus('{{ $club->tax_club_id }}')" class="link">{{ __('Reactivate') }}</button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </section>

            {{-- History --}}
            <section aria-labelledby="history-heading" class="rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
                <h2 id="history-heading" class="text-2xl font-black">{{ __('Status history') }}</h2>
                <ol class="mt-4 space-y-3 border-l-4 border-brand pl-5">
                    @forelse ($history as $change)
                        <li>
                            <p class="font-extrabold">
                                {{ $change->to_status === 'active' ? ($change->from_status ? __('Reactivated') : __('Registered')) : __('Deactivated') }}
                                <span class="font-semibold text-slate">{{ $change->changed_at?->translatedFormat('j M Y H:i') }}</span>
                            </p>
                            <p class="text-slate">{{ $change->officer?->full_name ?? __('System') }}@if ($change->reason): {{ $change->reason }}@endif</p>
                        </li>
                    @empty
                        <li class="text-slate">{{ __('No changes recorded yet.') }}</li>
                    @endforelse
                </ol>
            </section>

            @if ($changes->isNotEmpty())
                <section aria-labelledby="changes-heading" class="rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <h2 id="changes-heading" class="text-2xl font-black">{{ __('Detail changes') }}</h2>
                        @if ($can['audit'])
                            <a wire:navigate href="{{ route('admin.audit', ['subject' => $institution->institution_id]) }}" class="link">{{ __('Full audit log') }}</a>
                        @endif
                    </div>
                    <ul class="mt-4 divide-y divide-line">
                        @foreach ($changes as $record)
                            <li class="py-3">
                                <p class="font-extrabold">{{ $record->actor?->full_name ?? __('System') }} <span class="font-semibold text-slate">{{ $record->created_at->translatedFormat('j M Y H:i') }}</span></p>
                                <ul class="mt-1 text-sm">
                                    @foreach ($record->changedFields() as $field)
                                        <li><span class="font-bold">{{ str_replace('_', ' ', $field) }}</span>: <span class="text-danger line-through">{{ $record->before[$field] ?? '—' }}</span> → <span class="text-savanna">{{ $record->after[$field] ?? '—' }}</span></li>
                                    @endforeach
                                </ul>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            <dl class="grid grid-cols-3 gap-3 lg:grid-cols-1">
                @foreach ([[__('Active members'), $memberCounts['active']], [__('Waiting for confirmation'), $memberCounts['pending']], [__('Alumni'), $memberCounts['alumni']]] as [$label, $value])
                    <div class="rounded-2xl border-2 border-line border-t-8 border-t-brand bg-white px-5 py-4">
                        <dt class="font-extrabold text-slate">{{ $label }}</dt>
                        <dd class="mt-1 text-3xl font-black tabular-nums">{{ number_format($value) }}</dd>
                    </div>
                @endforeach
            </dl>

            <div class="rounded-2xl border-2 border-line bg-white p-5">
                <h2 class="font-black">{{ __('Contact person') }}</h2>
                @if ($institution->contact_name || $institution->contact_phone || $institution->contact_email)
                    <p class="mt-2 font-semibold">{{ $institution->contact_name }}</p>
                    <p class="text-slate">{{ $institution->contact_phone }}</p>
                    <p class="break-all text-slate">{{ $institution->contact_email }}</p>
                @else
                    <p class="mt-2 text-slate">{{ __('Not recorded') }}</p>
                @endif
            </div>

            <div class="rounded-2xl border-2 border-line bg-white p-5">
                <h2 class="font-black">{{ __('Regional coordinators') }}</h2>
                @forelse ($coordinators as $coordinator)
                    <p class="mt-2 font-semibold">{{ $coordinator->full_name }} <span class="block font-normal text-slate">{{ $coordinator->phone }}</span></p>
                @empty
                    <p class="mt-2 text-slate">{{ __('No coordinator for :region yet.', ['region' => $institution->region]) }}</p>
                @endforelse
            </div>

            @if ($can['members'])
                <a wire:navigate href="{{ route('admin.members.index', ['institution' => $institution->institution_id]) }}" class="btn-ghost w-full">{{ __('See members') }}</a>
            @endif

            @if ($can['status'])
                <div class="rounded-2xl border-2 border-line bg-white p-5">
                    <h2 class="font-black">{{ $isActive ? __('Deactivate institution') : __('Reactivate institution') }}</h2>
                    <p class="mt-1 text-slate">
                        {{ $isActive ? __('New students will not be able to choose it. Existing members and records are kept.') : __('Students will be able to choose it again when registering.') }}
                    </p>
                    @if ($isActive && ! $changingStatus)
                        <button type="button" wire:click="$set('changingStatus', true)" class="btn mt-4 w-full border-2 border-line hover:border-danger hover:text-danger">{{ __('Deactivate') }}</button>
                    @elseif ($isActive)
                        <form wire:submit="changeStatus" class="mt-4">
                            <label for="statusReason" class="block font-extrabold">{{ __('Reason') }}</label>
                            <textarea wire:model="statusReason" id="statusReason" rows="3" class="field"></textarea>
                            @error('statusReason')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                            <div class="mt-3 grid gap-2">
                                <button type="submit" class="btn bg-danger text-white hover:brightness-110">{{ __('Deactivate institution') }}</button>
                                <button type="button" wire:click="$set('changingStatus', false)" class="btn hover:bg-mist">{{ __('Cancel') }}</button>
                            </div>
                        </form>
                    @else
                        <button type="button" wire:click="changeStatus" class="btn mt-4 w-full bg-savanna text-white hover:brightness-110">{{ __('Reactivate') }}</button>
                    @endif
                </div>
            @endif
        </aside>
    </div>
</div>
