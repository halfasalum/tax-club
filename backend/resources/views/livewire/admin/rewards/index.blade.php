@php
    use App\Support\Rewards;

    $types = Rewards::typeLabels();
    $statuses = Rewards::statusLabels();
    $views = ['open' => __('In progress'), 'pending_approval' => __('Waiting for approval'), 'delivered' => __('Received'), 'cancelled' => __('Cancelled'), 'all' => __('All')];
@endphp
<div>
    <a wire:navigate href="{{ route('admin.competitions.index') }}" class="link">← {{ __('Competitions') }}</a>
    <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ __('Rewards') }}</h1>
    <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Prizes and recognition for members. Rewards with a cash value need a second officer\'s approval, and every handover is recorded.') }}</p>

    @if ($statusMessage)<p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>@endif
    @error('action')<p class="mt-6 rounded-2xl bg-[#fef3f2] px-5 py-4 font-bold text-danger" role="alert">{{ $message }}</p>@enderror

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_22rem]">
        <section class="min-w-0">
            <div class="flex flex-wrap gap-1 rounded-2xl bg-white p-1.5 shadow-sm" role="tablist" aria-label="{{ __('Status') }}">
                @foreach ($views as $value => $label)
                    <button type="button" role="tab" wire:click="$set('status', '{{ $value }}')" aria-selected="{{ $status === $value ? 'true' : 'false' }}" @class(['rounded-xl px-4 py-2 font-extrabold', 'bg-ink text-brand' => $status === $value, 'text-slate hover:bg-brand-wash' => $status !== $value])>{{ $label }}</button>
                @endforeach
            </div>
            <div class="mt-4 overflow-x-auto rounded-2xl border-2 border-line bg-white">
                <table class="w-full min-w-[44rem] text-left">
                    <thead class="border-b-2 border-line bg-mist text-sm">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Member') }}</th>
                            <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Reward') }}</th>
                            <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Status') }}</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($awards as $award)
                            <tr wire:key="aw-{{ $award->id }}">
                                <td class="px-4 py-3"><span class="font-bold">{{ $award->user?->full_name }}</span><span class="block text-sm text-slate">{{ $award->user?->institution?->name }}</span></td>
                                <td class="px-4 py-3"><span class="font-bold">{{ $award->reward?->name() }}</span><span class="block text-sm text-slate">{{ $award->reason }}</span></td>
                                <td class="px-4 py-3"><span class="font-bold">{{ $statuses[$award->status] ?? $award->status }}</span>@if ($award->delivery_note)<span class="block text-sm text-slate">{{ $award->delivery_note }}</span>@endif</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($acting === $award->id)
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <input wire:model="actionNote" type="text" class="field mt-0 w-56" placeholder="{{ $award->status === 'issued' ? __('Who received it, where, receipt no.') : __('Note or reason') }}" aria-label="{{ __('Note') }}">
                                            @if ($award->status === 'pending_approval' && $can['approve'])<button type="button" wire:click="act({{ $award->id }}, 'approve')" class="btn-primary">{{ __('Approve') }}</button>@endif
                                            @if ($award->status === 'approved' && $can['award'])<button type="button" wire:click="act({{ $award->id }}, 'issue')" class="btn-primary">{{ __('Mark as sent') }}</button>@endif
                                            @if ($award->status === 'issued' && $can['award'])<button type="button" wire:click="act({{ $award->id }}, 'deliver')" class="btn-primary">{{ __('Mark as received') }}</button>@endif
                                            @if ($can['award'])<button type="button" wire:click="act({{ $award->id }}, 'cancel')" class="btn-ghost">{{ __('Cancel') }}</button>@endif
                                        </div>
                                    @elseif (! in_array($award->status, ['delivered', 'cancelled'], true))
                                        <button type="button" wire:click="$set('acting', {{ $award->id }})" class="link">{{ __('Update') }}</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-10 text-center font-bold text-slate">{{ __('No rewards here.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $awards->links() }}</div>
        </section>

        <aside class="space-y-6">
            @if ($can['award'])
                <form wire:submit="award" class="space-y-3 rounded-3xl border-4 border-ink bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Give a reward') }}</h2>
                    <input wire:model="awardNumber" type="text" class="field mt-0 font-receipt" placeholder="{{ __('Membership no.') }}" aria-label="{{ __('Membership no.') }}">
                    @error('awardNumber')<p class="font-semibold text-danger">{{ $message }}</p>@enderror
                    <select wire:model="awardRewardId" class="field mt-0" aria-label="{{ __('Reward') }}">
                        <option value="">{{ __('Choose a reward') }}</option>
                        @foreach ($rewards->where('is_active', true) as $option)<option value="{{ $option->id }}">{{ $option->name() }}</option>@endforeach
                    </select>
                    <input wire:model="awardReason" type="text" class="field mt-0" placeholder="{{ __('What it is for') }}" aria-label="{{ __('What it is for') }}">
                    @error('awardReason')<p class="font-semibold text-danger">{{ $message }}</p>@enderror
                    <button type="submit" class="btn-primary w-full">{{ __('Give reward') }}</button>
                </form>
            @endif

            <section class="space-y-3 rounded-3xl border-2 border-line bg-white p-6">
                <div class="flex items-center justify-between"><h2 class="text-xl font-black">{{ __('Catalogue') }}</h2>@if ($can['manage'])<button type="button" wire:click="newReward" class="link">{{ __('Add') }}</button>@endif</div>
                @if ($editing && $can['manage'])
                    <form wire:submit="saveReward" class="space-y-2 rounded-2xl bg-mist p-3">
                        <input wire:model="reward.name_sw" type="text" class="field mt-0" placeholder="{{ __('Name in Kiswahili') }}" aria-label="{{ __('Name in Kiswahili') }}">
                        <input wire:model="reward.name_en" type="text" class="field mt-0" placeholder="{{ __('Name in English') }}" aria-label="{{ __('Name in English') }}">
                        @error('reward.name_sw')<p class="font-semibold text-danger">{{ $message }}</p>@enderror
                        <select wire:model="reward.type" class="field mt-0" aria-label="{{ __('Type') }}">@foreach ($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                        <div class="grid grid-cols-2 gap-2">
                            <input wire:model="reward.value_tzs" type="number" min="0" class="field mt-0" placeholder="{{ __('Value (TZS)') }}" aria-label="{{ __('Value (TZS)') }}">
                            <input wire:model="reward.stock" type="number" min="0" class="field mt-0" placeholder="{{ __('In stock') }}" aria-label="{{ __('In stock') }}">
                        </div>
                        <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" wire:model="reward.needs_approval" class="size-4 accent-ink"> {{ __('Needs approval (always on when it has a value)') }}</label>
                        <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" wire:model="reward.is_active" class="size-4 accent-ink"> {{ __('Offered') }}</label>
                        <div class="flex gap-2"><button type="submit" class="btn-primary">{{ __('Save') }}</button><button type="button" wire:click="$set('editing', false)" class="btn-ghost">{{ __('Cancel') }}</button></div>
                    </form>
                @endif
                <ul class="divide-y divide-line">
                    @forelse ($rewards as $item)
                        <li class="flex items-center justify-between gap-2 py-2">
                            <span @class(['opacity-50' => ! $item->is_active])><span class="font-bold">{{ $item->name() }}</span><span class="block text-sm text-slate">{{ $types[$item->type] ?? '' }}@if ($item->value_tzs) · TZS {{ number_format($item->value_tzs) }}@endif @if ($item->stock !== null) · {{ __(':count left', ['count' => $item->stock]) }}@endif</span></span>
                            @if ($can['manage'])<button type="button" wire:click="editReward({{ $item->id }})" class="link">{{ __('Edit') }}</button>@endif
                        </li>
                    @empty
                        <li class="py-2 text-slate">{{ __('No rewards yet.') }}</li>
                    @endforelse
                </ul>
            </section>
        </aside>
    </div>
</div>
