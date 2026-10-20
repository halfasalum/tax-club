@php
    use App\Support\SafetyDesk;

    $categories = SafetyDesk::categoryLabels();
    $outcomes = SafetyDesk::outcomeLabels();
    $sources = ['report' => __('Member reports'), 'filter' => __('Content filter')];
@endphp
<div>
    <a wire:navigate href="{{ route('admin.moderation.index') }}" class="link">← {{ __('Moderation') }}</a>
    <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ __('Safety report') }}</h1>

    <div class="mt-6 flex flex-wrap items-end gap-3">
        <div><label for="from" class="block font-bold">{{ __('From') }}</label><input wire:model.live="from" id="from" type="date" class="field mt-1"></div>
        <div><label for="to" class="block font-bold">{{ __('To') }}</label><input wire:model.live="to" id="to" type="date" class="field mt-1"></div>
    </div>

    <dl class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4" wire:loading.class="opacity-60">
        @foreach ([
            [__('Cases'), $stats['totals']['cases']], [__('Still open'), $stats['totals']['open']], [__('Safeguarding'), $stats['totals']['safeguarding']], [__('Under 18 involved'), $stats['totals']['minors']],
            [__('Restrictions given'), $stats['totals']['restrictions']], [__('Active restrictions'), $stats['totals']['active_restrictions']], [__('Average hours to resolve'), $stats['average_hours'] ?? '—'],
        ] as [$label, $value])
            <div class="rounded-2xl bg-white p-4 shadow-sm"><dt class="text-sm font-extrabold text-slate">{{ $label }}</dt><dd class="text-3xl font-black tabular-nums">{{ $value }}</dd></div>
        @endforeach
    </dl>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @foreach ([
            [__('By category'), $stats['by_category'], $categories],
            [__('By outcome'), $stats['by_outcome'], $outcomes],
            [__('How cases started'), $stats['by_source'], $sources],
            [__('By region of the member'), $stats['by_region'], []],
        ] as [$title, $rows, $labels])
            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ $title }}</h2>
                <ul class="mt-3 divide-y divide-line">
                    @forelse ($rows as $key => $total)
                        <li class="flex justify-between py-1.5"><span>{{ $labels[$key] ?? ($key ?: '—') }}</span><span class="font-bold tabular-nums">{{ $total }}</span></li>
                    @empty
                        <li class="py-2 text-slate">{{ __('Nothing in this period.') }}</li>
                    @endforelse
                </ul>
            </section>
        @endforeach
    </div>

    @if ($canConfigure)
        <form wire:submit="saveSettings" class="mt-8 space-y-4 rounded-3xl border-2 border-line bg-white p-6">
            <h2 class="text-xl font-black">{{ __('Content filter') }}</h2>
            @if ($saved)<p role="status" class="rounded-xl bg-savanna px-4 py-2 font-extrabold text-white">{{ __('Saved.') }}</p>@endif
            <p class="text-slate">{{ __('Posts, comments and questions with these words, or with phone numbers, emails or chat-app names, wait for a moderator instead of appearing.') }}</p>
            <div>
                <label for="blockedWords" class="block font-bold">{{ __('Blocked words') }} <span class="font-semibold text-slate">({{ __('separate with commas') }})</span></label>
                <textarea wire:model="blockedWords" id="blockedWords" rows="4" class="field"></textarea>
            </div>
            <div>
                <label for="autoHideAfter" class="block font-bold">{{ __('Hide content automatically after this many reports') }}</label>
                <input wire:model="autoHideAfter" id="autoHideAfter" type="number" min="1" max="20" class="field w-28">
            </div>
            <button type="submit" class="btn-primary">{{ __('Save') }}</button>
        </form>
    @endif
</div>
