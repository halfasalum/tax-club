@php
    $periods = ['week' => __('This week'), 'month' => __('This month'), 'all' => __('All time')];
    $scopes = ['national' => __('Tanzania'), 'region' => __('My region'), 'institution' => __('My school')];
    $member = auth()->user();
@endphp
<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Points and leaderboard') }}</h1>

    <dl class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        @foreach ([[__('Total points'), number_format($member->total_points ?? 0)], [__('This month'), number_format($monthPoints)], [__('Badges'), $earned->count()], [__('Lessons completed'), $counts['lessons_completed']]] as [$label, $value])
            <div class="rounded-2xl border-2 border-line border-t-8 border-t-brand bg-white px-5 py-4">
                <dt class="font-extrabold text-slate">{{ $label }}</dt>
                <dd class="mt-1 text-3xl font-black tabular-nums">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_24rem]">
        <section aria-labelledby="board-heading">
            <h2 id="board-heading" class="text-2xl font-black">{{ __('Leaderboard') }}</h2>
            @if (! $enabled)
                <p class="mt-4 rounded-2xl bg-white p-6 font-bold text-slate">{{ __('Leaderboards are switched off at the moment.') }}</p>
            @else
                <div class="mt-4 flex flex-wrap gap-3">
                    <div class="inline-flex rounded-2xl bg-white p-1 shadow-sm" role="tablist" aria-label="{{ __('Period') }}">
                        @foreach ($periods as $value => $label)
                            <button type="button" role="tab" wire:click="$set('period', '{{ $value }}')" aria-selected="{{ $period === $value ? 'true' : 'false' }}" @class(['rounded-xl px-4 py-2 font-extrabold', 'bg-ink text-brand' => $period === $value, 'text-slate' => $period !== $value])>{{ $label }}</button>
                        @endforeach
                    </div>
                    <div class="inline-flex rounded-2xl bg-white p-1 shadow-sm" role="tablist" aria-label="{{ __('Area') }}">
                        @foreach ($scopes as $value => $label)
                            <button type="button" role="tab" wire:click="$set('scope', '{{ $value }}')" aria-selected="{{ $scope === $value ? 'true' : 'false' }}" @class(['rounded-xl px-4 py-2 font-extrabold', 'bg-ink text-brand' => $scope === $value, 'text-slate' => $scope !== $value])>{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                <ol class="mt-4 divide-y divide-line overflow-hidden rounded-2xl border-2 border-line bg-white" wire:loading.class="opacity-60">
                    @forelse ($ranking['entries'] as $entry)
                        <li @class(['flex items-center gap-4 px-4 py-3', 'bg-brand-wash' => $entry['is_me']])>
                            <span @class(['grid size-10 shrink-0 place-items-center rounded-full font-black', 'bg-brand' => $entry['rank'] <= 3, 'bg-mist' => $entry['rank'] > 3])>{{ $entry['rank'] }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-black">{{ $entry['display_name'] }}@if ($entry['is_me']) <span class="text-sm font-bold text-ink-soft">({{ __('you') }})</span>@endif</span>
                                <span class="block truncate text-sm text-slate">{{ $entry['place'] }}</span>
                            </span>
                            <span class="font-black tabular-nums">{{ number_format($entry['points']) }}</span>
                        </li>
                    @empty
                        <li class="p-8 text-center font-bold text-slate">{{ __('No points earned in this period yet. Be the first!') }}</li>
                    @endforelse
                </ol>
                @if ($ranking['me'] && $ranking['me']['rank'] > \App\Support\Leaderboard::SIZE)
                    <p class="mt-3 rounded-xl bg-brand-wash p-3 font-bold">{{ __('Your position: :rank with :points points', ['rank' => $ranking['me']['rank'], 'points' => $ranking['me']['points']]) }}</p>
                @endif
            @endif
        </section>

        <div class="space-y-6">
            <section class="rounded-3xl border-2 border-line bg-white p-6" aria-labelledby="badges-heading">
                <h2 id="badges-heading" class="text-xl font-black">{{ __('Badges') }}</h2>
                <ul class="mt-4 grid grid-cols-2 gap-3">
                    @foreach ($badges as $badge)
                        @php($has = $earned->has($badge->id))
                        <li @class(['rounded-2xl border-2 p-3 text-center', 'border-ink bg-brand-wash' => $has, 'border-line opacity-60' => ! $has])>
                            <span class="block text-3xl" aria-hidden="true">{{ $badge->icon }}</span>
                            <span class="mt-1 block text-sm font-black leading-tight">{{ $badge->name() }}</span>
                            <span class="mt-1 block text-xs text-slate">{{ $has ? __('Earned :date', ['date' => \Illuminate\Support\Carbon::parse($earned[$badge->id])->translatedFormat('j M Y')]) : $badge->description() }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="rounded-3xl border-2 border-line bg-white p-6" aria-labelledby="history-heading">
                <h2 id="history-heading" class="text-xl font-black">{{ __('Recent points') }}</h2>
                <ul class="mt-3 divide-y divide-line">
                    @forelse ($history as $entry)
                        <li class="flex items-start justify-between gap-3 py-2">
                            <span class="min-w-0">
                                <span class="block font-bold">{{ $entry->label() }}</span>
                                <span class="block truncate text-sm text-slate">{{ $entry->description }} · {{ $entry->created_at->translatedFormat('j M') }}</span>
                            </span>
                            <span @class(['font-black tabular-nums', 'text-savanna' => $entry->points > 0, 'text-danger' => $entry->points < 0])>{{ $entry->points > 0 ? '+' : '' }}{{ $entry->points }}</span>
                        </li>
                    @empty
                        <li class="py-2 text-slate">{{ __('Complete lessons and quizzes to earn points.') }}</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</div>
