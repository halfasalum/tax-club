<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizQuestion;
use App\Support\QuizWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Quiz authoring over the API (GAM-REQ01/02). Same rules as the
 * Administration Portal: questions can only change while the quiz is a
 * draft and nobody has taken it; publishing goes through review.
 */
class QuizAuthoringController extends BaseController
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->settingsRules());

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $quiz = QuizWorkflow::create($validator->validated(), $request->user());

        return $this->success($quiz, 'Draft quiz created.', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $quiz = Quiz::find($id);

        if ($error = $this->editableError($quiz)) {
            return $error;
        }

        $validator = Validator::make($request->all(), $this->settingsRules(partial: true));

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $quiz->update($validator->validated());

        return $this->success($quiz->fresh(), 'Quiz updated.');
    }

    public function destroy(string $id): JsonResponse
    {
        $quiz = Quiz::find($id);

        if (! $quiz) {
            return $this->error('Quiz not found.', 404);
        }

        if ($quiz->published_at || QuizWorkflow::hasAttempts($quiz)) {
            return $this->error('Published quizzes cannot be deleted. Archive it instead.', 409);
        }

        $quiz->delete();

        return $this->success(null, 'Draft quiz deleted.');
    }

    public function submit(Request $request, string $id): JsonResponse
    {
        $quiz = Quiz::find($id);

        if (! $quiz) {
            return $this->error('Quiz not found.', 404);
        }

        try {
            QuizWorkflow::submit($quiz, $request->user());
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(null, 'Sent for review.');
    }

    public function toggle(): JsonResponse
    {
        return $this->error('Publishing now goes through review. Use the Administration Portal.', 410);
    }

    public function manageQuestions(string $id): JsonResponse
    {
        $quiz = Quiz::with('questions.answers')->find($id);

        if (! $quiz) {
            return $this->error('Quiz not found.', 404);
        }

        return $this->success([
            'quiz' => $quiz,
            'editable' => QuizWorkflow::canEdit($quiz),
            'problems' => QuizWorkflow::readinessErrors($quiz),
        ]);
    }

    public function addQuestion(Request $request, string $id): JsonResponse
    {
        $quiz = Quiz::find($id);

        if ($error = $this->editableError($quiz)) {
            return $error;
        }

        $validator = Validator::make($request->all(), $this->questionRules());

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $question = $quiz->questions()->create([
            ...$validator->validated(),
            'question_type' => $this->type($request->question_type),
            'order_index' => (int) $quiz->questions()->max('order_index') + 1,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->success($question, 'Question added.', 201);
    }

    public function updateQuestion(Request $request, string $questionId): JsonResponse
    {
        $question = QuizQuestion::find($questionId);

        if ($error = $this->editableError($question?->quiz)) {
            return $error;
        }

        $validator = Validator::make($request->all(), $this->questionRules(partial: true));

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $data = $validator->validated();
        if (isset($data['question_type'])) {
            $data['question_type'] = $this->type($data['question_type']);
        }

        $question->update($data);

        return $this->success($question->fresh('answers'), 'Question updated.');
    }

    public function deleteQuestion(string $questionId): JsonResponse
    {
        $question = QuizQuestion::find($questionId);

        if ($error = $this->editableError($question?->quiz)) {
            return $error;
        }

        $question->delete();

        return $this->success(null, 'Question deleted.');
    }

    public function addAnswer(Request $request, string $questionId): JsonResponse
    {
        $question = QuizQuestion::find($questionId);

        if ($error = $this->editableError($question?->quiz)) {
            return $error;
        }

        $validator = Validator::make($request->all(), ['answer_text' => 'required|string|max:500', 'is_correct' => 'required|boolean']);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $answer = $question->answers()->create([
            'answer_text' => $request->answer_text,
            'is_correct' => $request->boolean('is_correct'),
            'order_index' => (int) $question->answers()->max('order_index') + 1,
        ]);

        return $this->success($answer, 'Answer added.', 201);
    }

    public function updateAnswer(Request $request, string $answerId): JsonResponse
    {
        $answer = QuizAnswer::find($answerId);

        if ($error = $this->editableError($answer?->question?->quiz)) {
            return $error;
        }

        $validator = Validator::make($request->all(), ['answer_text' => 'sometimes|string|max:500', 'is_correct' => 'sometimes|boolean']);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $answer->update($validator->validated());

        return $this->success($answer, 'Answer updated.');
    }

    public function deleteAnswer(string $answerId): JsonResponse
    {
        $answer = QuizAnswer::find($answerId);

        if ($error = $this->editableError($answer?->question?->quiz)) {
            return $error;
        }

        $answer->delete();

        return $this->success(null, 'Answer deleted.');
    }

    public function reorderQuestions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'questions' => 'required|array',
            'questions.*.question_id' => 'required|exists:quiz_questions,question_id',
            'questions.*.order_index' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        foreach ($request->questions as $item) {
            $question = QuizQuestion::find($item['question_id']);
            if ($error = $this->editableError($question->quiz)) {
                return $error;
            }
            $question->update(['order_index' => $item['order_index']]);
        }

        return $this->success(null, 'Questions reordered.');
    }

    private function editableError(?Quiz $quiz): ?JsonResponse
    {
        if (! $quiz) {
            return $this->error('Not found.', 404);
        }

        return QuizWorkflow::canEdit($quiz) ? null : $this->error('This quiz is in review, approved or already taken, so it cannot be changed.', 409);
    }

    private function type(string $type): string
    {
        return $type === 'multiple_choice' ? QuizQuestion::SINGLE_CHOICE : $type;
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsRules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'title' => "{$required}|string|max:300",
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'content_id' => 'nullable|exists:content,content_id',
            'category_id' => 'nullable|exists:content_categories,id',
            'mode' => 'sometimes|in:scored,practice',
            'pass_mark' => 'sometimes|integer|min:1|max:100',
            'time_limit_minutes' => 'nullable|integer|min:1|max:240',
            'max_attempts' => 'nullable|integer|min:1|max:20',
            'retained_result' => 'sometimes|in:best,latest',
            'show_answers' => 'sometimes|in:after_submit,after_close,never',
            'shuffle_questions' => 'sometimes|boolean',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function questionRules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'question_text' => "{$required}|string|max:2000",
            'explanation' => 'nullable|string|max:2000',
            'question_type' => [$required, Rule::in([...QuizQuestion::TYPES, 'multiple_choice'])],
            'points' => "{$required}|integer|min:1|max:20",
        ];
    }
}
