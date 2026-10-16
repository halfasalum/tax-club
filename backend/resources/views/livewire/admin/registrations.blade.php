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
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Registrations') }}</h1>
    <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Confirm that each student is a member of the Tax Club at their institution. Approved members can sign in and receive a membership number.') }}</p>

    <div class="mt-6 inline-flex flex-wrap gap-1 rounded-2xl bg-white p-1.5 shadow-sm" role="tablist" aria-label="{{ __('Status') }}">
        @foreach ($statuses as $value => $label)
            <button type="button" role="tab" wire:click="$set('status', '{{ $value }}')" aria-selected="{{ $status === $value ? 'true' : 'false' }}"
                    @class(['rounded-xl px-5 py-2.5 font-extrabold transition-colors', 'bg-ink text-brand' => $status === $value, 'text-slate hover:bg-brand-wash hover:text-ink' => $status !== $value])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="mt-5 grid gap-3 md:grid-cols-[2fr_2fr_1fr]">
        <div>
            <label for="search" class="sr-only">{{ __('Search by name, phone or reference') }}</label>
            <input wire:model.live.debounce.300ms="search" id="search" type="search" placeholder="{{ __('Search by name, phone or reference') }}" class="field mt-0">
        </div>
        <div>
            <label for="institution" class="sr-only">{{ __('Institution') }}</label>
            <select wire:model.live="institution" id="institution" class="field mt-0">
                <option value="">{{ __('All institutions') }}</option>
                @foreach ($this->institutions as $option)
                    <option value="{{ $option->institution_id }}">{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="category" class="sr-only">{{ __('Category') }}</label>
            <select wire:model.live="category" id="category" class="field mt-0">
                <option value="">{{ __('All categories') }}</option>
                @foreach ($categories as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($statusMessage)
        <p role="status" class="mt-6 flex items-center gap-3 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">
            <svg viewBox="0 0 20 20" class="size-6 shrink-0" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.58l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
            {{ $statusMessage }}
        </p>
    @endif

    @error('decision')
        <p role="alert" class="mt-6 rounded-2xl border-2 border-danger bg-danger/5 px-5 py-4 font-extrabold text-danger">{{ $message }}</p>
    @enderror

    <div wire:loading.class="opacity-60" wire:target="status, search, institution, category">
        @if ($this->registrations->isEmpty())
            <div class="mt-8 rounded-2xl bg-white p-8 text-center">
                <p class="text-xl font-black">{{ $status === 'pending_verification' ? __('All caught up') : __('Nothing to show') }}</p>
                <p class="mt-2 text-lg text-slate">
                    {{ $status === 'pending_verification' ? __('No registrations are waiting. New ones will appear here.') : __('No registrations match these filters.') }}
                </p>
            </div>
        @else
            <p class="mt-6 font-bold text-slate">{{ trans_choice(':count registration|:count registrations', $this->registrations->total(), ['count' => number_format($this->registrations->total())]) }}</p>
            <ul class="mt-3 space-y-3">
                @foreach ($this->registrations as $registration)
                    <li wire:key="registration-{{ $registration->user_id }}" class="rounded-2xl border-2 border-line bg-white p-5 transition-colors hover:border-ink/40">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="flex min-w-0 gap-4">
                                <span class="grid size-12 shrink-0 place-items-center rounded-full bg-brand text-lg font-black" aria-hidden="true">{{ mb_substr($registration->full_name, 0, 1) }}</span>
                                <div class="min-w-0">
                                    <p class="text-lg font-black">{{ $registration->full_name }}</p>
                                    <p class="font-semibold text-ink-soft">{{ collect([$registration->institution?->name ?? __('No institution'), $registration->institution?->region])->filter()->implode(', ') }}</p>
                                    <p class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-slate">
                                        <span class="font-receipt font-semibold text-ink">{{ $registration->registration_reference }}</span>
                                        <span>{{ $categories[$registration->user_type] ?? $registration->user_type }}</span>
                                        <span>{{ $registration->phone }}</span>
                                        <span>{{ __('submitted :date', ['date' => $registration->created_at?->translatedFormat('j M Y H:i')]) }}</span>
                                    </p>
                                    @if ($registration->status_reason)
                                        <p class="mt-3 rounded-xl bg-mist px-4 py-2"><span class="font-extrabold">{{ __('Reason') }}:</span> {{ $registration->status_reason }}</p>
                                    @endif
                                </div>
                            </div>

                            @if ($canVerify && $registration->account_status === 'pending_verification' && $rejectingUserId !== $registration->user_id)
                                <div class="flex gap-2">
                                    <button type="button" wire:click="approve('{{ $registration->user_id }}')" wire:loading.attr="disabled" wire:target="approve('{{ $registration->user_id }}')"
                                            class="btn bg-savanna px-5 text-white hover:brightness-110">
                                        {{ __('Approve') }}<span class="sr-only">: {{ $registration->full_name }}</span>
                                    </button>
                                    <button type="button" wire:click="startRejecting('{{ $registration->user_id }}')"
                                            class="btn border-2 border-line px-5 hover:border-danger hover:text-danger">
                                        {{ __('Reject') }}<span class="sr-only">: {{ $registration->full_name }}</span>
                                    </button>
                                </div>
                            @endif
                        </div>

                        @if ($rejectingUserId === $registration->user_id)
                            <form wire:submit="reject" class="mt-5 rounded-2xl border-2 border-danger/40 bg-danger/5 p-5">
                                <label for="rejection-reason" class="block font-extrabold">{{ __('Why is this registration rejected?') }}</label>
                                <p class="text-slate">{{ __('The student will see this reason.') }}</p>
                                <textarea wire:model="rejectionReason" id="rejection-reason" rows="2" class="field" autofocus></textarea>
                                @error('rejectionReason')<p class="mt-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <button type="submit" class="btn bg-danger text-white hover:brightness-110">{{ __('Reject registration') }}</button>
                                    <button type="button" wire:click="cancelRejecting" class="btn hover:bg-mist">{{ __('Cancel') }}</button>
                                </div>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="mt-6">{{ $this->registrations->links() }}</div>
        @endif
    </div>
</div>
