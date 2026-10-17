@php
    use App\Livewire\Admin\AuditLog;

    $areas = ['auth' => __('Sign-ins and security'), 'members' => __('Members'), 'institutions' => __('Institutions and Tax Clubs'), 'notifications' => __('Notifications')];
    $show = fn ($value) => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : ($value === null ? '—' : (is_bool($value) ? ($value ? __('Yes') : __('No')) : (string) $value));
@endphp
<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Audit log') }}</h1>
    <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Every change to members, institutions and settings, and every sign-in. Records cannot be edited or deleted.') }}</p>

    <div class="mt-6 grid gap-3 md:grid-cols-4">
        <div>
            <label for="area" class="text-sm font-extrabold">{{ __('Area') }}</label>
            <select wire:model.live="area" id="area" class="field mt-1">
                <option value="">{{ __('Everything') }}</option>
                @foreach ($areas as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="actor" class="text-sm font-extrabold">{{ __('Done by') }}</label>
            <input wire:model.live.debounce.300ms="actor" id="actor" type="search" placeholder="{{ __('Name or phone') }}" class="field mt-1">
        </div>
        <div>
            <label for="from" class="text-sm font-extrabold">{{ __('From') }}</label>
            <input wire:model.live="from" id="from" type="date" class="field mt-1">
        </div>
        <div>
            <label for="to" class="text-sm font-extrabold">{{ __('To') }}</label>
            <input wire:model.live="to" id="to" type="date" class="field mt-1">
        </div>
    </div>
    @if ($hasFilters)
        <button type="button" wire:click="clearFilters" class="link mt-3">{{ __('Clear filters') }}</button>
    @endif

    <div class="mt-6 overflow-x-auto rounded-2xl border-2 border-line bg-white" wire:loading.class="opacity-60">
        <table class="w-full min-w-[48rem] text-left">
            <thead class="border-b-2 border-line bg-mist text-sm">
                <tr>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('When') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Who') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('What') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Changes') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line align-top">
                @forelse ($this->records as $record)
                    <tr wire:key="audit-{{ $record->id }}">
                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                            {{ $record->created_at->translatedFormat('j M Y H:i') }}
                            @if ($record->ip)<span class="block text-slate">{{ $record->ip }}</span>@endif
                        </td>
                        <td class="px-4 py-3 font-bold">{{ $record->actor?->full_name ?? __('System or visitor') }}</td>
                        <td class="px-4 py-3">
                            <span class="font-extrabold">{{ AuditLog::actionLabel($record->action) }}</span>
                            @if ($record->auditable_id)
                                <button type="button" wire:click="$set('subject', '{{ $record->auditable_id }}')" class="block text-left text-sm text-slate underline decoration-dotted" title="{{ __('Show all records for this item') }}">{{ str_replace('_', ' ', $record->auditable_type) }} · {{ \Illuminate\Support\Str::limit($record->auditable_id, 13) }}</button>
                            @endif
                            @if ($record->reason)<span class="mt-1 block text-sm">{{ __('Reason') }}: {{ $record->reason }}</span>@endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if ($record->before || $record->after)
                                <dl class="space-y-1">
                                    @foreach (array_slice($record->changedFields(), 0, 12) as $field)
                                        <div>
                                            <dt class="inline font-extrabold">{{ str_replace('_', ' ', $field) }}:</dt>
                                            <dd class="inline break-all">
                                                @if ($record->before !== null && array_key_exists($field, $record->before))<span class="text-danger line-through">{{ \Illuminate\Support\Str::limit($show($record->before[$field]), 60) }}</span> → @endif
                                                <span class="text-savanna">{{ \Illuminate\Support\Str::limit($show($record->after[$field] ?? null), 60) }}</span>
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @else
                                <span class="text-slate">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-lg font-bold text-slate">{{ __('No records match these filters.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $this->records->links() }}</div>
</div>
