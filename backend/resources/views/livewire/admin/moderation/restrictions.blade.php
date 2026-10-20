@php use App\Support\SafetyDesk; @endphp
<div>
    <a wire:navigate href="{{ route('admin.moderation.index') }}" class="link">← {{ __('Moderation') }}</a>
    <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ __('Restrictions') }}</h1>
    <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Members who cannot use part of the platform for now. Every restriction needs a reason and the member is told.') }}</p>

    @if ($statusMessage)<p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>@endif

    <form wire:submit="restrict" class="mt-6 grid gap-3 rounded-3xl border-2 border-line bg-white p-6 md:grid-cols-[1fr_14rem_7rem]">
        <div>
            <label for="number" class="block font-bold">{{ __('Membership no.') }}</label>
            <input wire:model="number" id="number" type="text" class="field font-receipt" placeholder="TC-26-DSM-000123-4">
            @error('number')<p class="mt-1 font-semibold text-danger">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="type" class="block font-bold">{{ __('Restrict') }}</label>
            <select wire:model="type" id="type" class="field">
                @foreach (\App\Models\UserRestriction::TYPES as $type)<option value="{{ $type }}">{{ ucfirst(SafetyDesk::restrictionLabel($type)) }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="days" class="block font-bold">{{ __('Days') }}</label>
            <input wire:model="days" id="days" type="number" min="1" class="field">
        </div>
        <div class="md:col-span-3">
            <label for="reason" class="block font-bold">{{ __('Reason') }}</label>
            <input wire:model="reason" id="reason" type="text" class="field">
            @error('reason')<p class="mt-1 font-semibold text-danger">{{ $message }}</p>@enderror
        </div>
        <div><button type="submit" class="btn-primary">{{ __('Restrict') }}</button></div>
    </form>

    <div class="mt-6 overflow-x-auto rounded-2xl border-2 border-line bg-white">
        <table class="w-full min-w-[44rem] text-left">
            <thead class="border-b-2 border-line bg-mist text-sm">
                <tr>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Member') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Restricted from') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Until') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Reason') }}</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($restrictions as $restriction)
                    <tr wire:key="r-{{ $restriction->id }}">
                        <td class="px-4 py-3"><span class="font-bold">{{ $restriction->user?->full_name }}</span><span class="block font-receipt text-sm text-slate">{{ $restriction->user?->membership_id }}</span></td>
                        <td class="px-4 py-3">{{ ucfirst(SafetyDesk::restrictionLabel($restriction->type)) }}</td>
                        <td class="px-4 py-3">{{ $restriction->ends_at?->translatedFormat('j M Y') ?? __('Until lifted') }}</td>
                        <td class="px-4 py-3 text-sm">{{ $restriction->reason }}<span class="block text-slate">{{ $restriction->creator?->full_name }}</span></td>
                        <td class="px-4 py-3 text-right">
                            @if ($lifting === $restriction->id)
                                <form wire:submit="lift" class="flex flex-wrap justify-end gap-2">
                                    <input wire:model="liftReason" type="text" class="field mt-0 w-48" placeholder="{{ __('Reason') }}" aria-label="{{ __('Reason') }}">
                                    <button type="submit" class="btn-primary">{{ __('Lift') }}</button>
                                </form>
                                @error('liftReason')<p class="mt-1 font-semibold text-danger">{{ $message }}</p>@enderror
                            @else
                                <button type="button" wire:click="$set('lifting', {{ $restriction->id }})" class="link">{{ __('Lift') }}</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center font-bold text-slate">{{ __('No active restrictions.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
