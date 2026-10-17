@php use App\Models\QuizQuestion; @endphp
<div class="mx-auto max-w-2xl pb-24">
    <div class="fixed inset-x-0 bottom-0 z-20 lg:left-72 border-t-4 border-brand bg-ink px-4 py-3 text-white print:hidden">
        <div class="mx-auto flex max-w-2xl items-center justify-between gap-3">
            <p class="font-black">{{ __(':answered of :total answered', ['answered' => $answered, 'total' => $questions->count()]) }}</p>
            @if ($secondsLeft !== null)
                <p x-data="{ left: {{ (int) $secondsLeft }}, timer: null, destroy() { clearInterval(this.timer) } }"
                   x-init="timer = setInterval(() => { left = Math.max(0, left - 1); if (left === 0) { clearInterval(timer); $wire.submit(true) } }, 1000)"
                   :class="left < 60 ? 'bg-danger text-white' : 'bg-brand text-ink'"
                   class="rounded-full px-4 py-1.5 font-receipt text-lg font-semibold tabular-nums" role="timer" aria-live="off">
                    ⏱ <span x-text="String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0')"></span>
                </p>
            @endif
        </div>
    </div>

    <h1 class="text-2xl font-black tracking-tight sm:text-3xl">{{ $quiz->title }}</h1>
    <p class="mt-1 text-slate">{{ __('Your answers are saved as you go. You can leave and come back before time runs out.') }}</p>
    @if ($saveError)<p class="mt-4 rounded-xl bg-[#fef3f2] p-3 font-bold text-danger" role="alert">{{ $saveError }}</p>@endif

    <form wire:submit="submit" class="mt-6 space-y-6">
        @foreach ($questions as $index => $question)
            @php($multi = $question->question_type === QuizQuestion::MULTIPLE_SELECT)
            <fieldset class="rounded-3xl border-2 border-line bg-white p-5 sm:p-6" wire:key="question-{{ $question->question_id }}">
                <legend class="sr-only">{{ __('Question :number', ['number' => $index + 1]) }}</legend>
                <p class="text-sm font-extrabold text-slate">{{ __('Question :number of :total', ['number' => $index + 1, 'total' => $questions->count()]) }} · {{ trans_choice(':count mark|:count marks', $question->points, ['count' => $question->points]) }}</p>
                <p class="mt-2 text-lg font-black leading-snug">{{ $question->question_text }}</p>
                @if ($multi)<p class="mt-1 text-sm font-bold text-ink-soft">{{ __('Choose all that apply.') }}</p>@endif
                <div class="mt-4 space-y-2">
                    @foreach ($question->answers as $answer)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-line px-4 py-3 font-semibold transition has-[:checked]:border-ink has-[:checked]:bg-brand-wash">
                            @if ($multi)
                                <input type="checkbox" wire:model.live="answers.{{ $question->question_id }}" value="{{ $answer->answer_id }}" class="size-5 accent-ink">
                            @else
                                <input type="radio" wire:model.live="answers.{{ $question->question_id }}" name="q-{{ $question->question_id }}" value="{{ $answer->answer_id }}" class="size-5 accent-ink">
                            @endif
                            <span>{{ $answer->answer_text }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach

        <button type="submit" class="btn-primary w-full text-lg" wire:loading.attr="disabled"
                @if ($answered < $questions->count()) wire:confirm="{{ __('Some questions are not answered. Submit anyway?') }}" @endif>
            {{ __('Submit answers') }}
        </button>
    </form>
</div>
