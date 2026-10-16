@php
    $statuses = [
        'pending_verification' => __('Waiting'),
        'active' => __('Approved'),
        'rejected' => __('Rejected'),
    ];
    $categories = [
        'secondary' => __('Secondary school'),
        'college' => __('College'),
        'university' => __('University'),
    ];
@endphp
<div>
    <h1 class="text-3xl font-extrabold tracking-tight">{{ __('Registrations') }}</h1>
    <p class="mt-2 max-w-[65ch] text-slate">{{ __('Confirm that each student is a member of the Tax Club at their institution. Approved members can sign in and receive a membership number.') }}</p>

    <div class="mt-6 flex flex-wrap gap-1 border-b border-line" role="tablist" aria-label="{{ __('Status') }}">
        @foreach ($statuses as $value => $label)
            <button type="button" role="tab" wire:click="$set('status', '{{ $value }}')" aria-selected="{{ $status === $value ? 'true' : 'false' }}"
                    @class(['-mb-px border-b-[3px] px-4 py-2.5 text-sm font-semibold', 'border-flag text-ink' => $status === $value, 'border-transparent text-slate hover:text-ink' => $status !== $value])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="mt-5 grid gap-3 md:grid-cols-[2fr_2fr_1fr]">
        <div>
            <label for="search" class="sr-only">{{ __('Search by name, phone or reference') }}</label>
            <input wire:model.live.debounce.300ms="search" id="search" type="search" placeholder="{{ __('Search by name, phone or reference') }}"
                   class="w-full rounded-md border border-line bg-white px-4 py-2.5 focus:border-ink focus:outline-none">
        </div>
        <div>
            <label for="institution" class="sr-only">{{ __('Institution') }}</label>
            <select wire:model.live="institution" id="institution" class="w-full rounded-md border border-line bg-white px-3 py-2.5 focus:border-ink focus:outline-none">
                <option value="">{{ __('All institutions') }}</option>
                @foreach ($this->institutions as $option)
                    <option value="{{ $option->institution_id }}">{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="category" class="sr-only">{{ __('Category') }}</label>
            <select wire:model.live="category" id="category" class="w-full rounded-md border border-line bg-white px-3 py-2.5 focus:border-ink focus:outline-none">
                <option value="">{{ __('All categories') }}</option>
                @foreach ($categories as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($statusMessage)
        <p role="status" class="mt-6 rounded-md border border-savanna/30 bg-savanna/10 px-4 py-3 font-medium text-savanna">{{ $statusMessage }}</p>
    @endif

    @error('decision')
        <p role="alert" class="mt-6 rounded-md border border-danger/30 bg-danger/5 px-4 py-3 font-medium text-danger">{{ $message }}</p>
    @enderror

    @if ($this->registrations->isEmpty())
        <p class="mt-10 text-slate">
            {{ $status === 'pending_verification' ? __('No registrations are waiting. New ones will appear here.') : __('No registrations match these filters.') }}
        </p>
    @else
        <ul class="mt-6 divide-y divide-line rounded-lg border border-line bg-white">
            @foreach ($this->registrations as $registration)
                <li wire:key="registration-{{ $registration->user_id }}" class="px-5 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="font-semibold">{{ $registration->full_name }}</p>
                            <p class="mt-0.5 text-sm text-slate">{{ collect([$registration->institution?->name ?? __('No institution'), $registration->institution?->region])->filter()->implode(', ') }}</p>
                            <p class="mt-1 text-sm text-slate">
                                <span class="font-receipt text-ink">{{ $registration->registration_reference }}</span>,
                                {{ $categories[$registration->user_type] ?? $registration->user_type }},
                                {{ $registration->phone }},
                                {{ __('submitted :date', ['date' => $registration->created_at?->translatedFormat('j M Y H:i')]) }}
                            </p>
                            @if ($registration->status_reason)
                                <p class="mt-2 text-sm"><span class="font-semibold">{{ __('Reason') }}:</span> {{ $registration->status_reason }}</p>
                            @endif
                        </div>

                        @if ($canVerify && $registration->account_status === 'pending_verification' && $rejectingUserId !== $registration->user_id)
                            <div class="flex gap-2">
                                <button type="button" wire:click="approve('{{ $registration->user_id }}')" wire:loading.attr="disabled"
                                        class="rounded-md bg-savanna px-4 py-2 text-sm font-semibold text-white hover:brightness-110">{{ __('Approve') }}</button>
                                <button type="button" wire:click="startRejecting('{{ $registration->user_id }}')"
                                        class="rounded-md border border-line px-4 py-2 text-sm font-semibold hover:border-danger hover:text-danger">{{ __('Reject') }}</button>
                            </div>
                        @endif
                    </div>

                    @if ($rejectingUserId === $registration->user_id)
                        <form wire:submit="reject" class="mt-4 rounded-md border border-danger/30 bg-danger/5 p-4">
                            <label for="rejection-reason" class="block text-sm font-semibold">{{ __('Why is this registration rejected?') }}</label>
                            <p class="text-sm text-slate">{{ __('The student will see this reason.') }}</p>
                            <textarea wire:model="rejectionReason" id="rejection-reason" rows="2" class="mt-2 w-full rounded-md border border-line bg-white px-3 py-2 focus:border-ink focus:outline-none"></textarea>
                            @error('rejectionReason')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                            <div class="mt-3 flex gap-2">
                                <button type="submit" class="rounded-md bg-danger px-4 py-2 text-sm font-semibold text-white">{{ __('Reject registration') }}</button>
                                <button type="button" wire:click="cancelRejecting" class="rounded-md px-4 py-2 text-sm font-semibold">{{ __('Cancel') }}</button>
                            </div>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
        <div class="mt-6">{{ $this->registrations->links() }}</div>
    @endif
</div>
