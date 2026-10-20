@php
    $categories = ['secondary' => __('Secondary school'), 'college' => __('College'), 'university' => __('University')];
    $user = auth()->user();
@endphp
<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Institutions') }}</h1>
            <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Schools, colleges and universities taking part in the Tax Club programme. Only active institutions appear in the registration form.') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($user->hasControl('import_institutions'))
                <a wire:navigate href="{{ route('admin.institutions.import') }}" class="btn-ghost">{{ __('Import CSV') }}</a>
            @endif
            @if ($user->hasControl('add_institution'))
                <a wire:navigate href="{{ route('admin.institutions.create') }}" class="btn-brand">{{ __('Register institution') }}</a>
            @endif
        </div>
    </div>

    <div class="mt-6 grid gap-3 md:grid-cols-[2fr_1fr_1fr_1fr]">
        <div>
            <label for="search" class="sr-only">{{ __('Search by name, code or district') }}</label>
            <input wire:model.live.debounce.300ms="search" id="search" type="search" placeholder="{{ __('Search by name, code or district') }}" class="field mt-0">
        </div>
        <div>
            <label for="type" class="sr-only">{{ __('Category') }}</label>
            <select wire:model.live="type" id="type" class="field mt-0">
                <option value="">{{ __('All categories') }}</option>
                @foreach ($categories as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="region" class="sr-only">{{ __('Region') }}</label>
            <select wire:model.live="region" id="region" class="field mt-0">
                <option value="">{{ __('All regions') }}</option>
                @foreach ($regions as $regionName)
                    <option value="{{ $regionName }}">{{ $regionName }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="sr-only">{{ __('Status') }}</label>
            <select wire:model.live="status" id="status" class="field mt-0">
                <option value="">{{ __('Any status') }}</option>
                <option value="active">{{ __('Active') }}</option>
                <option value="inactive">{{ __('Inactive') }}</option>
            </select>
        </div>
    </div>

    <div class="mt-6" wire:loading.class="opacity-60" wire:target="search, type, region, status, clearFilters">
        @if ($this->institutions->isEmpty())
            <div class="rounded-2xl bg-white p-8 text-center">
                @if ($hasFilters)
                    <p class="text-xl font-black">{{ __('No institutions match these filters.') }}</p>
                    <button type="button" wire:click="clearFilters" class="link mt-3">{{ __('Clear filters') }}</button>
                @else
                    <p class="text-xl font-black">{{ __('No institutions yet') }}</p>
                    <p class="mt-2 text-lg text-slate">{{ __('Register the first institution, or import TRA’s list from a CSV file.') }}</p>
                @endif
            </div>
        @else
            <p class="font-bold text-slate">{{ trans_choice(':count institution|:count institutions', $this->institutions->total(), ['count' => number_format($this->institutions->total())]) }}</p>
            <div class="mt-3 overflow-x-auto rounded-2xl border-2 border-line bg-white">
                <table class="w-full min-w-[48rem] text-left">
                    <thead class="bg-ink text-white">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-extrabold">{{ __('Institution') }}</th>
                            <th scope="col" class="px-5 py-3 font-extrabold">{{ __('Location') }}</th>
                            <th scope="col" class="px-5 py-3 font-extrabold">{{ __('Tax Club') }}</th>
                            <th scope="col" class="px-5 py-3 text-right font-extrabold">{{ __('Members') }}</th>
                            <th scope="col" class="px-5 py-3 font-extrabold">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->institutions as $institution)
                            <tr wire:key="institution-{{ $institution->institution_id }}" class="hover:bg-brand-wash/60">
                                <td class="px-5 py-3.5">
                                    <a wire:navigate href="{{ route('admin.institutions.show', $institution) }}" class="font-extrabold hover:underline hover:decoration-brand hover:decoration-[3px] hover:underline-offset-4">{{ $institution->name }}</a>
                                    <span class="block text-sm text-slate"><span class="font-receipt">{{ $institution->code }}</span>, {{ $categories[$institution->type] ?? $institution->type }}</span>
                                </td>
                                <td class="px-5 py-3.5">{{ collect([$institution->district, $institution->region])->filter()->implode(', ') }}</td>
                                <td class="px-5 py-3.5">
                                    @if ($institution->activeTaxClub)
                                        <span class="inline-flex items-center gap-1.5 font-semibold"><span class="size-2 rounded-full bg-savanna" aria-hidden="true"></span>{{ __('Established') }}</span>
                                    @else
                                        <span class="text-slate">{{ __('Not yet') }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right tabular-nums">
                                    <span class="font-extrabold">{{ number_format($institution->members_count) }}</span>
                                    @if ($institution->pending_count > 0)
                                        <span class="block text-sm text-slate">{{ trans_choice(':count waiting|:count waiting', $institution->pending_count, ['count' => $institution->pending_count]) }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    <span @class(['rounded-full px-3 py-1 text-sm font-extrabold', 'bg-savanna text-white' => $institution->status === 'active', 'bg-mist text-ink-soft' => $institution->status !== 'active'])>
                                        {{ $institution->status === 'active' ? __('Active') : __('Inactive') }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-6">{{ $this->institutions->links() }}</div>
        @endif
    </div>
</div>
