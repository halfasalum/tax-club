<div class="mx-auto max-w-2xl">
    <a href="{{ route('member.quizzes.show', $quiz) }}" class="link">← {{ $quiz->title }}</a>

    <section @class(['mt-6 rounded-3xl border-4 p-8 text-center', 'border-savanna bg-[#ecfdf3]' => $attempt->passed, 'border-ink bg-white' => ! $attempt->passed])>
        <p class="text-6xl font-black tabular-nums">{{ (int) $attempt->percent }}%</p>
        <p class="mt-2 text-xl font-black">{{ $attempt->passed ? __('You passed!') : __('Not passed this time') }}</p>
        <p class="mt-1 text-slate">{{ __(':score of :max marks · pass mark :pass%', ['score' => $attempt->score, 'max' => $attempt->max_score, 'pass' => $quiz->pass_mark]) }}</p>
        @if ($attempt->auto_submitted)<p class="mt-2 font-bold text-ink-soft">{{ __('Time ran out, so your saved answers were submitted.') }}</p>@endif
        @if ($pointsAwarded > 0)
            <p class="mt-4 inline-block rounded-full bg-brand px-4 py-2 text-lg font-black">+{{ $pointsAwarded }} {{ __('points') }}</p>
        @elseif (! $quiz->isPractice() && ! $attempt->is_best_attempt)
            <p class="mt-4 text-slate">{{ __('Your earlier result still counts, so no new points this time.') }}</p>
        @endif
        <div class="mt-6 flex flex-wrap justify-center gap-3">
            @if ($status['can_start'])
                <a href="{{ route('member.quizzes.show', $quiz) }}" class="btn-primary">{{ __('Try again') }}</a>
            @endif
            <a href="{{ route('member.quizzes') }}" class="btn-ghost">{{ __('More quizzes') }}</a>
        </div>
    </section>

    @if ($showAnswers)
        <h2 class="mt-10 text-2xl font-black">{{ __('Your answers') }}</h2>
        <ol class="mt-4 space-y-4">
            @foreach ($questions as $index => $question)
                @php($response = $responses->get($question->question_id))
                @php($chosen = $response?->selected_answer_ids ?? [])
                <li @class(['rounded-2xl border-2 bg-white p-5', 'border-savanna' => $response?->is_correct, 'border-danger' => ! $response?->is_correct])>
                    <p class="font-black">{{ $index + 1 }}. {{ $question->question_text }}</p>
                    <ul class="mt-2 space-y-1">
                        @foreach ($question->answers as $answer)
                            @php($picked = in_array($answer->answer_id, $chosen, true))
                            <li @class(['flex gap-2', 'font-bold text-savanna' => $answer->is_correct, 'text-danger line-through' => $picked && ! $answer->is_correct, 'text-slate' => ! $picked && ! $answer->is_correct])>
                                <span aria-hidden="true">{{ $answer->is_correct ? '✓' : ($picked ? '✗' : '·') }}</span>
                                <span>{{ $answer->answer_text }}@if ($picked) <span class="text-xs font-extrabold">({{ __('your answer') }})</span>@endif</span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($question->explanation)<p class="mt-3 rounded-xl bg-brand-wash p-3">💡 {{ $question->explanation }}</p>@endif
                </li>
            @endforeach
        </ol>
    @elseif ($quiz->show_answers === 'after_close')
        <p class="mt-8 rounded-2xl bg-white p-6 text-center font-bold text-slate">{{ __('Correct answers will be shown after the quiz closes.') }}</p>
    @endif
</div>
