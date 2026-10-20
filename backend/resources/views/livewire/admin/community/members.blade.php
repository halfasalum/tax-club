<div class="mx-auto max-w-4xl">
    <a wire:navigate href="{{ route('admin.community.spaces') }}" class="link">← {{ __('Community spaces') }}</a>
    <h1 class="mt-4 text-3xl font-black tracking-tight">{{ $space->name }}</h1>
    <p class="mt-1 text-slate">{{ __('Moderators can remove posts in this space. Patrons are good moderators for their own club.') }}</p>

    @if ($statusMessage)<p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>@endif

    <form wire:submit="add" class="mt-6 space-y-3 rounded-3xl border-2 border-line bg-white p-6">
        <label for="numbers" class="block font-bold">{{ __('Membership numbers') }} <span class="font-semibold text-slate">({{ __('separate with commas or new lines') }})</span></label>
        <textarea wire:model="numbers" id="numbers" rows="3" class="field font-receipt"></textarea>
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model="role" class="field mt-0 w-48" aria-label="{{ __('Role') }}">
                <option value="member">{{ __('Members') }}</option>
                <option value="moderator">{{ __('Moderators') }}</option>
            </select>
            <button type="submit" class="btn-primary">{{ __('Add') }}</button>
        </div>
    </form>

    <ul class="mt-6 divide-y divide-line rounded-2xl border-2 border-line bg-white">
        @forelse ($members as $member)
            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3" wire:key="m-{{ $member->user_id }}">
                <span>
                    <span class="font-bold">{{ $member->full_name }}</span>
                    @if ($member->pivot->role === 'moderator')<span class="ml-1 rounded-full bg-ink px-2 py-0.5 text-xs font-extrabold text-brand">{{ __('Moderator') }}</span>@endif
                    <span class="block text-sm text-slate"><span class="font-receipt">{{ $member->membership_id }}</span> · {{ $member->institution?->name }}</span>
                </span>
                <button type="button" wire:click="remove('{{ $member->user_id }}')" wire:confirm="{{ __('Remove from this space?') }}" class="link">{{ __('Remove') }}</button>
            </li>
        @empty
            <li class="px-5 py-6 text-slate">{{ __('Nobody added yet. Open spaces do not need members to be added.') }}</li>
        @endforelse
    </ul>
</div>
