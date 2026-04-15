<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizUserResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class QuizController extends BaseController
{
    /**
     * GET /api/v1/quizzes
     * Requires: auth:sanctum
     */
    public function index(Request $request): JsonResponse
    {
        $quizzes = Quiz::with('content:content_id,title,content_type')
            ->active()
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return $this->paginated($quizzes);
    }

    /**
     * GET /api/v1/quizzes/{id}
     * Requires: auth:sanctum
     */
    public function show(string $id): JsonResponse
    {
        $quiz = Quiz::with('content')->find($id);

        if (! $quiz) {
            return $this->error('Quiz not found.', 404);
        }

        return $this->success($quiz);
    }

    /**
     * POST /api/v1/quizzes
     * Requires: create_quiz
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:300',
            'content_id'  => 'nullable|uuid|exists:content,content_id',
            'description' => 'nullable|string',
            'total_marks' => 'required|integer|min:1',
            'is_active'   => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $quiz = Quiz::create($request->only(['title', 'content_id', 'description', 'total_marks', 'is_active']));

        return $this->success($quiz, 'Quiz created successfully.', 201);
    }

    /**
     * PUT /api/v1/quizzes/{id}
     * Requires: edit_quiz
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $quiz = Quiz::find($id);

        if (! $quiz) {
            return $this->error('Quiz not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'title'       => 'sometimes|string|max:300',
            'description' => 'nullable|string',
            'total_marks' => 'sometimes|integer|min:1',
            'is_active'   => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $quiz->fill($request->only(['title', 'description', 'total_marks', 'is_active']))->save();

        return $this->success($quiz, 'Quiz updated successfully.');
    }

    /**
     * DELETE /api/v1/quizzes/{id}
     * Requires: delete_quiz
     */
    public function destroy(string $id): JsonResponse
    {
        $quiz = Quiz::withCount('attempts')->find($id);

        if (! $quiz) {
            return $this->error('Quiz not found.', 404);
        }

        $quiz->delete();

        return $this->success(null, 'Quiz deleted successfully.');
    }

    // ─── Quiz Attempts ─────────────────────────────────────────────

    /**
     * POST /api/v1/quizzes/{id}/attempts
     * Student submits a quiz attempt. Requires: auth:sanctum
     */
    public function submitAttempt(Request $request, string $id): JsonResponse
    {
        $quiz = Quiz::find($id);

        if (! $quiz || ! $quiz->is_active) {
            return $this->error('Quiz not found or not available.', 404);
        }

        $validator = Validator::make($request->all(), [
            'score' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        if ($request->score > $quiz->total_marks) {
            return $this->error("Score cannot exceed the maximum marks of {$quiz->total_marks}.", 422);
        }

        $attempt = QuizAttempt::create([
            'user_id'   => $request->user()->user_id,
            'quiz_id'   => $quiz->quiz_id,
            'score'     => $request->score,
            'max_score' => $quiz->total_marks,
        ]);

        return $this->success([
            'attempt'          => $attempt,
            'score_percentage' => $attempt->score_percentage,
            'passed'           => $attempt->isPassed(),
        ], 'Quiz attempt recorded.', 201);
    }

    /**
     * GET /api/v1/quizzes/{id}/attempts
     * Admin/educator sees all attempts; students see only their own.
     * Requires: auth:sanctum
     */
    public function attempts(Request $request, string $id): JsonResponse
    {
        $quiz = Quiz::find($id);

        if (! $quiz) {
            return $this->error('Quiz not found.', 404);
        }

        $user    = $request->user();
        $isAdmin = $user->hasAnyRole(['admin', 'super_admin', 'educator']);

        $query = QuizAttempt::with('user:user_id,full_name,membership_id')
            ->where('quiz_id', $id)
            ->when(! $isAdmin, fn($q) => $q->where('user_id', $user->user_id))
            ->orderBy('score', 'desc');

        return $this->paginated($query->paginate($request->per_page ?? 20));
    }

    /**
     * GET /api/v1/quizzes/{id}/leaderboard
     * Top scorers per quiz. Requires: auth:sanctum
     */
    public function leaderboard(string $id): JsonResponse
    {
        $quiz = Quiz::find($id);

        if (! $quiz) {
            return $this->error('Quiz not found.', 404);
        }

        $leaderboard = QuizAttempt::with('user:user_id,full_name,membership_id')
            ->where('quiz_id', $id)
            ->orderBy('score', 'desc')
            ->orderBy('attempted_at', 'asc') // earlier attempt wins tie
            ->limit(20)
            ->get()
            ->map(fn($attempt, $index) => [
                'rank'             => $index + 1,
                'user'             => $attempt->user,
                'score'            => $attempt->score,
                'max_score'        => $attempt->max_score,
                'score_percentage' => $attempt->score_percentage,
                'attempted_at'     => $attempt->attempted_at,
            ]);

        return $this->success($leaderboard);
    }

    /**
     * GET /api/v1/quizzes/my-attempts
     * Authenticated user's own quiz history. Requires: auth:sanctum
     */
    public function myAttempts(Request $request): JsonResponse
    {
        $attempts = QuizAttempt::with('quiz:quiz_id,title,total_marks')
            ->where('user_id', $request->user()->user_id)
            ->orderBy('attempted_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return $this->paginated($attempts);
    }


    /**
     * GET /api/v1/quizzes/{id}/questions
     * Get quiz questions with answers (for taking quiz)
     */
    public function questions(string $id): JsonResponse
    {
        $quiz = Quiz::with(['questions.answers' => function ($q) {
            $q->orderBy('order_index');
        }])->find($id);

        if (!$quiz || !$quiz->is_active) {
            return $this->error('Quiz not found.', 404);
        }

        // Hide correct answers for now (will be checked during submission)
        $questions = $quiz->questions->map(function ($question) {
            return [
                'question_id' => $question->question_id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'points' => $question->points,
                'order_index' => $question->order_index,
                'answers' => $question->answers->map(function ($answer) {
                    return [
                        'answer_id' => $answer->answer_id,
                        'answer_text' => $answer->answer_text,
                    ];
                }),
            ];
        });

        return $this->success([
            'quiz' => $quiz,
            'questions' => $questions,
            'total_questions' => $questions->count(),
            'total_possible_points' => $quiz->questions->sum('points'),
        ]);
    }

    /**
     * POST /api/v1/quizzes/{id}/submit
     * Submit quiz answers and calculate points
     */
    public function submitQuiz(Request $request, string $id): JsonResponse
    {
        $quiz = Quiz::with('questions.answers')->find($id);

        if (!$quiz || !$quiz->is_active) {
            return $this->error('Quiz not available.', 404);
        }

        $validator = Validator::make($request->all(), [
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|uuid|exists:quiz_questions,question_id',
            'answers.*.answer_id' => 'nullable|uuid|exists:quiz_answers,answer_id',
            'answers.*.answer_text' => 'nullable|string',
            'time_spent_seconds' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $user = $request->user();
        $answers = collect($request->answers);
        $totalScore = 0;
        $totalPointsEarned = 0;
        $responses = [];

        DB::beginTransaction();

        try {
            // Check if user has previous attempts
            $previousBestAttempt = $user->quizAttempts()
                ->where('quiz_id', $id)
                ->where('is_best_attempt', true)
                ->first();

            // Calculate score for each answer
            foreach ($quiz->questions as $question) {
                $userAnswer = $answers->firstWhere('question_id', $question->question_id);

                if (!$userAnswer) {
                    continue;
                }

                $isCorrect = false;
                $pointsEarned = 0;
                $selectedAnswer = null;

                if ($question->question_type === 'multiple_choice' && isset($userAnswer['answer_id'])) {
                    $selectedAnswer = $question->answers->firstWhere('answer_id', $userAnswer['answer_id']);
                    if ($selectedAnswer && $selectedAnswer->is_correct) {
                        $isCorrect = true;
                        $pointsEarned = $question->points;
                        $totalScore += $question->points;
                    }
                } elseif ($question->question_type === 'true_false' && isset($userAnswer['answer_id'])) {
                    $selectedAnswer = $question->answers->firstWhere('answer_id', $userAnswer['answer_id']);
                    if ($selectedAnswer && $selectedAnswer->is_correct) {
                        $isCorrect = true;
                        $pointsEarned = $question->points;
                        $totalScore += $question->points;
                    }
                }

                $totalPointsEarned += $pointsEarned;

                $responses[] = [
                    'response_id' => (string) \Illuminate\Support\Str::uuid(),
                    'question_id' => $question->question_id,
                    'answer_id' => $userAnswer['answer_id'] ?? null,
                    'answer_text' => $userAnswer['answer_text'] ?? null,
                    'is_correct' => $isCorrect,
                    'points_earned' => $pointsEarned,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $maxScore = $quiz->questions->sum('points');
            $scorePercentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;
            $isPassed = $scorePercentage >= 50;

            // Create attempt record
            $attempt = QuizAttempt::create([
                'attempt_id' => (string) \Illuminate\Support\Str::uuid(),
                'user_id' => $user->user_id,
                'quiz_id' => $quiz->quiz_id,
                'score' => $totalScore,
                'max_score' => $maxScore,
                'total_points_earned' => $totalPointsEarned,
                'is_best_attempt' => false,
                'time_spent_seconds' => $request->time_spent_seconds,
                'attempted_at' => now(),
            ]);

            // Save responses
            foreach ($responses as $response) {
                $response['attempt_id'] = $attempt->attempt_id;
                QuizUserResponse::create($response);
            }

            // Handle points and best attempt
            if ($previousBestAttempt) {
                if ($totalPointsEarned > $previousBestAttempt->total_points_earned) {
                    // New attempt is better - update points
                    $pointsDifference = $totalPointsEarned - $previousBestAttempt->total_points_earned;
                    $user->addPoints($pointsDifference, "Improved quiz score: {$quiz->title}");

                    // Mark previous as not best
                    $previousBestAttempt->is_best_attempt = false;
                    $previousBestAttempt->save();

                    // Mark new as best
                    $attempt->is_best_attempt = true;
                    $attempt->save();
                } else {
                    // New attempt is worse or equal - no points change
                    $attempt->is_best_attempt = false;
                    $attempt->save();
                }
            } else {
                // First attempt - award all points
                $user->addPoints($totalPointsEarned, "Completed quiz: {$quiz->title}");
                $attempt->is_best_attempt = true;
                $attempt->save();
            }

            DB::commit();

            // Update user's total points recalculation
            $user->recalculatePoints();

            return $this->success([
                'attempt_id' => $attempt->attempt_id,
                'score' => $totalScore,
                'max_score' => $maxScore,
                'score_percentage' => $scorePercentage,
                'points_earned' => $totalPointsEarned,
                'total_user_points' => $user->total_points,
                'passed' => $isPassed,
                'is_best_attempt' => $attempt->is_best_attempt,
                'message' => $isPassed ? 'Congratulations! You passed the quiz!' : 'Keep practicing! You can retake the quiz to improve your score.',
            ], 'Quiz submitted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to submit quiz: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/quizzes/{id}/my-attempts
     * Get user's attempts for a specific quiz
     */
    public function myQuizAttempts(Request $request, string $id): JsonResponse
    {
        $quiz = Quiz::find($id);

        if (!$quiz) {
            return $this->error('Quiz not found.', 404);
        }

        $attempts = $request->user()
            ->quizAttempts()
            ->where('quiz_id', $id)
            ->orderBy('attempted_at', 'desc')
            ->get()
            ->map(function ($attempt) {
                return [
                    'attempt_id' => $attempt->attempt_id,
                    'score' => $attempt->score,
                    'max_score' => $attempt->max_score,
                    'score_percentage' => $attempt->score_percentage,
                    'points_earned' => $attempt->total_points_earned,
                    'is_best_attempt' => $attempt->is_best_attempt,
                    'attempted_at' => $attempt->attempted_at,
                    'passed' => $attempt->isPassed(),
                ];
            });

        return $this->success([
            'quiz' => $quiz,
            'attempts' => $attempts,
            'best_attempt' => $attempts->firstWhere('is_best_attempt', true),
            'total_attempts' => $attempts->count(),
        ]);
    }

    /**
     * GET /api/v1/quizzes/{id}/attempt/{attemptId}/results
     * Get detailed results for a specific attempt
     */
    public function attemptResults(Request $request, string $id, string $attemptId): JsonResponse
    {
        $attempt = QuizAttempt::with(['responses.question', 'responses.answer'])
            ->where('quiz_id', $id)
            ->where('attempt_id', $attemptId)
            ->where('user_id', $request->user()->user_id)
            ->first();

        if (!$attempt) {
            return $this->error('Attempt not found.', 404);
        }

        $results = $attempt->responses->map(function ($response) {
            return [
                'question_id' => $response->question_id,
                'question_text' => $response->question->question_text,
                'user_answer' => $response->answer?->answer_text ?? $response->answer_text,
                'correct_answer' => $response->question->getCorrectAnswer()?->answer_text,
                'is_correct' => $response->is_correct,
                'points_earned' => $response->points_earned,
                'max_points' => $response->question->points,
            ];
        });

        return $this->success([
            'attempt' => [
                'attempt_id' => $attempt->attempt_id,
                'score' => $attempt->score,
                'max_score' => $attempt->max_score,
                'score_percentage' => $attempt->score_percentage,
                'points_earned' => $attempt->total_points_earned,
                'attempted_at' => $attempt->attempted_at,
                'time_spent_seconds' => $attempt->time_spent_seconds,
            ],
            'results' => $results,
        ]);
    }

    /**
     * GET /api/v1/leaderboard
     * Global leaderboard by points
     */
    public function globalLeaderboard(Request $request): JsonResponse
    {
        $users = User::select('user_id', 'full_name', 'membership_id', 'total_points')
            ->where('total_points', '>', 0)
            ->orderBy('total_points', 'desc')
            ->paginate($request->per_page ?? 20);

        $leaderboard = $users->map(function ($user, $index) use ($users) {
            return [
                'rank' => $users->firstItem() + $index,
                'user_id' => $user->user_id,
                'full_name' => $user->full_name,
                'membership_id' => $user->membership_id,
                'total_points' => $user->total_points,
            ];
        });

        return $this->success([
            'leaderboard' => $leaderboard,
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/quizzes/{id}/questions
     * Add a question to a quiz. Requires: edit_quiz
     */
    public function addQuestion(Request $request, string $id): JsonResponse
    {
        $quiz = Quiz::find($id);

        if (!$quiz) {
            return $this->error('Quiz not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,true_false',
            'points' => 'required|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        // Get the highest order index
        $maxOrder = $quiz->questions()->max('order_index') ?? -1;

        $question = $quiz->questions()->create([
            'question_id' => (string) \Illuminate\Support\Str::uuid(),
            'question_text' => $request->question_text,
            'question_type' => $request->question_type,
            'points' => $request->points,
            'order_index' => $maxOrder + 1,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->success($question, 'Question added successfully.', 201);
    }

    /**
     * PUT /api/v1/quizzes/questions/{questionId}
     * Update a question. Requires: edit_quiz
     */
    public function updateQuestion(Request $request, string $questionId): JsonResponse
    {
        $question = \App\Models\QuizQuestion::find($questionId);

        if (!$question) {
            return $this->error('Question not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'question_text' => 'sometimes|string',
            'question_type' => 'sometimes|in:multiple_choice,true_false',
            'points' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $question->fill($request->only(['question_text', 'question_type', 'points', 'is_active']))->save();

        return $this->success($question, 'Question updated successfully.');
    }

    // app/Http/Controllers/API/V1/QuizController.php

    /**
     * GET /api/v1/quizzes/{id}/manage-questions
     * Admin endpoint to manage questions (includes correct answers)
     * Requires: edit_quiz permission
     */
    public function manageQuestions(string $id): JsonResponse
    {
        $quiz = Quiz::with(['questions.answers' => function ($q) {
            $q->orderBy('order_index');
        }])->find($id);

        if (!$quiz) {
            return $this->error('Quiz not found.', 404);
        }

        // Include all answer data including is_correct for admin management
        $questions = $quiz->questions->map(function ($question) {
            return [
                'question_id' => $question->question_id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'points' => $question->points,
                'order_index' => $question->order_index,
                'is_active' => $question->is_active,
                'answers' => $question->answers->map(function ($answer) {
                    return [
                        'answer_id' => $answer->answer_id,
                        'answer_text' => $answer->answer_text,
                        'is_correct' => $answer->is_correct, // Include this for admin
                        'order_index' => $answer->order_index,
                    ];
                }),
            ];
        });

        return $this->success([
            'quiz' => $quiz,
            'questions' => $questions,
            'total_questions' => $questions->count(),
            'total_possible_points' => $quiz->questions->sum('points'),
        ]);
    }

    /**
     * DELETE /api/v1/quizzes/questions/{questionId}
     * Delete a question. Requires: edit_quiz
     */
    public function deleteQuestion(string $questionId): JsonResponse
    {
        $question = \App\Models\QuizQuestion::with('answers')->find($questionId);

        if (!$question) {
            return $this->error('Question not found.', 404);
        }

        // Delete associated answers first
        foreach ($question->answers as $answer) {
            $answer->delete();
        }

        $question->delete();

        return $this->success(null, 'Question deleted successfully.');
    }

    /**
     * POST /api/v1/quizzes/questions/{questionId}/answers
     * Add an answer to a question. Requires: edit_quiz
     */
    public function addAnswer(Request $request, string $questionId): JsonResponse
    {
        $question = \App\Models\QuizQuestion::find($questionId);

        if (!$question) {
            return $this->error('Question not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'answer_text' => 'required|string',
            'is_correct' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        // Get the highest order index
        $maxOrder = $question->answers()->max('order_index') ?? -1;

        $answer = $question->answers()->create([
            'answer_id' => (string) \Illuminate\Support\Str::uuid(),
            'answer_text' => $request->answer_text,
            'is_correct' => $request->is_correct,
            'order_index' => $maxOrder + 1,
        ]);

        return $this->success($answer, 'Answer added successfully.', 201);
    }

    /**
     * PUT /api/v1/quizzes/answers/{answerId}
     * Update an answer. Requires: edit_quiz
     */
    public function updateAnswer(Request $request, string $answerId): JsonResponse
    {
        $answer = \App\Models\QuizAnswer::find($answerId);

        if (!$answer) {
            return $this->error('Answer not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'answer_text' => 'sometimes|string',
            'is_correct' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $answer->fill($request->only(['answer_text', 'is_correct']))->save();

        return $this->success($answer, 'Answer updated successfully.');
    }

    /**
     * DELETE /api/v1/quizzes/answers/{answerId}
     * Delete an answer. Requires: edit_quiz
     */
    public function deleteAnswer(string $answerId): JsonResponse
    {
        $answer = \App\Models\QuizAnswer::find($answerId);

        if (!$answer) {
            return $this->error('Answer not found.', 404);
        }

        $answer->delete();

        return $this->success(null, 'Answer deleted successfully.');
    }

    /**
     * POST /api/v1/quizzes/reorder-questions
     * Reorder questions. Requires: edit_quiz
     */
    public function reorderQuestions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'questions' => 'required|array',
            'questions.*.question_id' => 'required|uuid|exists:quiz_questions,question_id',
            'questions.*.order_index' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        foreach ($request->questions as $item) {
            \App\Models\QuizQuestion::where('question_id', $item['question_id'])
                ->update(['order_index' => $item['order_index']]);
        }

        return $this->success(null, 'Questions reordered successfully.');
    }

    /**
     * PATCH /api/v1/quizzes/{id}/toggle
     * Toggle quiz active status. Requires: edit_quiz
     */
    public function toggle(string $id): JsonResponse
    {
        $quiz = Quiz::find($id);

        if (!$quiz) {
            return $this->error('Quiz not found.', 404);
        }

        $quiz->is_active = !$quiz->is_active;
        $quiz->save();

        $state = $quiz->is_active ? 'activated' : 'deactivated';

        return $this->success($quiz, "Quiz {$state} successfully.");
    }
}
