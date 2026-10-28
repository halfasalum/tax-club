@php
    use App\Models\MemberStatusChange;
    use App\Models\User;
    use App\Support\MemberAccounts;
    use App\Support\MembershipCard;

    $categories = ['primary' => __('Primary school'), 'secondary' => __('Secondary school'), 'college' => __('College'), 'university' => __('University'), 'alumni' => __('Alumni')];
    $actions = [
        User::STATUS_SUSPENDED => [__('Suspend'), __('Suspend account'), 'btn-ghost'],
        User::STATUS_DEACTIVATED => [__('Deactivate'), __('Deactivate account'), 'btn-ghost'],
        User::STATUS_ACTIVE => [__('Reactivate'), __('Reactivate account'), 'btn-primary'],
    ];
    $statusTone = $member->isActive() ? 'bg-[#dcfce7] text-savanna' : ($member->account_status === User::STATUS_SUSPENDED ? 'bg-[#fef3f2] text-danger' : 'bg-mist text-slate');
@endphp
<div>
    <a wire:navigate href="{{ route('admin.members.index') }}" class="link">← {{ __('Members') }}</a>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-4">
            @if ($member->photo_path)
                <img src="{{ route('members.photo', ['member' => $member, 'v' => md5($member->photo_path)]) }}" alt="" width="96" height="96" class="size-24 rounded-2xl border-4 border-ink object-cover">
            @endif
            <div>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ $member->full_name }}</h1>
            <p class="mt-2 flex flex-wrap items-center gap-2 text-lg">
                <span @class(['rounded-full px-3 py-1 text-sm font-extrabold', $statusTone])>{{ MemberAccounts::statusLabel($member->account_status) }}</span>
                <span class="font-bold text-slate">{{ $categories[$member->user_type] ?? $member->user_type }}</span>
                @if ($member->isMinor())<span class="rounded-full bg-ink px-2.5 py-0.5 text-xs font-extrabold text-brand">{{ __('Under 18') }}</span>@endif
            </p>
            @if ($member->status_reason && ! $member->isActive())
                <p class="mt-2 text-slate">{{ __('Reason') }}: {{ $member->status_reason }}</p>
            @endif
            </div>
        </div>
        @if ($can['audit'])
            <a wire:navigate href="{{ route('admin.audit', ['subject' => $member->user_id]) }}" class="btn-ghost">{{ __('Audit log') }}</a>
        @endif
    </div>

    @if ($statusMessage)
        <p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <section aria-labelledby="details-heading" class="rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
                <h2 id="details-heading" class="text-2xl font-black">{{ __('Details') }}</h2>
                <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
                    @foreach ([
                        [__('Membership no.'), $member->membership_id ?? '—', true],
                        [__('Registration reference'), $member->registration_reference ?? '—', true],
                        [__('Mobile number'), $member->phone ?? '—', false],
                        ...($member->tin ? [[__('TIN'), \App\Support\MemberProfile::formatTin($member->tin), false]] : []),
                        [__('Email'), $member->email ?? '—', false],
                        [__('Institution'), trim(($member->institution?->name ?? '—').', '.($member->institution?->region ?? ''), ', '), false],
                        [__('Date of birth'), $member->date_of_birth ? __('Born :date (age :age)', ['date' => $member->date_of_birth->translatedFormat('j M Y'), 'age' => $member->date_of_birth->age]) : '—', false],
                        [__('Member since'), $member->membership_issued_at?->translatedFormat('j M Y') ?? '—', false],
                        [__('Last sign-in'), $member->last_login?->translatedFormat('j M Y H:i') ?? __('Never'), false],
                        [__('Points'), number_format($member->total_points ?? 0), false],
                        [__('Card checks'), $checks ? __(':count, last :date', ['count' => $checks, 'date' => \Illuminate\Support\Carbon::parse($lastCheck)->translatedFormat('j M Y')]) : __('None yet'), false],
                    ] as [$label, $value, $mono])
                        <div>
                            <dt class="text-sm font-extrabold text-slate">{{ $label }}</dt>
                            <dd @class(['mt-0.5 font-bold', 'font-receipt' => $mono])>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($member->guardian_name)
                    <div class="mt-6 rounded-2xl bg-brand-wash p-4">
                        <p class="font-extrabold">{{ __('Parent or guardian') }}</p>
                        <p>{{ $member->guardian_name }} ({{ __(ucfirst($member->guardian_relationship ?? 'guardian')) }}), {{ $member->guardian_phone }}</p>
                        @if ($member->guardian_consent_confirmed_at)
                            <p class="text-sm text-slate">{{ __('Consent confirmed :date', ['date' => $member->guardian_consent_confirmed_at->translatedFormat('j M Y')]) }}</p>
                        @endif
                    </div>
                @endif
            </section>

            <section aria-labelledby="history-heading" class="rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
                <h2 id="history-heading" class="text-2xl font-black">{{ __('Membership history') }}</h2>
                <ol class="mt-4 space-y-3 border-l-4 border-brand pl-5">
                    @forelse ($history as $change)
                        <li>
                            <p class="font-extrabold">
                                @if ($change->field === MemberStatusChange::FIELD_CATEGORY)
                                    {{ __('Category: :from → :to', ['from' => $categories[$change->from_value] ?? $change->from_value, 'to' => $categories[$change->to_value] ?? $change->to_value]) }}
                                @else
                                    {{ MemberAccounts::statusLabel($change->to_value) }}
                                @endif
                                <span class="font-semibold text-slate">{{ $change->changed_at->translatedFormat('j M Y H:i') }}</span>
                            </p>
                            <p class="text-slate">{{ $change->officer?->full_name ?? __('System') }}@if ($change->reason): {{ $change->reason }}@endif</p>
                        </li>
                    @empty
                        <li class="text-slate">{{ __('No changes recorded yet.') }}</li>
                    @endforelse
                    <li>
                        <p class="font-extrabold">{{ __('Registered') }} <span class="font-semibold text-slate">{{ $member->created_at?->translatedFormat('j M Y H:i') }}</span></p>
                    </li>
                </ol>
            </section>
        </div>

        <aside class="space-y-6">
            @if ($can['status'] && MemberAccounts::allowedStatuses($member) !== [])
                <section aria-labelledby="status-heading" class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 id="status-heading" class="text-xl font-black">{{ __('Account status') }}</h2>
                    @if ($newStatus === '')
                        <p class="mt-2 text-slate">{{ __('Suspending or deactivating signs the member out straight away. The member is told by SMS, with your reason.') }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach (MemberAccounts::allowedStatuses($member) as $status)
                                <button type="button" wire:click="startStatusChange('{{ $status }}')" class="{{ $actions[$status][2] }} py-2">{{ $actions[$status][0] }}</button>
                            @endforeach
                        </div>
                    @else
                        <form wire:submit="changeStatus" class="mt-4 space-y-4">
                            <p class="font-extrabold">{{ $actions[$newStatus][1] }}</p>
                            <div>
                                <label for="statusReason" class="block font-bold">{{ __('Reason (the member will see this)') }}</label>
                                <textarea wire:model="statusReason" id="statusReason" rows="3" @class(['field', 'field-error' => $errors->has('statusReason')])></textarea>
                                @error('statusReason')<p class="mt-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" class="btn-primary flex-1 py-2">{{ __('Confirm') }}</button>
                                <button type="button" wire:click="cancelStatusChange" class="btn-ghost py-2">{{ __('Cancel') }}</button>
                            </div>
                        </form>
                    @endif
                </section>
            @endif

            @if ($can['alumni'] && MemberAccounts::canBecomeAlumni($member))
                <section aria-labelledby="alumni-heading" class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 id="alumni-heading" class="text-xl font-black">{{ __('Alumni') }}</h2>
                    @if ($member->alumni_requested_at)
                        <p class="mt-2 rounded-xl bg-brand-wash p-3 font-bold">{{ __('Asked to become alumni on :date.', ['date' => $member->alumni_requested_at->translatedFormat('j M Y')]) }}</p>
                    @else
                        <p class="mt-2 text-slate">{{ __('Move members who have finished their studies. They keep their number, points and history.') }}</p>
                    @endif
                    <button type="button" wire:click="makeAlumni" wire:confirm="{{ __('Move :name to alumni?', ['name' => $member->full_name]) }}" class="btn-primary mt-4 w-full py-2">{{ $member->alumni_requested_at ? __('Approve alumni request') : __('Move to alumni') }}</button>
                    @error('alumni')<p class="mt-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror

                    @if ($member->alumni_requested_at)
                        <form wire:submit="declineAlumni" class="mt-4 space-y-2">
                            <label for="alumniReason" class="block font-bold">{{ __('Or decline, with a reason') }}</label>
                            <textarea wire:model="alumniReason" id="alumniReason" rows="2" @class(['field', 'field-error' => $errors->has('alumniReason')])></textarea>
                            @error('alumniReason')<p class="font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
                            <button type="submit" class="btn-ghost w-full py-2">{{ __('Decline request') }}</button>
                        </form>
                    @endif
                </section>
            @endif

            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ __('Points') }} <span class="tabular-nums">{{ number_format($member->total_points ?? 0) }}</span></h2>
                <ul class="mt-3 divide-y divide-line text-sm">
                    @forelse ($ledger as $entry)
                        <li class="flex justify-between gap-3 py-2">
                            <span class="min-w-0"><span class="block font-bold">{{ $entry->label() }}</span><span class="block truncate text-slate">{{ $entry->description }}@if ($entry->officer) · {{ $entry->officer->full_name }}@endif</span></span>
                            <span @class(['font-black tabular-nums', 'text-savanna' => $entry->points > 0, 'text-danger' => $entry->points < 0])>{{ $entry->points > 0 ? '+' : '' }}{{ $entry->points }}</span>
                        </li>
                    @empty
                        <li class="py-2 text-slate">{{ __('No points yet.') }}</li>
                    @endforelse
                </ul>
                @if ($can['points'])
                    <form wire:submit="adjustPoints" class="mt-4 space-y-2 border-t-2 border-line pt-4">
                        <p class="font-extrabold">{{ __('Add or remove points') }}</p>
                        <input wire:model="pointsChange" type="number" placeholder="{{ __('e.g. 20 or -10') }}" aria-label="{{ __('Points') }}" @class(['field mt-0', 'field-error' => $errors->has('pointsChange')])>
                        @error('pointsChange')<p class="font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
                        <input wire:model="pointsReason" type="text" placeholder="{{ __('Reason (the member will see this)') }}" aria-label="{{ __('Reason') }}" @class(['field mt-0', 'field-error' => $errors->has('pointsReason')])>
                        @error('pointsReason')<p class="font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
                        <button type="submit" class="btn-ghost w-full py-2">{{ __('Save') }}</button>
                    </form>
                @endif
            </section>

            @if ($member->membership_id)
                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Membership card') }}</h2>
                    <p class="mt-2 font-bold">{{ MembershipCard::isValid($member) ? __('Valid: checks show the card as active.') : __('Not valid: checks show the card as not active.') }}</p>
                </section>
            @endif
        </aside>
    </div>
</div>
