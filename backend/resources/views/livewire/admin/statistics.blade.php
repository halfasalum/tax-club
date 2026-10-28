@php
    $categories = ['primary' => __('Primary school'), 'secondary' => __('Secondary school'), 'college' => __('College'), 'university' => __('University')];
    $byCategory = ['active_primary', 'active_secondary', 'active_college', 'active_university', 'active_alumni'];
    $tableColumns = ['active_members', 'active_primary', 'active_secondary', 'active_college', 'active_university', 'active_alumni', 'under_18', 'waiting'];
    $max = max(1, max(array_map(fn ($column) => $totals[$column], $byCategory)));
@endphp
<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Statistics') }}</h1>
            <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Members by region, institution and category. Staff accounts are not counted.') }}</p>
        </div>
        <a href="{{ $exportUrl }}" class="btn-brand">{{ __('Download CSV') }}</a>
    </div>

    <div class="mt-6 grid gap-3 sm:grid-cols-2 md:max-w-2xl">
        <div>
            <label for="region" class="text-sm font-extrabold">{{ __('Region') }}</label>
            <select wire:model.live="region" id="region" class="field mt-1">
                <option value="">{{ __('All regions') }}</option>
                @foreach ($this->regions as $row)
                    <option value="{{ $row->region }}">{{ $row->region }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="type" class="text-sm font-extrabold">{{ __('Institution category') }}</label>
            <select wire:model.live="type" id="type" class="field mt-1">
                <option value="">{{ __('All categories') }}</option>
                @foreach ($categories as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <dl class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-5" wire:loading.class="opacity-60">
        @foreach ([
            [__('Active members'), $totals['active_members']],
            [__('Institutions'), $totals['institutions']],
            [__('Joined this month'), $totals['joined_this_month']],
            [__('Under 18'), $totals['under_18']],
            [__('Waiting for confirmation'), $totals['waiting']],
        ] as [$label, $value])
            <div class="rounded-2xl border-2 border-line border-t-8 border-t-brand bg-white px-5 py-4">
                <dt class="font-extrabold text-slate">{{ $label }}</dt>
                <dd class="mt-1 text-3xl font-black tabular-nums">{{ number_format($value) }}</dd>
            </div>
        @endforeach
    </dl>

    <section class="mt-8 rounded-3xl border-2 border-line bg-white p-6" aria-labelledby="category-heading">
        <h2 id="category-heading" class="text-xl font-black">{{ __('Active members by category') }}</h2>
        <ul class="mt-4 space-y-3">
            @foreach ($byCategory as $column)
                <li class="grid grid-cols-[9rem_1fr_4rem] items-center gap-3">
                    <span class="font-bold">{{ $labels[$column] }}</span>
                    <span class="h-4 overflow-hidden rounded-full bg-mist" aria-hidden="true"><span class="block h-full rounded-full bg-brand" style="width: {{ round($totals[$column] / $max * 100) }}%"></span></span>
                    <span class="text-right font-black tabular-nums">{{ number_format($totals[$column]) }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    @if ($region === '' && $this->regions->count() > 1)
        <section class="mt-8" aria-labelledby="region-heading">
            <h2 id="region-heading" class="text-2xl font-black">{{ __('By region') }}</h2>
            <div class="mt-4 overflow-x-auto rounded-2xl border-2 border-line bg-white">
                <table class="w-full min-w-[48rem] text-left">
                    <thead class="bg-ink text-sm text-white">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Region') }}</th>
                            <th scope="col" class="px-4 py-3 text-right font-extrabold">{{ __('Institutions') }}</th>
                            @foreach ($tableColumns as $column)
                                <th scope="col" class="px-4 py-3 text-right font-extrabold">{{ $labels[$column] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->regions as $row)
                            <tr wire:key="region-{{ $row->region }}" class="hover:bg-brand-wash/50">
                                <th scope="row" class="px-4 py-3 text-left"><button type="button" wire:click="$set('region', @js($row->region))" class="font-black hover:underline">{{ $row->region }}</button></th>
                                <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row->institutions) }}</td>
                                @foreach ($tableColumns as $column)
                                    <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row->{$column}) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="mt-8" aria-labelledby="institution-heading">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 id="institution-heading" class="text-2xl font-black">{{ __('By institution') }}</h2>
            <label class="flex items-center gap-2 text-sm font-extrabold">
                {{ __('Sort by') }}
                <select wire:model.live="sort" class="field mt-0 w-auto py-2">
                    @foreach ($tableColumns as $column)
                        <option value="{{ $column }}">{{ $labels[$column] }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="mt-4 overflow-x-auto rounded-2xl border-2 border-line bg-white" wire:loading.class="opacity-60">
            <table class="w-full min-w-[56rem] text-left">
                <thead class="border-b-2 border-line bg-mist text-sm">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Institution') }}</th>
                        <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Tax Club') }}</th>
                        @foreach ($tableColumns as $column)
                            <th scope="col" class="px-4 py-3 text-right font-extrabold">{{ $labels[$column] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($this->institutions as $row)
                        <tr wire:key="inst-{{ $row->institution_id }}">
                            <td class="px-4 py-3">
                                <span class="font-black">{{ $row->name }}</span>
                                <span class="block text-sm text-slate">{{ $row->code }} · {{ $row->district }}, {{ $row->region }}</span>
                            </td>
                            <td class="px-4 py-3">{{ $row->active_clubs > 0 ? __('Yes') : __('No') }}</td>
                            @foreach ($tableColumns as $column)
                                <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row->{$column}) }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-10 text-center text-lg font-bold text-slate">{{ __('No institutions match these filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $this->institutions->links() }}</div>
    </section>
</div>
