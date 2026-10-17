@php
    $types = ['article' => __('Article'), 'video' => __('Video'), 'infographic' => __('Infographic'), 'document' => __('Document')];
    $levels = ['beginner' => __('Beginner'), 'intermediate' => __('Intermediate'), 'advanced' => __('Advanced')];
@endphp
<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Learn') }}</h1>
            <p class="mt-1 text-lg text-slate">{{ trans_choice('{0} Start your first lesson today.|{1} You have completed :count lesson.|[2,*] You have completed :count lessons.', $completedCount, ['count' => $completedCount]) }}</p>
        </div>
    </div>

    @if ($resume && ! $filtering)
        <a wire:navigate href="{{ route('member.learn.lesson', $resume->content) }}" class="sun-hatch mt-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border-4 border-ink p-5 shadow-[6px_6px_0_var(--color-ink)] transition hover:-translate-y-0.5">
            <span>
                <span class="block text-sm font-extrabold text-ink-soft">{{ __('Continue where you left off') }}</span>
                <span class="text-xl font-black">{{ $resume->content->title }}</span>
            </span>
            <span class="btn-primary">{{ __('Continue') }}</span>
        </a>
    @endif

    @if ($modules->isNotEmpty() && ! $filtering)
        <section class="mt-10" aria-labelledby="modules-heading">
            <h2 id="modules-heading" class="text-2xl font-black">{{ __('Learning modules') }}</h2>
            <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($modules as ['module' => $module, 'status' => $status])
                    <li wire:key="module-{{ $module->id }}">
                        <a wire:navigate href="{{ route('member.learn.module', $module) }}" class="flex h-full flex-col rounded-2xl border-2 border-line bg-white p-5 transition hover:border-ink">
                            <span class="text-sm font-extrabold text-slate">{{ $module->category?->name() }}</span>
                            <span class="mt-1 text-lg font-black leading-snug">{{ $module->title }}</span>
                            <span class="mt-2 flex-1 text-slate">{{ \Illuminate\Support\Str::limit($module->summary, 110) }}</span>
                            <span class="mt-4 block h-3 overflow-hidden rounded-full bg-mist" role="progressbar" aria-valuenow="{{ $status['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Progress') }}">
                                <span @class(['block h-full rounded-full', 'bg-savanna' => $status['complete'], 'bg-brand' => ! $status['complete']]) style="width: {{ $status['percent'] }}%"></span>
                            </span>
                            <span class="mt-2 text-sm font-bold">{{ $status['complete'] ? __('Completed') : __(':done of :total lessons', ['done' => $status['done'], 'total' => $status['required']]) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="mt-10" aria-labelledby="lessons-heading">
        <h2 id="lessons-heading" class="text-2xl font-black">{{ __('All lessons') }}</h2>
        <div class="mt-4 grid gap-3 md:grid-cols-[2fr_1fr_1fr_1fr]">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search lessons, e.g. VAT or receipts') }}" class="field mt-0" aria-label="{{ __('Search lessons') }}">
            <select wire:model.live="category" class="field mt-0" aria-label="{{ __('Category') }}">
                <option value="">{{ __('All categories') }}</option>
                @foreach ($categories as $option)
                    <option value="{{ $option->id }}">{{ $option->name() }}</option>
                @endforeach
            </select>
            <select wire:model.live="type" class="field mt-0" aria-label="{{ __('Type') }}">
                <option value="">{{ __('All types') }}</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="level" class="field mt-0" aria-label="{{ __('Level') }}">
                <option value="">{{ __('All levels') }}</option>
                @foreach ($levels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <ul class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-60">
            @forelse ($this->lessons as $lesson)
                @php($state = $progress->get($lesson->content_id))
                <li wire:key="lesson-{{ $lesson->content_id }}">
                    <a wire:navigate href="{{ route('member.learn.lesson', $lesson) }}" class="flex h-full flex-col rounded-2xl border-2 border-line bg-white p-5 transition hover:border-ink">
                        <span class="flex flex-wrap items-center gap-2 text-sm font-extrabold">
                            <span class="rounded-full bg-brand px-2.5 py-0.5">{{ $types[$lesson->content_type] ?? $lesson->content_type }}</span>
                            <span class="text-slate">{{ $levels[$lesson->level] ?? '' }}@if ($lesson->estimated_minutes) · {{ __(':minutes min', ['minutes' => $lesson->estimated_minutes]) }}@endif</span>
                        </span>
                        <span class="mt-3 flex-1 text-lg font-black leading-snug">{{ $lesson->title }}</span>
                        @if ($lesson->summary)<span class="mt-2 text-slate">{{ \Illuminate\Support\Str::limit($lesson->summary, 100) }}</span>@endif
                        <span class="mt-3 text-sm font-extrabold">
                            @if ($state?->status === 'completed')
                                <span class="text-savanna">✓ {{ __('Completed') }}</span>
                            @elseif ($state?->status === 'in_progress')
                                <span class="text-ink-soft">{{ __('Started') }}</span>
                            @endif
                        </span>
                    </a>
                </li>
            @empty
                <li class="rounded-2xl bg-white p-8 text-center sm:col-span-2 lg:col-span-3">
                    <p class="text-lg font-black">{{ $filtering ? __('No lessons match your search.') : __('Lessons will appear here as soon as TRA publishes them.') }}</p>
                    @if ($filtering)<button type="button" wire:click="clearFilters" class="link mt-2">{{ __('Clear filters') }}</button>@endif
                </li>
            @endforelse
        </ul>
        <div class="mt-6">{{ $this->lessons->links() }}</div>
    </section>
</div>
