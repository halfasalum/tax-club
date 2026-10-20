@php
    $views = ['all' => __('All'), 'review' => __('Waiting for approval'), 'drafts' => __('Drafts'), 'scheduled' => __('Approved and scheduled'), 'sent' => __('Sent')];
@endphp
<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Broadcasts') }}</h1>
            <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Send announcements by SMS and to the inbox, to everyone or to chosen members. Another officer approves each one before it goes out.') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canRemind)<a wire:navigate href="{{ route('admin.reminders') }}" class="btn-ghost">{{ __('Automatic reminders') }}</a>@endif
            @if ($canCreate)<a wire:navigate href="{{ route('admin.broadcasts.create') }}" class="btn-brand">{{ __('New broadcast') }}</a>@endif
        </div>
    </div>

    <div class="mt-6 flex flex-wrap gap-1 rounded-2xl bg-white p-1.5 shadow-sm" role="tablist" aria-label="{{ __('Status') }}">
        @foreach ($views as $value => $label)
            <button type="button" role="tab" wire:click="$set('view', '{{ $value }}')" aria-selected="{{ $view === $value ? 'true' : 'false' }}"
                    @class(['rounded-xl px-4 py-2 font-extrabold transition-colors', 'bg-ink text-brand' => $view === $value, 'text-slate hover:bg-brand-wash hover:text-ink' => $view !== $value])>
                {{ $label }}@if ($value === 'review' && $waiting > 0)<span class="ml-1 rounded-full bg-brand px-2 text-sm text-ink">{{ $waiting }}</span>@endif
            </button>
        @endforeach
    </div>

    <div class="mt-6 overflow-x-auto rounded-2xl border-2 border-line bg-white" wire:loading.class="opacity-60">
        <table class="w-full min-w-[44rem] text-left">
            <thead class="border-b-2 border-line bg-mist text-sm">
                <tr>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Broadcast') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Who it is for') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Status') }}</th>
                    <th scope="col" class="px-4 py-3 text-right font-extrabold">{{ __('Recipients') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($this->broadcasts as $broadcast)
                    <tr wire:key="bc-{{ $broadcast->broadcast_id }}" class="hover:bg-brand-wash/50">
                        <td class="px-4 py-3">
                            <a wire:navigate href="{{ route('admin.broadcasts.show', $broadcast) }}" class="font-black hover:underline">{{ $broadcast->title }}</a>
                            <span class="block text-sm text-slate"><span class="font-receipt">{{ $broadcast->code }}</span> · {{ collect([$broadcast->send_sms ? 'SMS' : null, $broadcast->send_inbox ? __('Inbox') : null])->filter()->implode(' + ') }}</span>
                        </td>
                        <td class="px-4 py-3">{{ \App\Support\Audience::describe($broadcast->audience) }}</td>
                        <td class="px-4 py-3">
                            <x-editorial-status :status="$broadcast->status" />
                            @if ($broadcast->status === 'scheduled' && $broadcast->send_at)<span class="block text-sm text-slate">{{ $broadcast->send_at->translatedFormat('j M, H:i') }}</span>@endif
                            @if ($broadcast->sent_at)<span class="block text-sm text-slate">{{ $broadcast->sent_at->translatedFormat('j M, H:i') }}</span>@endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $broadcast->recipients_count !== null ? number_format($broadcast->recipients_count) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-lg font-bold text-slate">{{ __('No broadcasts yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $this->broadcasts->links() }}</div>
</div>
