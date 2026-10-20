@php
    use App\Support\SafetyDesk;

    $categories = SafetyDesk::categoryLabels();
    $outcomes = SafetyDesk::outcomeLabels();
    $actions = [
        'flagged' => __('Held by the filter'), 'auto_hidden' => __('Hidden automatically'), 'assigned' => __('Assigned'), 'escalated' => __('Escalated to safeguarding'),
        'note' => __('Note'), 'hidden' => __('Content hidden'), 'removed' => __('Content removed'), 'restored' => __('Content restored'),
        'warned' => __('Warning sent'), 'restricted' => __('Restriction applied'), 'restriction_lifted' => __('Restriction lifted'), 'suspended' => __('Account suspended'),
        'closed' => __('Closed'), 'reopened' => __('Reopened'),
    ];
@endphp
<div>
    <a wire:navigate href="{{ route('admin.moderation.index') }}" class="link">← {{ __('Moderation') }}</a>
    <div class="mt-4">
        <p class="font-receipt text-slate">{{ $case->reference }} · {{ $case->created_at->translatedFormat('j M Y H:i') }}</p>
        <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ $categories[$case->category] ?? $case->category }}</h1>
        <p class="mt-2 flex flex-wrap items-center gap-2">
            <span class="rounded-full bg-ink px-2.5 py-0.5 text-xs font-extrabold text-brand">{{ $case->status === 'closed' ? __('Closed') : ($case->status === 'investigating' ? __('Being investigated') : __('Open')) }}</span>
            <span @class(['rounded-full px-2.5 py-0.5 text-xs font-extrabold', 'bg-danger text-white' => in_array($case->priority, ['urgent', 'high'], true), 'bg-mist' => ! in_array($case->priority, ['urgent', 'high'], true)])>{{ \App\Support\FeedbackDesk::priorityLabels()[$case->priority] ?? '' }}</span>
            @if ($case->safeguarding)<span class="rounded-full bg-[#fef3f2] px-2.5 py-0.5 text-xs font-extrabold text-danger">{{ __('Safeguarding · confidential') }}</span>@endif
            @if ($case->involves_minor)<span class="rounded-full bg-brand px-2.5 py-0.5 text-xs font-extrabold">{{ __('Under 18 involved') }}</span>@endif
            @if ($case->outcome)<span class="text-slate">{{ $outcomes[$case->outcome] ?? $case->outcome }}</span>@endif
        </p>
    </div>

    @if ($statusMessage)<p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>@endif
    @error('action')<p class="mt-6 rounded-2xl bg-[#fef3f2] px-5 py-4 font-bold text-danger" role="alert">{{ $message }}</p>@enderror

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="min-w-0 space-y-6">
            <section class="rounded-3xl border-4 border-ink bg-white p-6">
                <p class="text-sm font-extrabold text-slate">{{ $subject['label'] }} @if ($subject['hidden'])<span class="ml-1 rounded-full bg-mist px-2 py-0.5 text-xs">{{ __('Not visible to members') }}</span>@endif</p>
                @if ($subject['excerpt'])<p class="mt-2 whitespace-pre-line text-lg">{{ $subject['excerpt'] }}</p>@endif
                @if ($subject['url'])<a href="{{ $subject['url'] }}" class="link mt-3 inline-block" target="_blank" rel="noopener">{{ __('Open it') }}</a>@endif
                @if (! $subject['exists'])<p class="mt-2 text-slate">{{ __('The item was deleted.') }}</p>@endif
            </section>

            @if ($owner)
                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Account') }}</h2>
                    <p class="mt-2"><span class="font-extrabold">{{ $owner->full_name }}</span> · <span class="font-receipt">{{ $owner->membership_id }}</span> · {{ $owner->institution?->name }}@if ($owner->isMinor()) · <span class="font-extrabold">{{ __('Under 18') }}</span>@endif</p>
                    <p class="mt-1 text-slate">{{ trans_choice('{0} No earlier cases|{1} :count earlier case|[2,*] :count earlier cases', $ownerHistory['cases'], ['count' => $ownerHistory['cases']]) }} · {{ trans_choice('{0} no warnings|{1} :count warning|[2,*] :count warnings', $ownerHistory['warnings'], ['count' => $ownerHistory['warnings']]) }} · {{ \App\Support\MemberAccounts::statusLabel($owner->account_status) }}</p>
                    @foreach ($ownerHistory['restrictions'] as $restriction)
                        <p class="mt-2 rounded-xl bg-[#fef3f2] p-3 text-sm font-bold text-danger">{{ __('Restricted: :feature', ['feature' => SafetyDesk::restrictionLabel($restriction->type)]) }} {{ $restriction->ends_at ? __('until :date', ['date' => $restriction->ends_at->translatedFormat('j M Y')]) : '' }}</p>
                    @endforeach
                </section>
            @endif

            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ trans_choice('{0} Reports|{1} :count report|[2,*] :count reports', $reports->count(), ['count' => $reports->count()]) }}</h2>
                <ul class="mt-3 divide-y divide-line">
                    @forelse ($reports as $report)
                        <li class="py-3">
                            <p class="font-bold">{{ $categories[$report->category] ?? $report->category }}</p>
                            @if ($report->details)<p class="whitespace-pre-line">{{ $report->details }}</p>@endif
                            <p class="text-sm text-slate">{{ $report->reporter?->full_name ?? __('System') }}@if ($report->reporter?->isMinor()) · {{ __('Under 18') }}@endif · {{ $report->created_at->translatedFormat('j M Y H:i') }}</p>
                        </li>
                    @empty
                        <li class="py-3 text-slate">{{ __('Raised by the content filter, not by a member.') }}</li>
                    @endforelse
                </ul>
            </section>

            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ __('History') }}</h2>
                <ol class="mt-3 space-y-3 border-l-4 border-brand pl-4">
                    @foreach ($history as $step)
                        <li>
                            <p class="font-extrabold">{{ $actions[$step->action] ?? $step->action }}</p>
                            <p class="text-sm text-slate">{{ $step->performer?->full_name ?? __('System') }} · {{ $step->created_at->translatedFormat('j M Y H:i') }}</p>
                            @if ($step->reason)<p class="whitespace-pre-line text-sm">{{ $step->reason }}</p>@endif
                        </li>
                    @endforeach
                </ol>
                <form wire:submit="addNote" class="mt-4 flex flex-wrap items-end gap-3">
                    <div class="min-w-0 flex-1">
                        <label for="note" class="block font-bold">{{ __('Investigation note') }}</label>
                        <input wire:model="note" id="note" type="text" class="field">
                    </div>
                    <button type="submit" class="btn-ghost">{{ __('Add note') }}</button>
                </form>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="space-y-3 rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ __('Assigned to') }}</h2>
                <select wire:model="assignee" class="field mt-0" aria-label="{{ __('Officer') }}">
                    <option value="">{{ __('Nobody') }}</option>
                    @foreach ($officers as $officer)<option value="{{ $officer->user_id }}">{{ $officer->full_name }}</option>@endforeach
                </select>
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="assign" class="btn-ghost">{{ __('Assign') }}</button>
                    @if ($case->assigned_to !== auth()->id())<button type="button" wire:click="takeIt" class="link">{{ __('Assign to me') }}</button>@endif
                </div>
            </section>

            @if ($case->isOpen())
                <section class="space-y-3 rounded-3xl border-4 border-ink bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Take action') }}</h2>
                    <label for="reason" class="block font-bold">{{ __('Reason (kept in the record; sent to the member for warnings and restrictions)') }}</label>
                    <textarea wire:model="reason" id="reason" rows="3" class="field"></textarea>

                    @if ($subject['can_hide'])
                        <p class="pt-2 text-sm font-extrabold text-slate">{{ __('Content') }}</p>
                        <div class="grid grid-cols-2 gap-2">
                            @if ($subject['hidden'])
                                <button type="button" wire:click="restore" class="btn-ghost col-span-2">{{ __('Make visible again') }}</button>
                            @else
                                <button type="button" wire:click="hide" class="btn-ghost">{{ __('Hide') }}</button>
                            @endif
                            <button type="button" wire:click="hide(true)" wire:confirm="{{ __('Remove this content for good?') }}" class="btn border-2 border-line hover:border-danger hover:text-danger">{{ __('Remove') }}</button>
                        </div>
                    @endif

                    @if ($owner && $canRestrict)
                        <p class="pt-2 text-sm font-extrabold text-slate">{{ __('Account') }}</p>
                        <button type="button" wire:click="warn" class="btn-ghost w-full">{{ __('Send a warning') }}</button>
                        <div class="grid grid-cols-[1fr_5rem] gap-2">
                            <select wire:model="restrictionType" class="field mt-0" aria-label="{{ __('What to restrict') }}">
                                @foreach (\App\Models\UserRestriction::TYPES as $type)<option value="{{ $type }}">{{ ucfirst(SafetyDesk::restrictionLabel($type)) }}</option>@endforeach
                            </select>
                            <input wire:model="restrictionDays" type="number" min="1" max="365" class="field mt-0" aria-label="{{ __('Days (empty = until lifted)') }}" title="{{ __('Days (empty = until lifted)') }}">
                        </div>
                        <button type="button" wire:click="restrict" class="btn-ghost w-full">{{ __('Restrict') }}</button>
                        <button type="button" wire:click="suspend" wire:confirm="{{ __('Suspend this account? The member will not be able to sign in.') }}" class="btn w-full border-2 border-line hover:border-danger hover:text-danger">{{ __('Suspend account') }}</button>
                    @endif

                    @unless ($case->safeguarding)
                        <button type="button" wire:click="escalate" class="btn w-full border-2 border-danger text-danger hover:bg-[#fef3f2]">{{ __('Escalate to safeguarding') }}</button>
                    @endunless
                </section>

                <section class="space-y-3 rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Close the case') }}</h2>
                    <select wire:model="outcome" class="field mt-0" aria-label="{{ __('Outcome') }}">
                        @foreach ($outcomes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                    <p class="text-sm text-slate">{{ __('"No rule broken" makes hidden content visible again. The note goes in the record.') }}</p>
                    <button type="button" wire:click="close" class="btn-primary w-full">{{ __('Close case') }}</button>
                </section>
            @else
                <section class="space-y-3 rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Reopen') }}</h2>
                    <textarea wire:model="reason" rows="2" class="field mt-0" aria-label="{{ __('Reason') }}"></textarea>
                    <button type="button" wire:click="reopen" class="btn-ghost w-full">{{ __('Reopen case') }}</button>
                </section>
            @endif
        </aside>
    </div>
</div>
