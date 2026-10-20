<div class="mx-auto max-w-2xl">
    <a wire:navigate href="{{ route('member.quizzes') }}" class="link">← {{ __('Quizzes') }}</a>
    <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ $quiz->title }}</h1>
    @if ($quiz->description)<p class="mt-2 text-lg text-slate">{{ $quiz->description }}</p>@endif

    <section class="mt-6 rounded-3xl border-2 border-line bg-white p-6">
        <h2 class="text-xl font-black">{{ __('Before you start') }}</h2>
        <ul class="mt-3 space-y-2 text-lg">
            <li>📝 {{ trans_choice(':count question|:count questions', $questionCount, ['count' => $questionCount]) }}</li>
            <li>⏱ {{ $quiz->time_limit_minutes ? __('You have :minutes minutes once you start. The quiz is submitted automatically when time runs out.', ['minutes' => $quiz->time_limit_minutes]) : __('No time limit.') }}</li>
            <li>🎯 {{ __('Pass mark: :percent%', ['percent' => $quiz->pass_mark]) }}</li>
            <li>🔁 {{ $status['attempts_left'] === null ? __('You can try as many times as you like.') : trans_choice('{0} No attempts left.|{1} :count attempt left.|[2,*] :count attempts left.', $status['attempts_left'], ['count' => $status['attempts_left']]) }}
                @if (! $quiz->isPractice()) {{ $quiz->retained_result === 'latest' ? __('Your latest attempt counts.') : __('Your best attempt counts.') }}@endif
            </li>
            @if ($quiz->isPractice())<li>💡 {{ __('Practice quiz: no points, just learning.') }}</li>@endif
        </ul>
        @if ($quiz->instructions)<p class="mt-4 rounded-xl bg-brand-wash p-4">{{ $quiz->instructions }}</p>@endif

        @error('start')<p class="mt-4 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
        @if ($status['in_progress'])
            <a wire:navigate href="{{ route('member.quizzes.take', $status['in_progress']) }}" class="btn-brand mt-6 w-full text-lg">{{ __('Continue your attempt') }}</a>
        @elseif ($status['can_start'])
            <button type="button" wire:click="start" class="btn-primary mt-6 w-full text-lg">{{ $attempts->isEmpty() ? __('Start quiz') : __('Try again') }}</button>
        @else
            <p class="mt-6 rounded-xl bg-mist p-4 font-bold">{{ $status['reason'] }}</p>
        @endif
    </section>

    @if ($attempts->isNotEmpty())
        <section class="mt-6 rounded-3xl border-2 border-line bg-white p-6">
            <h2 class="text-xl font-black">{{ __('Your attempts') }}</h2>
            <ul class="mt-3 divide-y divide-line">
                @foreach ($attempts as $attempt)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <span>
                            <span class="font-black">{{ __('Attempt :number', ['number' => $attempt->attempt_number]) }}</span>
                            <span class="text-slate">· {{ $attempt->submitted_at?->translatedFormat('j M Y H:i') }}</span>
                            @if ($attempt->is_best_attempt)<span class="ml-1 rounded-full bg-brand px-2 py-0.5 text-xs font-extrabold">{{ __('Counts') }}</span>@endif
                        </span>
                        <a wire:navigate href="{{ route('member.quizzes.result', $attempt) }}" @class(['font-black', 'text-savanna' => $attempt->passed])>{{ (int) $attempt->percent }}% →</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
