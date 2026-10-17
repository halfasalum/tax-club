@php
    use App\Models\Quiz;
    use App\Models\QuizQuestion;

    $typeLabels = [QuizQuestion::SINGLE_CHOICE => __('One correct answer'), QuizQuestion::TRUE_FALSE => __('True or false'), QuizQuestion::MULTIPLE_SELECT => __('Several correct answers')];
    $answerRules = ['after_submit' => __('right after submitting'), 'after_close' => __('after the quiz closes'), 'never' => __('never')];
    $actions = [
        'created' => __('Created'), 'submitted' => __('Sent for review'), 'approved' => __('Approved'), 'returned' => __('Returned for changes'),
        'rejected' => __('Rejected'), 'published' => __('Published'), 'scheduled' => __('Scheduled'), 'closed' => __('Closed'),
        'archived' => __('Archived'), 'back_to_draft' => __('Back to draft'), 'duplicated' => __('Created as a copy'),
    ];
@endphp
<div>
    <a wire:navigate href="{{ route('admin.quizzes.index') }}" class="link">← {{ __('Quizzes') }}</a>
    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="font-receipt text-slate">{{ $quiz->code }}</p>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ $quiz->title }}</h1>
            <p class="mt-2 flex flex-wrap items-center gap-2">
                <x-editorial-status :status="$quiz->editorial_status" />
                <x-editorial-status :status="$quiz->displayStatus()" />
                @if ($quiz->opens_at)<span class="text-slate">{{ __('from :date', ['date' => $quiz->opens_at->translatedFormat('j M Y H:i')]) }}</span>@endif
                @if ($quiz->closes_at)<span class="text-slate">{{ __('until :date', ['date' => $quiz->closes_at->translatedFormat('j M Y H:i')]) }}</span>@endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($can['edit'] && $editable)
                <a wire:navigate href="{{ route('admin.quizzes.edit', $quiz) }}" class="btn-ghost">{{ __('Edit questions') }}</a>
            @endif
            @if ($can['edit'] && ! $editable && ! $hasAttempts && $quiz->editorial_status === Quiz::APPROVED)
                <button type="button" wire:click="reopen" wire:confirm="{{ __('Take this quiz back to draft? It will need review again.') }}" class="btn-ghost">{{ __('Make changes') }}</button>
            @endif
            @if ($can['create'] && $hasAttempts)
                <button type="button" wire:click="duplicate" class="btn-ghost">{{ __('Make a new version') }}</button>
            @endif
        </div>
    </div>

    @if ($statusMessage)
        <p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>
    @endif
    @error('action')<p class="mt-6 rounded-2xl bg-[#fef3f2] px-5 py-4 font-bold text-danger" role="alert">{{ $message }}</p>@enderror

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ __('Rules') }}</h2>
                <dl class="mt-3 grid gap-x-6 gap-y-2 sm:grid-cols-2">
                    @foreach ([
                        [__('Kind of quiz'), $quiz->isPractice() ? __('Practice (no points)') : __('Scored (earns points)')],
                        [__('Pass mark'), $quiz->pass_mark.'%'],
                        [__('Time limit'), $quiz->time_limit_minutes ? __(':minutes minutes', ['minutes' => $quiz->time_limit_minutes]) : __('No limit')],
                        [__('Attempts allowed'), $quiz->max_attempts ?? __('Unlimited')],
                        [__('Result that counts'), $quiz->retained_result === 'latest' ? __('Latest attempt') : __('Best attempt')],
                        [__('Correct answers shown'), $answerRules[$quiz->show_answers] ?? ''],
                        [__('Category'), $quiz->category?->name() ?? '—'],
                        [__('Linked lesson'), $quiz->content?->title ?? '—'],
                    ] as [$label, $value])
                        <div><dt class="inline font-extrabold">{{ $label }}:</dt> <dd class="inline">{{ $value }}</dd></div>
                    @endforeach
                </dl>
                @if ($quiz->instructions)<p class="mt-4 rounded-xl bg-mist p-3">{{ $quiz->instructions }}</p>@endif
            </section>

            @if ($problems)
                <ul class="list-disc space-y-1 rounded-2xl bg-[#fef3f2] p-5 pl-10 font-semibold text-danger">
                    @foreach ($problems as $problem)<li>{{ $problem }}</li>@endforeach
                </ul>
            @endif

            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ trans_choice('{0} Questions|{1} :count question · :marks marks|[2,*] :count questions · :marks marks', $quiz->questions->count(), ['count' => $quiz->questions->count(), 'marks' => $quiz->questions->sum('points')]) }}</h2>
                <ol class="mt-4 space-y-4">
                    @foreach ($quiz->questions as $index => $question)
                        <li>
                            <p class="font-black">{{ $index + 1 }}. {{ $question->question_text }} <span class="text-sm font-bold text-slate">({{ $typeLabels[$question->question_type] ?? '' }}, {{ trans_choice(':count mark|:count marks', $question->points, ['count' => $question->points]) }})</span></p>
                            <ul class="mt-1 space-y-0.5 pl-6">
                                @foreach ($question->answers as $answer)
                                    <li @class(['font-bold text-savanna' => $answer->is_correct, 'text-slate' => ! $answer->is_correct])>{{ $answer->is_correct ? '✓' : '·' }} {{ $answer->answer_text }}</li>
                                @endforeach
                            </ul>
                            @if ($question->explanation)<p class="mt-1 pl-6 text-sm italic text-slate">{{ $question->explanation }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </section>

            @if ($results)
                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Results') }}</h2>
                    <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ([[__('Attempts'), $results['attempts']], [__('Members'), $results['members']], [__('Average score'), $results['average'].'%'], [__('Members who passed'), $results['passed']]] as [$label, $value])
                            <div class="rounded-2xl bg-mist p-4"><dt class="text-sm font-extrabold text-slate">{{ $label }}</dt><dd class="text-2xl font-black tabular-nums">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                    @if ($results['hardest']->isNotEmpty())
                        <h3 class="mt-5 font-black">{{ __('Most often answered wrongly') }}</h3>
                        <ul class="mt-2 space-y-1">
                            @foreach ($results['hardest'] as $row)
                                <li>{{ \Illuminate\Support\Str::limit($row->question_text, 80) }} <span class="font-bold text-slate">{{ __(':percent% correct', ['percent' => (int) round($row->correct / max(1, $row->answered) * 100)]) }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            @if (in_array($quiz->editorial_status, Quiz::EDITABLE, true) && $can['edit'])
                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Ready for review?') }}</h2>
                    @if ($quiz->review_note)<p class="mt-2 rounded-xl bg-[#fef3f2] p-3"><span class="font-extrabold">{{ __('Reviewer notes') }}:</span> {{ $quiz->review_note }}</p>@endif
                    <button type="button" wire:click="submit" class="btn-primary mt-4 w-full" @disabled($problems)>{{ __('Send for review') }}</button>
                </section>
            @endif

            @if ($quiz->editorial_status === Quiz::IN_REVIEW && $can['review'])
                <section class="rounded-3xl border-4 border-ink bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Review') }}</h2>
                    @if ($ownSubmission && \App\Support\ContentWorkflow::fourEyes())
                        <p class="mt-3 rounded-xl bg-brand-wash p-3 font-bold">{{ __('You sent this for review, so another reviewer must decide.') }}</p>
                    @else
                        <p class="mt-1 text-sm text-slate">{{ __('Check every question, the correct answers and the explanations.') }}</p>
                        <label for="reviewNote" class="mt-4 block font-bold">{{ __('Notes for the author') }}</label>
                        <textarea wire:model="reviewNote" id="reviewNote" rows="3" class="field"></textarea>
                        <div class="mt-3 grid gap-2">
                            <button type="button" wire:click="approve" class="btn-primary">{{ __('Approve') }}</button>
                            <button type="button" wire:click="returnForChanges" class="btn-ghost">{{ __('Return for changes') }}</button>
                            <button type="button" wire:click="reject" wire:confirm="{{ __('Reject this quiz?') }}" class="btn border-2 border-line hover:border-danger hover:text-danger">{{ __('Reject') }}</button>
                        </div>
                    @endif
                </section>
            @endif

            @if ($quiz->editorial_status === Quiz::APPROVED && $can['publish'] && ! $quiz->isOpen() && $quiz->publication_status !== Quiz::ARCHIVED)
                <section class="rounded-3xl border-4 border-savanna bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Open to members') }}</h2>
                    <form wire:submit="publish" class="mt-4 space-y-3">
                        <div>
                            <label for="opensAt" class="block font-bold">{{ __('Opens') }} <span class="font-semibold text-slate">({{ __('empty = now') }})</span></label>
                            <input wire:model="opensAt" id="opensAt" type="datetime-local" class="field">
                        </div>
                        <div>
                            <label for="closesAt" class="block font-bold">{{ __('Closes') }} <span class="font-semibold text-slate">({{ __('optional') }})</span></label>
                            <input wire:model="closesAt" id="closesAt" type="datetime-local" class="field">
                        </div>
                        <button type="submit" class="btn-brand w-full">{{ __('Publish') }}</button>
                    </form>
                </section>
            @endif

            @if ($can['publish'] && in_array($quiz->publication_status, [Quiz::PUBLISHED, Quiz::SCHEDULED, Quiz::CLOSED], true))
                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Close or archive') }}</h2>
                    <label for="reason" class="mt-3 block font-bold">{{ __('Reason') }}</label>
                    <textarea wire:model="reason" id="reason" rows="2" class="field"></textarea>
                    <div class="mt-3 grid gap-2">
                        @if ($quiz->isOpen())<button type="button" wire:click="close" class="btn-ghost">{{ __('Close now') }}</button>@endif
                        <button type="button" wire:click="archive" wire:confirm="{{ __('Archive this quiz?') }}" class="btn border-2 border-line hover:border-danger hover:text-danger">{{ __('Archive') }}</button>
                    </div>
                </section>
            @endif

            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ __('History') }}</h2>
                <ol class="mt-3 space-y-3 border-l-4 border-brand pl-4">
                    @foreach ($events as $event)
                        <li>
                            <p class="font-extrabold">{{ $actions[$event->action] ?? $event->action }}</p>
                            <p class="text-sm text-slate">{{ $event->user?->full_name ?? __('System') }} · {{ $event->created_at->translatedFormat('j M Y H:i') }}</p>
                            @if ($event->note)<p class="text-sm">{{ $event->note }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </section>
        </aside>
    </div>
</div>
