<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
}