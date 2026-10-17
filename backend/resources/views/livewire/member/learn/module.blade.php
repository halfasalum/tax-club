<div class="mx-auto max-w-3xl">
    <a href="{{ route('member.learn') }}" class="link">← {{ __('Learn') }}</a>
    <p class="mt-4 text-sm font-extrabold text-slate">{{ $module->category?->name() }}</p>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ $module->title }}</h1>
    @if ($module->summary)<p class="mt-2 text-lg text-slate">{{ $module->summary }}</p>@endif

    <div class="mt-6 rounded-2xl border-2 border-line bg-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="font-black">{{ $status['complete'] ? __('Module completed') : __(':done of :total required lessons done', ['done' => $status['done'], 'total' => $status['required']]) }}</p>
            @if ($nextLesson)
                <a href="{{ route('member.learn.lesson', $nextLesson) }}" class="btn-primary py-2">{{ $status['done'] > 0 ? __('Continue') : __('Start') }}</a>
            @endif
        </div>
        <span class="mt-3 block h-3 overflow-hidden rounded-full bg-mist" role="progressbar" aria-valuenow="{{ $status['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Progress') }}">
            <span @class(['block h-full rounded-full', 'bg-savanna' => $status['complete'], 'bg-brand' => ! $status['complete']]) style="width: {{ $status['percent'] }}%"></span>
        </span>
    </div>

    <ol class="mt-6 space-y-3">
        @foreach ($lessons as $index => $lesson)
            @php($done = $progress->get($lesson->content_id)?->status === 'completed')
            <li wire:key="item-{{ $lesson->content_id }}">
                <a href="{{ route('member.learn.lesson', $lesson) }}" class="flex items-center gap-4 rounded-2xl border-2 border-line bg-white p-4 transition hover:border-ink">
                    <span @class(['grid size-10 shrink-0 place-items-center rounded-full font-black', 'bg-savanna text-white' => $done, 'bg-brand' => ! $done])>{{ $done ? '✓' : $index + 1 }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-black">{{ $lesson->title }}</span>
                        <span class="text-sm text-slate">{{ $lesson->pivot->is_required ? __('Required') : __('Optional') }}@if ($lesson->estimated_minutes) · {{ __(':minutes min', ['minutes' => $lesson->estimated_minutes]) }}@endif</span>
                    </span>
                </a>
            </li>
        @endforeach
    </ol>
</div>
