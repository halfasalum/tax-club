@php
    use App\Support\MemberAccounts;
    use App\Models\User;

    $categories = ['secondary' => __('Secondary school'), 'college' => __('College'), 'university' => __('University'), 'alumni' => __('Alumni')];
    $statuses = [User::STATUS_ACTIVE => __('Active'), User::STATUS_SUSPENDED => __('Suspended'), User::STATUS_DEACTIVATED => __('Deactivated'), User::STATUS_REJECTED => __('Not approved'), '' => __('Everyone')];
    $statusStyles = [User::STATUS_ACTIVE => 'bg-[#dcfce7] text-savanna', User::STATUS_SUSPENDED => 'bg-[#fef3f2] text-danger', User::STATUS_DEACTIVATED => 'bg-mist text-slate', User::STATUS_REJECTED => 'bg-mist text-slate'];
@endphp
<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Members') }}</h1>
    <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Find members, follow their membership status, and move graduating students to alumni.') }}</p>

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
            <label for="search" class="sr-only">{{ __('Search by name, phone or membership number') }}</label>
            <input wire:model.live.debounce.300ms="search" id="search" type="search" placeholder="{{ __('Search by name, phone or membership number') }}" class="field mt-0">
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

    <div class="mt-4 flex flex-wrap items-center gap-3">
        <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border-2 border-line bg-white px-4 py-2 font-extrabold">
            <input type="checkbox" wire:model.live="alumniRequests" class="size-4 accent-ink">
            {{ __('Asked to become alumni') }}
            @if ($this->alumniRequestCount > 0)
                <span class="rounded-full bg-brand px-2 py-0.5 text-sm font-black">{{ $this->alumniRequestCount }}</span>
            @endif
        </label>
        <button type="button" wire:click="clearFilters" class="link">{{ __('Clear filters') }}</button>
    </div>

    @if ($statusMessage)
        <p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>
    @endif

    @if ($canManageAlumni && $this->members->isNotEmpty())
        <div class="mt-6 flex flex-wrap items-center gap-3 rounded-2xl bg-brand-wash p-4">
            <p class="font-extrabold">{{ trans_choice('{0} No members selected|{1} :count member selected|[2,*] :count members selected', count($selected), ['count' => count($selected)]) }}</p>
            <button type="button" wire:click="selectPage" class="btn-ghost py-2">{{ __('Select students on this page') }}</button>
            <button type="button" wire:click="moveSelectedToAlumni" wire:confirm="{{ __('Move the selected members to alumni? They keep their membership number and points.') }}" class="btn-primary py-2" @disabled($selected === [])>{{ __('Move selected to alumni') }}</button>
            @error('selected')<p class="w-full font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
        </div>
    @endif

    <div class="mt-6 overflow-x-auto rounded-2xl border-2 border-line bg-white" wire:loading.class="opacity-60">
        <table class="w-full min-w-[44rem] text-left">
            <thead class="border-b-2 border-line bg-mist text-sm">
                <tr>
                    @if ($canManageAlumni)<th scope="col" class="w-10 px-4 py-3"><span class="sr-only">{{ __('Select') }}</span></th>@endif
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Member') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Institution') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Category') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($this->members as $member)
                    <tr wire:key="member-{{ $member->user_id }}" class="hover:bg-brand-wash/50">
                        @if ($canManageAlumni)
                            <td class="px-4 py-3">
                                @if (MemberAccounts::canBecomeAlumni($member))
                                    <input type="checkbox" wire:model.live="selected" value="{{ $member->user_id }}" class="size-4 accent-ink" aria-label="{{ __('Select :name', ['name' => $member->full_name]) }}">
                                @endif
                            </td>
                        @endif
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.members.show', $member) }}" class="font-black hover:underline">{{ $member->full_name }}</a>
                            @if ($member->isMinor())<span class="ml-1 rounded-full bg-ink px-2 py-0.5 text-xs font-extrabold text-brand">{{ __('Under 18') }}</span>@endif
                            @if ($member->alumni_requested_at)<span class="ml-1 rounded-full bg-brand px-2 py-0.5 text-xs font-extrabold">{{ __('Alumni request') }}</span>@endif
                            <span class="block font-receipt text-sm text-slate">{{ $member->membership_id ?? $member->registration_reference }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $member->institution?->name ?? '—' }}<span class="block text-sm text-slate">{{ $member->institution?->region }}</span></td>
                        <td class="px-4 py-3 font-bold">{{ $categories[$member->user_type] ?? $member->user_type }}</td>
                        <td class="px-4 py-3"><span @class(['rounded-full px-3 py-1 text-sm font-extrabold', $statusStyles[$member->account_status] ?? 'bg-mist'])>{{ MemberAccounts::statusLabel($member->account_status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-lg font-bold text-slate">{{ __('No members match these filters.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $this->members->links() }}</div>
</div>
