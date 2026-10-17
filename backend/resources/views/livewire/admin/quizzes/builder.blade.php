@php
    use App\Models\QuizQuestion;

    $audiences = ['secondary' => __('Secondary school'), 'college' => __('College'), 'university' => __('University'), 'alumni' => __('Alumni')];
    $typeLabels = [QuizQuestion::SINGLE_CHOICE => __('One correct answer'), QuizQuestion::TRUE_FALSE => __('True or false'), QuizQuestion::MULTIPLE_SELECT => __('Several correct answers')];
    $errorText = 'mt-2 font-semibold text-danger';
@endphp
<div class="mx-auto max-w-5xl">
    <a href="{{ $quiz ? route('admin.quizzes.show', $quiz) : route('admin.quizzes.index') }}" class="link">← {{ $quiz ? __('Back to the quiz') : __('Quizzes') }}</a>
    <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ $quiz ? $quiz->title : __('New quiz') }}</h1>
    @if ($quiz)<p class="font-receipt text-slate">{{ $quiz->code }}</p>@endif

    @if ($statusMessage || session('status'))
        <p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage ?? session('status') }}</p>
    @endif

    @unless ($editable)
        <p class="mt-6 rounded-2xl bg-brand-wash p-4 font-bold">{{ __('This quiz is in review, approved or already taken by members, so it cannot be changed here. Open the quiz page to send it back to draft or make a new version.') }}</p>
    @endunless

    <form wire:submit="saveSettings" class="mt-8 space-y-5 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
        <fieldset @disabled(! $editable) class="space-y-5">
            <h2 class="text-xl font-black">{{ __('Settings') }}</h2>
            <div>
                <label for="title" class="block font-extrabold">{{ __('Title') }}</label>
                <input wire:model="title" id="title" type="text" @class(['field', 'field-error' => $errors->has('title')])>
                @error('title')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="description" class="block font-extrabold">{{ __('Short description') }}</label>
                    <textarea wire:model="description" id="description" rows="2" class="field"></textarea>
                </div>
                <div>
                    <label for="instructions" class="block font-extrabold">{{ __('Instructions for members') }}</label>
                    <textarea wire:model="instructions" id="instructions" rows="2" class="field"></textarea>
                </div>
                <div>
                    <label for="category_id" class="block font-extrabold">{{ __('Category') }}</label>
                    <select wire:model="category_id" id="category_id" class="field">
                        <option value="">{{ __('Choose a category') }}</option>
                        @foreach ($categories as $option)
                            <option value="{{ $option->id }}">{{ $option->name() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="content_id" class="block font-extrabold">{{ __('Linked lesson') }} <span class="font-semibold text-slate">({{ __('optional') }})</span></label>
                    <select wire:model="content_id" id="content_id" class="field">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($lessons as $lesson)
                            <option value="{{ $lesson->content_id }}">{{ $lesson->code }} · {{ $lesson->title }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <fieldset>
                <legend class="font-extrabold">{{ __('Members who can take it') }} <span class="font-semibold text-slate">({{ __('none ticked means everyone') }})</span></legend>
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach ($audiences as $value => $label)
                        <label class="flex items-center gap-2 rounded-xl border-2 border-line px-3 py-2 font-bold"><input type="checkbox" wire:model="audience" value="{{ $value }}" class="size-4 accent-ink"> {{ $label }}</label>
                    @endforeach
                </div>
            </fieldset>

            <h3 class="pt-2 text-lg font-black">{{ __('Rules') }}</h3>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="mode" class="block font-extrabold">{{ __('Kind of quiz') }}</label>
                    <select wire:model="mode" id="mode" class="field">
                        <option value="scored">{{ __('Scored (earns points)') }}</option>
                        <option value="practice">{{ __('Practice (no points)') }}</option>
                    </select>
                </div>
                <div>
                    <label for="pass_mark" class="block font-extrabold">{{ __('Pass mark (%)') }}</label>
                    <input wire:model="pass_mark" id="pass_mark" type="number" min="1" max="100" @class(['field', 'field-error' => $errors->has('pass_mark')])>
                    @error('pass_mark')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="time_limit_minutes" class="block font-extrabold">{{ __('Time limit (minutes)') }}</label>
                    <input wire:model="time_limit_minutes" id="time_limit_minutes" type="number" min="1" max="240" placeholder="{{ __('No limit') }}" class="field">
                </div>
                <div>
                    <label for="max_attempts" class="block font-extrabold">{{ __('Attempts allowed') }}</label>
                    <input wire:model="max_attempts" id="max_attempts" type="number" min="1" max="20" placeholder="{{ __('Unlimited') }}" class="field">
                </div>
                <div>
                    <label for="retained_result" class="block font-extrabold">{{ __('Result that counts') }}</label>
                    <select wire:model="retained_result" id="retained_result" class="field">
                        <option value="best">{{ __('Best attempt') }}</option>
                        <option value="latest">{{ __('Latest attempt') }}</option>
                    </select>
                </div>
                <div>
                    <label for="show_answers" class="block font-extrabold">{{ __('Show correct answers') }}</label>
                    <select wire:model="show_answers" id="show_answers" class="field">
                        <option value="after_submit">{{ __('Right after submitting') }}</option>
                        <option value="after_close">{{ __('After the quiz closes') }}</option>
                        <option value="never">{{ __('Never') }}</option>
                    </select>
                </div>
            </div>
            <label class="flex items-center gap-2 font-bold"><input type="checkbox" wire:model="shuffle_questions" class="size-4 accent-ink"> {{ __('Show questions in a different order for each member') }}</label>
            <button type="submit" class="btn-primary">{{ $quiz ? __('Save settings') : __('Create quiz and add questions') }}</button>
        </fieldset>
    </form>

    @if ($quiz)
        <section class="mt-8 rounded-3xl border-2 border-line bg-white p-6 sm:p-8" aria-labelledby="questions-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="questions-heading" class="text-xl font-black">{{ trans_choice('{0} Questions|{1} :count question · :marks marks|[2,*] :count questions · :marks marks', $questions->count(), ['count' => $questions->count(), 'marks' => $questions->sum('points')]) }}</h2>
                @if ($editable && ! $editingQuestion)
                    <button type="button" wire:click="newQuestion" class="btn-brand py-2">{{ __('Add a question') }}</button>
                @endif
            </div>

            @if ($problems && $questions->isNotEmpty())
                <ul class="mt-4 list-disc space-y-1 rounded-xl bg-[#fef3f2] p-4 pl-8 font-semibold text-danger">
                    @foreach ($problems as $problem)<li>{{ $problem }}</li>@endforeach
                </ul>
            @endif

            @if ($editingQuestion)
                <form wire:submit="saveQuestion" class="mt-6 space-y-4 rounded-2xl border-4 border-ink p-5">
                    <h3 class="text-lg font-black">{{ $editingQuestionId ? __('Edit question') : __('New question') }}</h3>
                    <div>
                        <label for="question_text" class="block font-extrabold">{{ __('Question') }}</label>
                        <textarea wire:model="question.question_text" id="question_text" rows="2" @class(['field', 'field-error' => $errors->has('question.question_text')])></textarea>
                        @error('question.question_text')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
                        <div>
                            <label for="question_type" class="block font-extrabold">{{ __('Type') }}</label>
                            <select wire:model.live="question.question_type" id="question_type" class="field">
                                @foreach ($typeLabels as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="points" class="block font-extrabold">{{ __('Marks') }}</label>
                            <input wire:model="question.points" id="points" type="number" min="1" max="20" class="field">
                        </div>
                    </div>
                    <fieldset>
                        <legend class="font-extrabold">{{ __('Answer options') }} <span class="font-semibold text-slate">({{ $question['question_type'] === QuizQuestion::MULTIPLE_SELECT ? __('tick every correct answer') : __('tick the correct answer') }})</span></legend>
                        <ul class="mt-2 space-y-2">
                            @foreach ($question['answers'] as $index => $answer)
                                <li class="flex items-center gap-3" wire:key="option-{{ $index }}">
                                    <button type="button" wire:click="markCorrect({{ $index }})" role="checkbox" aria-checked="{{ $answer['correct'] ? 'true' : 'false' }}" aria-label="{{ __('Correct answer') }}"
                                            @class(['grid size-9 shrink-0 place-items-center rounded-full border-2 font-black', 'border-savanna bg-savanna text-white' => $answer['correct'], 'border-line bg-white text-transparent' => ! $answer['correct']])>✓</button>
                                    <input wire:model="question.answers.{{ $index }}.text" type="text" aria-label="{{ __('Option :number', ['number' => $index + 1]) }}" @readonly($question['question_type'] === QuizQuestion::TRUE_FALSE) @class(['field mt-0 flex-1', 'field-error' => $errors->has('question.answers.'.$index.'.text')])>
                                    @if ($question['question_type'] !== QuizQuestion::TRUE_FALSE && count($question['answers']) > 2)
                                        <button type="button" wire:click="removeOption({{ $index }})" class="link text-sm">{{ __('Remove') }}</button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @error('question.answers')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                        @if ($question['question_type'] !== QuizQuestion::TRUE_FALSE && count($question['answers']) < 8)
                            <button type="button" wire:click="addOption" class="link mt-3">{{ __('Add an option') }}</button>
                        @endif
                    </fieldset>
                    <div>
                        <label for="explanation" class="block font-extrabold">{{ __('Explanation shown after answering') }} <span class="font-semibold text-slate">({{ __('optional') }})</span></label>
                        <textarea wire:model="question.explanation" id="explanation" rows="2" class="field"></textarea>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="btn-primary">{{ __('Save question') }}</button>
                        <button type="button" wire:click="cancelQuestion" class="btn-ghost">{{ __('Cancel') }}</button>
                    </div>
                </form>
            @endif

            <ol class="mt-6 space-y-3">
                @forelse ($questions as $index => $item)
                    <li class="rounded-2xl border-2 border-line p-4" wire:key="q-{{ $item->question_id }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <p class="min-w-0 flex-1 font-black"><span class="mr-2 rounded-full bg-brand px-2">{{ $index + 1 }}</span>{{ $item->question_text }}</p>
                            <span class="text-sm font-bold text-slate">{{ $typeLabels[$item->question_type] ?? $item->question_type }} · {{ trans_choice(':count mark|:count marks', $item->points, ['count' => $item->points]) }}</span>
                        </div>
                        <ul class="mt-2 space-y-1 pl-8">
                            @foreach ($item->answers as $answer)
                                <li @class(['font-bold text-savanna' => $answer->is_correct, 'text-slate' => ! $answer->is_correct])>{{ $answer->is_correct ? '✓' : '·' }} {{ $answer->answer_text }}</li>
                            @endforeach
                        </ul>
                        @if ($item->explanation)<p class="mt-2 pl-8 text-sm italic text-slate">{{ $item->explanation }}</p>@endif
                        @if ($editable)
                            <div class="mt-3 flex flex-wrap gap-3 pl-8 text-sm">
                                <button type="button" wire:click="editQuestion('{{ $item->question_id }}')" class="link">{{ __('Edit') }}</button>
                                <button type="button" wire:click="moveQuestion('{{ $item->question_id }}', -1)" class="link" @disabled($loop->first)>{{ __('Move up') }}</button>
                                <button type="button" wire:click="moveQuestion('{{ $item->question_id }}', 1)" class="link" @disabled($loop->last)>{{ __('Move down') }}</button>
                                <button type="button" wire:click="deleteQuestion('{{ $item->question_id }}')" wire:confirm="{{ __('Delete this question?') }}" class="link text-danger">{{ __('Delete') }}</button>
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="rounded-2xl bg-mist p-6 text-center font-bold text-slate">{{ __('No questions yet.') }}</li>
                @endforelse
            </ol>
        </section>
    @endif
</div>
