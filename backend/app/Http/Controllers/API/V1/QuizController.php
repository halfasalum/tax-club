<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Support\Leaderboard;
use App\Support\QuizEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Taking quizzes from the mobile app (GAM-REQ05-07, GAM-REQ10).
 *
 *   GET  quizzes                         open quizzes with the member's status
 *   GET  quizzes/{id}                    rules and availability
 *   POST quizzes/{id}/start              start or resume; questions without answers
 *   PUT  quizzes/attempts/{id}/answers   save one answer (autosave)
 *   POST quizzes/attempts/{id}/submit    score and close
 *   GET  quizzes/{id}/attempt/{a}/results
 */
class QuizController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $member = $request->user();
        $results = QuizAttempt::where('user_id', $member->user_id)->where('is_best_attempt', true)->get()->keyBy('quiz_id');

        $quizzes = Quiz::availableTo($member)->with('category')
            ->withCount(['questions' => fn ($query) => $query->where('is_active', true)])
            ->latest('published_at')
            ->get()
            ->map(fn (Quiz $quiz) => $this->summary($quiz) + [
                'result' => ($result = $results->get($quiz->quiz_id)) ? ['percent' => $result->percent, 'passed' => $result->passed] : null,
            ]);

        return $this->success($quizzes);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $quiz = $this->findForMember($request->user(), $id);

        if (! $quiz) {
            return $this->error('Quiz not available.', 404);
        }

        $status = QuizEngine::availability($request->user(), $quiz);

        return $this->success($this->summary($quiz->loadCount(['questions' => fn ($query) => $query->where('is_active', true)])) + [
            'instructions' => $quiz->instructions,
            'can_start' => $status['can_start'],
            'reason' => $status['reason'],
            'attempts_used' => $status['attempts_used'],
            'attempts_left' => $status['attempts_left'],
            'attempt_in_progress' => $status['in_progress']?->attempt_id,
        ]);
    }

    public function start(Request $request, string $id): JsonResponse
    {
        $quiz = $this->findForMember($request->user(), $id);

        if (! $quiz) {
            return $this->error('Quiz not available.', 404);
        }

        try {
            $attempt = QuizEngine::start($request->user(), $quiz);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success($this->attemptPayload($attempt), 'Attempt started.');
    }

    /**
     * PUT quizzes/attempts/{attemptId}/answers  { question_id, answer_ids: [] }
     */
    public function saveAnswer(Request $request, string $attemptId): JsonResponse
    {
        $attempt = QuizAttempt::where('user_id', $request->user()->user_id)->find($attemptId);

        if (! $attempt) {
            return $this->error('Attempt not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'question_id' => 'required|string',
            'answer_ids' => 'present|array',
            'answer_ids.*' => 'string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        try {
            QuizEngine::saveAnswer($attempt, $request->question_id, $request->answer_ids);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 409);
        }

        return $this->success(['seconds_left' => $this->secondsLeft($attempt)], 'Saved.');
    }

    public function submitAttempt(Request $request, string $attemptId): JsonResponse
    {
        $attempt = QuizAttempt::where('user_id', $request->user()->user_id)->find($attemptId);

        if (! $attempt) {
            return $this->error('Attempt not found.', 404);
        }

        return $this->success($this->resultPayload(QuizEngine::submit($attempt)), 'Quiz submitted.');
    }

    /**
     * POST quizzes/{id}/submit — one-step submission kept for older app
     * versions. Only for quizzes without a time limit.
     */
    public function submitQuiz(Request $request, string $id): JsonResponse
    {
        $quiz = $this->findForMember($request->user(), $id);

        if (! $quiz) {
            return $this->error('Quiz not available.', 404);
        }

        if ($quiz->time_limit_minutes) {
            return $this->error('This quiz has a time limit. Start it with POST /quizzes/{id}/start.', 409);
        }

        $validator = Validator::make($request->all(), [
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|string',
            'answers.*.answer_id' => 'nullable|string',
            'answers.*.answer_ids' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        try {
            $attempt = QuizEngine::start($request->user(), $quiz);
            foreach ($request->answers as $answer) {
                if (in_array($answer['question_id'], $attempt->question_order ?? [], true)) {
                    QuizEngine::saveAnswer($attempt, $answer['question_id'], $answer['answer_ids'] ?? array_filter([$answer['answer_id'] ?? null]));
                }
            }
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success($this->resultPayload(QuizEngine::submit($attempt)), 'Quiz submitted.');
    }

    /**
     * GET quizzes/{id}/questions — replaced by start.
     */
    public function questions(): JsonResponse
    {
        return $this->error('Start the quiz with POST /quizzes/{id}/start to get its questions.', 410);
    }

    public function attemptResults(Request $request, string $id, string $attemptId): JsonResponse
    {
        $attempt = QuizAttempt::where('user_id', $request->user()->user_id)->where('quiz_id', $id)->find($attemptId);

        if (! $attempt || $attempt->status !== QuizAttempt::SUBMITTED) {
            return $this->error('Result not found.', 404);
        }

        return $this->success($this->resultPayload($attempt));
    }

    public function myAttempts(Request $request): JsonResponse
    {
        $attempts = QuizAttempt::with('quiz:quiz_id,title,code')
            ->where('user_id', $request->user()->user_id)
            ->where('status', QuizAttempt::SUBMITTED)
            ->latest('submitted_at')
            ->paginate(20);

        return $this->paginated($attempts);
    }

    public function myQuizAttempts(Request $request, string $id): JsonResponse
    {
        return $this->success(QuizAttempt::where('user_id', $request->user()->user_id)->where('quiz_id', $id)
            ->where('status', QuizAttempt::SUBMITTED)->orderByDesc('attempt_number')
            ->get(['attempt_id', 'attempt_number', 'score', 'max_score', 'percent', 'passed', 'is_best_attempt', 'submitted_at']));
    }

    /**
     * GET quizzes/{id}/leaderboard — best result per member.
     */
    public function leaderboard(Request $request, string $id): JsonResponse
    {
        if (! Leaderboard::enabled()) {
            return $this->success(['leaderboard' => []]);
        }

        $rows = DB::table('quiz_attempts')
            ->join('users', 'users.user_id', '=', 'quiz_attempts.user_id')
            ->where('quiz_attempts.quiz_id', $id)
            ->where('quiz_attempts.is_best_attempt', true)
            ->where('users.account_status', User::STATUS_ACTIVE)
            ->orderByDesc('quiz_attempts.percent')
            ->orderBy('quiz_attempts.time_spent_seconds')
            ->limit(Leaderboard::SIZE)
            ->get(['users.user_id', 'users.full_name', 'quiz_attempts.percent', 'quiz_attempts.time_spent_seconds']);

        return $this->success(['leaderboard' => $rows->values()->map(fn (object $row, int $index) => [
            'rank' => $index + 1,
            'display_name' => (new User(['full_name' => $row->full_name]))->publicName(),
            'percent' => (float) $row->percent,
            'time_spent_seconds' => $row->time_spent_seconds,
            'is_me' => $row->user_id === $request->user()->user_id,
        ])]);
    }

    /**
     * GET leaderboard?period=all|month|week&scope=national|region|institution
     */
    public function globalLeaderboard(Request $request): JsonResponse
    {
        if (! Leaderboard::enabled()) {
            return $this->success(['enabled' => false, 'leaderboard' => [], 'me' => null]);
        }

        $ranking = Leaderboard::rank($request->user()->load('institution'), (string) $request->query('period', 'all'), (string) $request->query('scope', 'national'));

        return $this->success([
            'enabled' => true,
            'leaderboard' => $ranking['entries']->map(fn (array $entry) => collect($entry)->except('user_id')->all())->values(),
            'me' => $ranking['me'] ? collect($ranking['me'])->except('user_id')->all() : null,
        ]);
    }

    private function findForMember(User $member, string $id): ?Quiz
    {
        $quiz = Quiz::find($id);

        if (! $quiz) {
            return null;
        }

        return $quiz->isAvailableTo($member) || $quiz->attempts()->where('user_id', $member->user_id)->exists() ? $quiz : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Quiz $quiz): array
    {
        return [
            'quiz_id' => $quiz->quiz_id,
            'code' => $quiz->code,
            'title' => $quiz->title,
            'description' => $quiz->description,
            'category' => $quiz->category?->name(),
            'content_id' => $quiz->content_id,
            'mode' => $quiz->mode,
            'pass_mark' => $quiz->pass_mark,
            'time_limit_minutes' => $quiz->time_limit_minutes,
            'max_attempts' => $quiz->max_attempts,
            'retained_result' => $quiz->retained_result,
            'questions_count' => $quiz->questions_count ?? null,
            'closes_at' => $quiz->closes_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attemptPayload(QuizAttempt $attempt): array
    {
        $questions = QuizQuestion::with('answers')->whereIn('question_id', $attempt->question_order ?? [])->get()->keyBy('question_id');
        $saved = $attempt->responses()->get()->keyBy('question_id');

        return [
            'attempt_id' => $attempt->attempt_id,
            'attempt_number' => $attempt->attempt_number,
            'started_at' => $attempt->started_at->toIso8601String(),
            'expires_at' => $attempt->expires_at?->toIso8601String(),
            'seconds_left' => $this->secondsLeft($attempt),
            'questions' => collect($attempt->question_order ?? [])->map(fn ($questionId) => $questions->get($questionId))->filter()->values()->map(fn (QuizQuestion $question) => [
                'question_id' => $question->question_id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'points' => $question->points,
                'answers' => $question->answers->map(fn ($answer) => ['answer_id' => $answer->answer_id, 'answer_text' => $answer->answer_text])->values(),
                'selected_answer_ids' => $saved->get($question->question_id)?->selected_answer_ids ?? [],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resultPayload(QuizAttempt $attempt): array
    {
        $showAnswers = QuizEngine::canSeeAnswers($attempt);
        $responses = $attempt->responses()->get()->keyBy('question_id');

        return [
            'attempt_id' => $attempt->attempt_id,
            'score' => $attempt->score,
            'max_score' => $attempt->max_score,
            'percent' => (float) $attempt->percent,
            'passed' => $attempt->passed,
            'auto_submitted' => $attempt->auto_submitted,
            'counts_as_result' => $attempt->is_best_attempt,
            'points_awarded' => (int) DB::table('points_ledger')->where('source_type', 'quiz_attempt')->where('source_id', $attempt->attempt_id)->sum('points'),
            'answers_visible' => $showAnswers,
            'questions' => $showAnswers ? QuizQuestion::with('answers')->whereIn('question_id', $attempt->question_order ?? [])->get()->map(fn (QuizQuestion $question) => [
                'question_id' => $question->question_id,
                'question_text' => $question->question_text,
                'explanation' => $question->explanation,
                'correct_answer_ids' => $question->answers->where('is_correct', true)->pluck('answer_id')->values(),
                'selected_answer_ids' => $responses->get($question->question_id)?->selected_answer_ids ?? [],
                'is_correct' => (bool) $responses->get($question->question_id)?->is_correct,
            ])->values() : null,
        ];
    }

    private function secondsLeft(QuizAttempt $attempt): ?int
    {
        return $attempt->expires_at ? max(0, (int) now()->diffInSeconds($attempt->expires_at, false)) : null;
    }
}
