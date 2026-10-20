<?php

namespace App\Livewire\Admin\Quizzes;

use App\Models\Content;
use App\Models\ContentCategory;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizQuestion;
use App\Support\QuizWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Create a quiz and manage its questions (GAM-REQ01, GAM-REQ02).
 */
#[Layout('components.layouts.app')]
class Builder extends Component
{
    public ?Quiz $quiz = null;

    // Settings
    public string $title = '';

    public string $description = '';

    public string $instructions = '';

    public ?string $category_id = null;

    public ?string $content_id = null;

    /** @var list<string> */
    public array $audience = [];

    public string $mode = Quiz::SCORED;

    public string $language = '';

    public int $pass_mark = 50;

    public ?int $time_limit_minutes = null;

    public ?int $max_attempts = null;

    public string $retained_result = Quiz::RETAIN_BEST;

    public string $show_answers = Quiz::ANSWERS_AFTER_SUBMIT;

    public bool $shuffle_questions = false;

    // Question being edited
    public ?string $editingQuestionId = null;

    public bool $editingQuestion = false;

    /**
     * @var array{question_text: string, explanation: string, question_type: string, points: int, answers: list<array{text: string, correct: bool}>}
     */
    public array $question = [];

    public ?string $statusMessage = null;

    public function mount(?Quiz $quiz = null): void
    {
        abort_unless(auth()->user()->hasControl('create_quiz') || auth()->user()->hasControl('edit_quiz'), 403);

        if ($quiz?->exists) {
            $this->quiz = $quiz;
            $this->fill($quiz->only(['title', 'mode', 'language', 'pass_mark', 'time_limit_minutes', 'max_attempts', 'retained_result', 'show_answers', 'shuffle_questions']));
            $this->description = (string) $quiz->description;
            $this->instructions = (string) $quiz->instructions;
            $this->category_id = $quiz->category_id ? (string) $quiz->category_id : null;
            $this->content_id = $quiz->content_id;
            $this->audience = $quiz->audience ?? [];
        }

        $this->resetQuestion();
    }

    public function saveSettings(): mixed
    {
        $this->guardEditable();

        $data = $this->validate([
            'title' => 'required|string|min:5|max:300',
            'description' => 'nullable|string|max:1000',
            'instructions' => 'nullable|string|max:2000',
            'category_id' => 'nullable|exists:content_categories,id',
            'content_id' => 'nullable|exists:content,content_id',
            'audience' => 'array',
            'audience.*' => 'in:'.implode(',', Content::AUDIENCES),
            'mode' => 'required|in:scored,practice',
            'language' => 'nullable|in:sw,en',
            'pass_mark' => 'required|integer|min:1|max:100',
            'time_limit_minutes' => 'nullable|integer|min:1|max:240',
            'max_attempts' => 'nullable|integer|min:1|max:20',
            'retained_result' => 'required|in:best,latest',
            'show_answers' => 'required|in:after_submit,after_close,never',
            'shuffle_questions' => 'boolean',
        ]);

        $data['audience'] = $data['audience'] ?: null;
        $data['description'] = $data['description'] ?: null;
        $data['instructions'] = $data['instructions'] ?: null;
        $data['category_id'] = $data['category_id'] ?: null;
        $data['content_id'] = $data['content_id'] ?: null;
        $data['language'] = $data['language'] ?: null;

        if ($this->quiz) {
            $this->quiz->update($data);
            $this->statusMessage = __('Settings saved.');

            return null;
        }

        $quiz = QuizWorkflow::create($data, auth()->user());
        session()->flash('status', __('Quiz created. Now add the questions.'));

        return $this->redirectRoute('admin.quizzes.edit', $quiz, navigate: true);
    }

    public function newQuestion(): void
    {
        $this->guardEditable();
        $this->resetQuestion();
        $this->editingQuestion = true;
    }

    public function editQuestion(string $id): void
    {
        $this->guardEditable();
        $question = $this->quiz->questions()->with('answers')->findOrFail($id);

        $this->editingQuestionId = $id;
        $this->editingQuestion = true;
        $this->question = [
            'question_text' => $question->question_text,
            'explanation' => (string) $question->explanation,
            'question_type' => $question->question_type === 'multiple_choice' ? QuizQuestion::SINGLE_CHOICE : $question->question_type,
            'points' => $question->points,
            'answers' => $question->answers->map(fn (QuizAnswer $answer) => ['text' => $answer->answer_text, 'correct' => $answer->is_correct])->all(),
        ];
    }

    public function updatedQuestionQuestionType(string $type): void
    {
        if ($type === QuizQuestion::TRUE_FALSE) {
            $this->question['answers'] = [['text' => __('True'), 'correct' => true], ['text' => __('False'), 'correct' => false]];
        } elseif (count($this->question['answers']) < 2) {
            $this->question['answers'] = [['text' => '', 'correct' => true], ['text' => '', 'correct' => false]];
        }
    }

    public function addOption(): void
    {
        if (count($this->question['answers']) < 8) {
            $this->question['answers'][] = ['text' => '', 'correct' => false];
        }
    }

    public function removeOption(int $index): void
    {
        unset($this->question['answers'][$index]);
        $this->question['answers'] = array_values($this->question['answers']);
    }

    /**
     * Single-answer questions: ticking one option unticks the others.
     */
    public function markCorrect(int $index): void
    {
        foreach ($this->question['answers'] as $i => $answer) {
            $this->question['answers'][$i]['correct'] = $this->question['question_type'] === QuizQuestion::MULTIPLE_SELECT
                ? ($i === $index ? ! $answer['correct'] : $answer['correct'])
                : $i === $index;
        }
    }

    public function saveQuestion(): void
    {
        $this->guardEditable();
        $this->validate([
            'question.question_text' => 'required|string|min:5|max:2000',
            'question.explanation' => 'nullable|string|max:2000',
            'question.question_type' => ['required', Rule::in(QuizQuestion::TYPES)],
            'question.points' => 'required|integer|min:1|max:20',
            'question.answers' => 'required|array|min:2|max:8',
            'question.answers.*.text' => 'required|string|max:500',
        ], [], ['question.question_text' => __('question'), 'question.answers.*.text' => __('answer option')]);

        $correct = collect($this->question['answers'])->where('correct', true)->count();
        $single = $this->question['question_type'] !== QuizQuestion::MULTIPLE_SELECT;

        if ($single ? $correct !== 1 : $correct < 1) {
            $this->addError('question.answers', $single ? __('Mark exactly one correct answer.') : __('Mark at least one correct answer.'));

            return;
        }

        DB::transaction(function () {
            $question = $this->editingQuestionId
                ? $this->quiz->questions()->findOrFail($this->editingQuestionId)
                : new QuizQuestion(['quiz_id' => $this->quiz->quiz_id, 'order_index' => (int) $this->quiz->questions()->max('order_index') + 1, 'is_active' => true]);

            $question->fill([
                'question_text' => trim($this->question['question_text']),
                'explanation' => trim($this->question['explanation']) ?: null,
                'question_type' => $this->question['question_type'],
                'points' => $this->question['points'],
            ])->save();

            $question->answers()->delete();
            foreach (array_values($this->question['answers']) as $position => $answer) {
                QuizAnswer::create(['question_id' => $question->question_id, 'answer_text' => trim($answer['text']), 'is_correct' => (bool) $answer['correct'], 'order_index' => $position]);
            }

            $this->quiz->update(['total_marks' => $this->quiz->questions()->where('is_active', true)->sum('points')]);
        });

        $this->statusMessage = __('Question saved.');
        $this->resetQuestion();
    }

    public function cancelQuestion(): void
    {
        $this->resetQuestion();
    }

    public function deleteQuestion(string $id): void
    {
        $this->guardEditable();
        $this->quiz->questions()->findOrFail($id)->delete();
        $this->quiz->update(['total_marks' => $this->quiz->questions()->where('is_active', true)->sum('points')]);
        $this->statusMessage = __('Question deleted.');
    }

    public function moveQuestion(string $id, int $direction): void
    {
        $this->guardEditable();
        $ids = $this->quiz->questions()->pluck('question_id')->all();
        $index = array_search($id, $ids, true);
        $target = $index + $direction;

        if ($index === false || ! isset($ids[$target])) {
            return;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        foreach ($ids as $position => $questionId) {
            QuizQuestion::whereKey($questionId)->update(['order_index' => $position]);
        }
    }

    private function resetQuestion(): void
    {
        $this->editingQuestionId = null;
        $this->editingQuestion = false;
        $this->question = [
            'question_text' => '',
            'explanation' => '',
            'question_type' => QuizQuestion::SINGLE_CHOICE,
            'points' => 1,
            'answers' => [['text' => '', 'correct' => true], ['text' => '', 'correct' => false], ['text' => '', 'correct' => false]],
        ];
        $this->resetErrorBag();
    }

    private function guardEditable(): void
    {
        abort_unless(auth()->user()->hasControl('create_quiz') || auth()->user()->hasControl('edit_quiz'), 403);
        abort_if($this->quiz && ! QuizWorkflow::canEdit($this->quiz), 409, __('This quiz cannot be changed now.'));
    }

    public function render(): mixed
    {
        return view('livewire.admin.quizzes.builder', [
            'questions' => $this->quiz?->questions()->with('answers')->get() ?? collect(),
            'categories' => ContentCategory::active()->get(),
            'lessons' => Content::whereNotNull('current_version')->orderBy('title')->get(['content_id', 'title', 'code']),
            'editable' => ! $this->quiz || QuizWorkflow::canEdit($this->quiz),
            'problems' => $this->quiz ? QuizWorkflow::readinessErrors($this->quiz) : [],
        ])->title($this->quiz ? __('Edit quiz') : __('New quiz'));
    }
}
