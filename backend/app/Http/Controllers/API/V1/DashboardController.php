<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Content;
use App\Models\Event;
use App\Models\Opportunity;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseController
{
    /**
     * GET /api/v1/dashboard
     * Returns contextual stats based on the authenticated user's role.
     * Requires: auth:sanctum
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return $this->adminDashboard();
        }

        return $this->studentDashboard($user);
    }

    // ─── Admin Dashboard ───────────────────────────────────────────

    private function adminDashboard(): JsonResponse
    {
        $stats = [
            'users' => [
                'total'      => User::count(),
                'verified'   => User::where('is_verified', true)->count(),
                'unverified' => User::where('is_verified', false)->count(),
                'by_type'    => User::select('user_type', DB::raw('count(*) as total'))
                                    ->groupBy('user_type')
                                    ->pluck('total', 'user_type'),
            ],
            'content' => [
                'total'     => Content::count(),
                'active'    => Content::where('is_active', true)->count(),
                'by_type'   => Content::select('content_type', DB::raw('count(*) as total'))
                                      ->groupBy('content_type')
                                      ->pluck('total', 'content_type'),
            ],
            'quizzes' => [
                'total_attempts' => QuizAttempt::count(),
                'avg_score'      => round(QuizAttempt::avg(DB::raw('(score / max_score) * 100')), 1),
            ],
            'opportunities' => [
                'total'  => Opportunity::count(),
                'active' => Opportunity::where('is_active', true)->count(),
                'open'   => Opportunity::open()->count(),
            ],
            'events' => [
                'total'    => Event::count(),
                'upcoming' => Event::upcoming()->count(),
            ],
            'recent_registrations' => User::with('institution:institution_id,name')
                ->select('user_id', 'full_name', 'email', 'user_type', 'is_verified', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
        ];

        return $this->success($stats, 'Admin dashboard data.');
    }

    // ─── Student Dashboard ─────────────────────────────────────────

    private function studentDashboard(User $user): JsonResponse
    {
        $userId = $user->user_id;

        $progressStats = UserProgress::where('user_id', $userId)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentAttempts = QuizAttempt::with('quiz:quiz_id,title')
            ->where('user_id', $userId)
            ->orderBy('attempted_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($a) => [
                'quiz'             => $a->quiz?->title,
                'score'            => $a->score,
                'max_score'        => $a->max_score,
                'score_percentage' => $a->score_percentage,
                'attempted_at'     => $a->attempted_at,
            ]);

        $upcomingEvents = Event::upcoming()
            ->whereHas('registeredUsers', fn($q) => $q->where('users.user_id', $userId))
            ->select('event_id', 'title', 'location', 'starts_at')
            ->orderBy('starts_at')
            ->limit(5)
            ->get();

        $openOpportunities = Opportunity::open()
            ->select('opportunity_id', 'title', 'organization', 'type', 'deadline')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $stats = [
            'membership_id'    => $user->membership_id,
            'progress' => [
                'completed'    => $progressStats['completed'] ?? 0,
                'in_progress'  => $progressStats['in_progress'] ?? 0,
                'not_started'  => $progressStats['not_started'] ?? 0,
            ],
            'quiz_attempts'         => QuizAttempt::where('user_id', $userId)->count(),
            'recent_quiz_attempts'  => $recentAttempts,
            'upcoming_events'       => $upcomingEvents,
            'open_opportunities'    => $openOpportunities,
        ];

        return $this->success($stats, 'Student dashboard data.');
    }
}