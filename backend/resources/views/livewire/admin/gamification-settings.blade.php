@php
    $ruleLabels = [
        'quiz_passed' => __('Passing a scored quiz'),
        'quiz_correct_answer' => __('Each correct answer (best attempt only)'),
        'event_attended' => __('Attending an event (recorded at check-in)'),
        'lesson_completed' => __('Completing a lesson'),
        'module_completed' => __('Completing a module'),
    ];
    $criteria = ['lessons_completed' => __('lessons completed'), 'modules_completed' => __('modules completed'), 'quizzes_passed' => __('quizzes passed'), 'perfect_scores' => __('perfect scores'), 'points_total' => __('points'), 'events_attended' => __('events attended')];
@endphp
<div class="mx-auto max-w-5xl">
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Points and badges') }}</h1>
    <p class="mt-2 text-lg text-slate">{{ __('Decide how many points each activity earns, and when badges are awarded.') }}</p>

    @if ($statusMessage)
        <p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>
    @endif

    <form wire:submit="saveRules" class="mt-8 space-y-4 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
        <h2 class="text-xl font-black">{{ __('Points rules') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($ruleLabels as $key => $label)
                <div>
                    <label for="rule-{{ $key }}" class="block font-bold">{{ $label }}</label>
                    <input wire:model="rules.{{ $key }}" id="rule-{{ $key }}" type="number" min="0" max="1000" class="field">
                </div>
            @endforeach
            <div>
                <label for="dailyCap" class="block font-bold">{{ __('Most points a member can earn automatically per day') }}</label>
                <input wire:model="dailyCap" id="dailyCap" type="number" min="1" class="field">
            </div>
        </div>
        <label class="flex items-start gap-3 rounded-xl bg-mist p-4">
            <input type="checkbox" wire:model="leaderboardsEnabled" class="mt-1 size-5 accent-ink">
            <span><span class="font-extrabold">{{ __('Show leaderboards to members') }}</span><span class="block text-slate">{{ __('Members appear as first name and last initial. For members under 18 the region is shown instead of the school.') }}</span></span>
        </label>
        <button type="submit" class="btn-primary">{{ __('Save rules') }}</button>
    </form>

    <section class="mt-8 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
        <h2 class="text-xl font-black">{{ __('Badges') }}</h2>
        <ul class="mt-4 divide-y divide-line">
            @foreach ($badges as $item)
                <li class="py-4" wire:key="badge-{{ $item->id }}">
                    @if ($editingBadge === $item->id)
                        <form wire:submit="saveBadge" class="grid gap-3 sm:grid-cols-2">
                            <div><label class="block text-sm font-bold">{{ __('Icon') }}</label><input wire:model="badge.icon" type="text" class="field"></div>
                            <div><label class="block text-sm font-bold">{{ __('Awarded at') }} ({{ $criteria[$item->criteria] ?? $item->criteria }})</label><input wire:model="badge.threshold" type="number" min="1" class="field"></div>
                            <div><label class="block text-sm font-bold">{{ __('Name in Kiswahili') }}</label><input wire:model="badge.name_sw" type="text" class="field"></div>
                            <div><label class="block text-sm font-bold">{{ __('Name in English') }}</label><input wire:model="badge.name_en" type="text" class="field"></div>
                            <div><label class="block text-sm font-bold">{{ __('Description in Kiswahili') }}</label><input wire:model="badge.description_sw" type="text" class="field"></div>
                            <div><label class="block text-sm font-bold">{{ __('Description in English') }}</label><input wire:model="badge.description_en" type="text" class="field"></div>
                            <label class="flex items-center gap-2 font-bold"><input type="checkbox" wire:model="badge.is_active" class="size-4 accent-ink"> {{ __('Active') }}</label>
                            <div class="flex gap-2 sm:justify-end"><button type="submit" class="btn-primary py-2">{{ __('Save') }}</button><button type="button" wire:click="$set('editingBadge', null)" class="btn-ghost py-2">{{ __('Cancel') }}</button></div>
                        </form>
                    @else
                        <div class="flex flex-wrap items-center gap-4">
                            <span class="text-3xl" aria-hidden="true">{{ $item->icon }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="font-black">{{ $item->name() }} @unless ($item->is_active)<span class="text-sm font-bold text-slate">({{ __('off') }})</span>@endunless</p>
                                <p class="text-sm text-slate">{{ __('At :threshold :criteria', ['threshold' => $item->threshold, 'criteria' => $criteria[$item->criteria] ?? $item->criteria]) }} · {{ trans_choice(':count member|:count members', $item->earners_count, ['count' => $item->earners_count]) }}</p>
                            </div>
                            <button type="button" wire:click="editBadge({{ $item->id }})" class="btn-ghost py-1.5">{{ __('Edit') }}</button>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
</div>
