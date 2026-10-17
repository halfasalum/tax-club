@php
    use App\Models\SmsMessage;
    use App\Support\Sms\SmsRetry;

    $statusStyles = [SmsMessage::STATUS_SENT => 'bg-[#dcfce7] text-savanna', SmsMessage::STATUS_FAILED => 'bg-[#fef3f2] text-danger', SmsMessage::STATUS_PENDING => 'bg-brand-wash'];
    $statusLabels = [SmsMessage::STATUS_SENT => __('Sent'), SmsMessage::STATUS_FAILED => __('Failed'), SmsMessage::STATUS_PENDING => __('Waiting')];
@endphp
<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('SMS delivery') }}</h1>
    <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Every SMS the platform sends. Failed messages are retried automatically twice; you can send them again here. Codes are hidden and never resent.') }}</p>

    <dl class="mt-6 grid grid-cols-3 gap-4 md:max-w-2xl">
        @foreach ($this->lastSevenDays as $status => $total)
            <div class="rounded-2xl border-2 border-line bg-white px-5 py-4">
                <dt class="font-extrabold text-slate">{{ $statusLabels[$status] }} <span class="font-semibold">({{ __('7 days') }})</span></dt>
                <dd @class(['mt-1 text-3xl font-black tabular-nums', 'text-danger' => $status === SmsMessage::STATUS_FAILED && $total > 0])>{{ number_format($total) }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="mt-6 grid gap-3 md:grid-cols-3">
        <select wire:model.live="status" class="field mt-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Any status') }}</option>
            @foreach ($statusLabels as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="purpose" class="field mt-0" aria-label="{{ __('Message') }}">
            <option value="">{{ __('All messages') }}</option>
            @foreach ($purposes as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Phone number') }}" class="field mt-0" aria-label="{{ __('Phone number') }}">
    </div>

    @if ($statusMessage)
        <p role="status" class="mt-6 rounded-2xl bg-ink px-5 py-4 font-extrabold text-brand">{{ $statusMessage }}</p>
    @endif

    <div class="mt-6 overflow-x-auto rounded-2xl border-2 border-line bg-white" wire:loading.class="opacity-60">
        <table class="w-full min-w-[52rem] text-left">
            <thead class="border-b-2 border-line bg-mist text-sm">
                <tr>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('When') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('To') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Message') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line align-top">
                @forelse ($this->messages as $sms)
                    <tr wire:key="sms-{{ $sms->id }}">
                        <td class="whitespace-nowrap px-4 py-3 text-sm">{{ $sms->created_at->translatedFormat('j M Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <span class="font-bold">{{ $sms->user?->full_name ?? '—' }}</span>
                            <span class="block font-receipt text-sm text-slate">{{ $sms->to }}</span>
                        </td>
                        <td class="max-w-md px-4 py-3 text-sm">
                            <span class="font-extrabold">{{ $purposes[$sms->purpose] ?? $sms->purpose }}</span>
                            <span class="block text-slate">{{ SmsRetry::displayText($sms) }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span @class(['rounded-full px-3 py-1 text-sm font-extrabold', $statusStyles[$sms->status] ?? 'bg-mist'])>{{ $statusLabels[$sms->status] ?? $sms->status }}</span>
                            <span class="mt-1 block text-sm text-slate">{{ trans_choice(':count attempt|:count attempts', $sms->attempts, ['count' => $sms->attempts]) }}@if ($sms->provider_reference) · #{{ $sms->provider_reference }}@endif</span>
                            @if ($sms->error)<span class="mt-1 block text-sm text-danger">{{ \Illuminate\Support\Str::limit($sms->error, 80) }}</span>@endif
                            @if (SmsRetry::canRetry($sms))
                                <button type="button" wire:click="retry({{ $sms->id }})" class="btn-ghost mt-2 py-1.5 text-sm">{{ __('Send again') }}</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-lg font-bold text-slate">{{ __('No messages match these filters.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $this->messages->links() }}</div>
</div>
