<div class="mx-auto max-w-3xl">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Notifications') }}</h1>
            <p class="mt-1 text-lg text-slate">{{ trans_choice('{0} You are all caught up.|{1} :count unread message|[2,*] :count unread messages', $unread, ['count' => $unread]) }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <label class="flex items-center gap-2 font-bold">
                <input type="checkbox" wire:model.live="unreadOnly" class="size-4 accent-ink"> {{ __('Unread only') }}
            </label>
            @if ($unread > 0)
                <button type="button" wire:click="markAllRead" class="btn-ghost py-2">{{ __('Mark all as read') }}</button>
            @endif
        </div>
    </div>

    <ul class="mt-6 space-y-3">
        @forelse ($this->notifications as $notification)
            <li wire:key="note-{{ $notification->id }}">
                <button type="button" wire:click="open('{{ $notification->id }}')"
                        @class(['flex w-full gap-4 rounded-2xl border-2 p-5 text-left transition hover:border-ink', 'border-ink bg-brand-wash' => ! $notification->read_at, 'border-line bg-white' => $notification->read_at])>
                    <span @class(['mt-1.5 size-3 shrink-0 rounded-full', 'bg-danger' => ! $notification->read_at, 'bg-line' => $notification->read_at]) aria-hidden="true"></span>
                    <span class="min-w-0 flex-1">
                        <span @class(['block text-lg leading-snug', 'font-extrabold' => ! $notification->read_at, 'font-semibold' => $notification->read_at])>{{ \Illuminate\Support\Str::after($notification->data['message'] ?? '', ': ') }}</span>
                        <span class="mt-1 block text-sm text-slate">
                            {{ $notification->created_at->diffForHumans() }}
                            @unless ($notification->read_at)<span class="sr-only">, {{ __('unread') }}</span>@endunless
                        </span>
                    </span>
                </button>
            </li>
        @empty
            <li class="rounded-2xl bg-white p-10 text-center text-lg font-bold text-slate">{{ __('No notifications yet. Updates about your membership will appear here.') }}</li>
        @endforelse
    </ul>

    <div class="mt-6">{{ $this->notifications->links() }}</div>
</div>
