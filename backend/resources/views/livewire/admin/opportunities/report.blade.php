@php
    $types = \App\Support\OpportunityBoard::typeLabels();
    $categories = \App\Support\Audience::categoryLabels();
@endphp
<div>
    <a wire:navigate href="{{ route('admin.opportunities.index') }}" class="link">← {{ __('Opportunities') }}</a>
    <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ __('Opportunity engagement') }}</h1>
    <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('How members find and use opportunities and career guidance. Figures are totals; no individual member is shown.') }}</p>

    <div class="mt-6 flex flex-wrap items-end gap-3">
        <div>
            <label for="from" class="block font-bold">{{ __('From') }}</label>
            <input wire:model.live="from" id="from" type="date" class="field mt-1">
        </div>
        <div>
            <label for="to" class="block font-bold">{{ __('To') }}</label>
            <input wire:model.live="to" id="to" type="date" class="field mt-1">
        </div>
    </div>

    <dl class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-5" wire:loading.class="opacity-60">
        @foreach ([[__('Opportunities live'), $report['totals']['published']], [__('Views'), $report['totals']['views']], [__('Saves'), $report['totals']['saves']], [__('Application clicks'), $report['totals']['applies']], [__('Members reached'), $report['totals']['members']]] as [$label, $value])
            <div class="rounded-2xl bg-white p-4 shadow-sm"><dt class="text-sm font-extrabold text-slate">{{ $label }}</dt><dd class="text-3xl font-black tabular-nums">{{ number_format($value) }}</dd></div>
        @endforeach
    </dl>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @foreach ([
            [__('By type of opportunity'), $report['by_type'], fn ($row) => $types[$row->label] ?? $row->label],
            [__('By membership category'), $report['by_member_type'], fn ($row) => $categories[$row->label] ?? ($row->label ?: __('Not signed in'))],
            [__('By region'), $report['by_region'], fn ($row) => $row->label ?: __('Unknown')],
            [__('Most engaging opportunities'), $report['top'], fn ($row) => $row->title],
        ] as [$title, $rows, $label])
            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ $title }}</h2>
                <table class="mt-3 w-full text-left">
                    <thead class="text-sm text-slate"><tr><th scope="col" class="py-1 font-extrabold"><span class="sr-only">{{ __('Name') }}</span></th><th scope="col" class="py-1 text-right font-extrabold">{{ __('Viewers') }}</th><th scope="col" class="py-1 text-right font-extrabold">{{ __('Saves') }}</th><th scope="col" class="py-1 text-right font-extrabold">{{ __('Applied') }}</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($rows as $row)
                            <tr><td class="py-1.5 pr-3">{{ $label($row) }}</td><td class="py-1.5 text-right tabular-nums">{{ (int) $row->viewers }}</td><td class="py-1.5 text-right tabular-nums">{{ (int) $row->saves }}</td><td class="py-1.5 text-right tabular-nums">{{ (int) $row->applies }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="py-4 text-slate">{{ __('No activity in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endforeach
        <section class="rounded-3xl border-2 border-line bg-white p-6 lg:col-span-2">
            <h2 class="text-xl font-black">{{ __('Career guidance lessons') }}</h2>
            <table class="mt-3 w-full text-left">
                <thead class="text-sm text-slate"><tr><th scope="col" class="py-1 font-extrabold">{{ __('Lesson') }}</th><th scope="col" class="py-1 text-right font-extrabold">{{ __('Readers') }}</th><th scope="col" class="py-1 text-right font-extrabold">{{ __('Completed') }}</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($report['career'] as $row)
                        <tr><td class="py-1.5 pr-3">{{ $row->title }}</td><td class="py-1.5 text-right tabular-nums">{{ (int) $row->readers }}</td><td class="py-1.5 text-right tabular-nums">{{ (int) $row->completed }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-slate">{{ __('No activity in this period.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
</div>
