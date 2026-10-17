<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Quizzes') }}</h1>
    <p class="mt-1 text-lg text-slate">{{ __('Test what you have learned and earn points.') }}</p>

    <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($open as $quiz)
            @php($result = $results->get($quiz->quiz_id))
            <li wire:key="quiz-{{ $quiz->quiz_id }}">
                <a href="{{ route('member.quizzes.show', $quiz) }}" class="flex h-full flex-col rounded-2xl border-2 border-line bg-white p-5 transition hover:border-ink">
                    <span class="flex flex-wrap gap-2 text-sm font-extrabold">
                        <span class="rounded-full bg-brand px-2.5 py-0.5">{{ $quiz->isPractice() ? __('Practice') : __('Earns points') }}</span>
                        @if ($quiz->time_limit_minutes)<span class="rounded-full bg-mist px-2.5 py-0.5">{{ __(':minutes min', ['minutes' => $quiz->time_limit_minutes]) }}</span>@endif
                    </span>
                    <span class="mt-3 flex-1 text-lg font-black leading-snug">{{ $quiz->title }}</span>
                    <span class="mt-2 text-sm text-slate">{{ trans_choice(':count question|:count questions', $quiz->questions_count, ['count' => $quiz->questions_count]) }}@if ($quiz->closes_at) · {{ __('closes :date', ['date' => $quiz->closes_at->translatedFormat('j M')]) }}@endif</span>
                    @if ($result)
                        <span @class(['mt-3 text-sm font-extrabold', 'text-savanna' => $result->passed, 'text-ink-soft' => ! $result->passed])>{{ $result->passed ? __('Passed with :percent%', ['percent' => (int) $result->percent]) : __('Best so far: :percent%', ['percent' => (int) $result->percent]) }}</span>
                    @endif
                </a>
            </li>
        @empty
            <li class="rounded-2xl bg-white p-8 text-center text-lg font-bold text-slate sm:col-span-2 lg:col-span-3">{{ __('No quizzes are open right now. Check back soon.') }}</li>
        @endforelse
    </ul>

    @if ($past->isNotEmpty())
        <h2 class="mt-12 text-2xl font-black">{{ __('Closed quizzes you took') }}</h2>
        <ul class="mt-4 divide-y divide-line rounded-2xl border-2 border-line bg-white">
            @foreach ($past as $quiz)
                @php($result = $results->get($quiz->quiz_id))
                <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <span class="font-bold">{{ $quiz->title }}</span>
                    <a href="{{ route('member.quizzes.result', $result) }}" class="link">{{ __(':percent% · see result', ['percent' => (int) $result->percent]) }}</a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
