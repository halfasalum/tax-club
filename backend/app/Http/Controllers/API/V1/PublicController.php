<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Content;
use App\Models\Event;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PublicController extends BaseController
{
    /**
     * GET /api/v1/public/highlights
     * Unauthenticated landing-page data: platform stats, latest published
     * content, and upcoming events. No auth required.
     */
    public function highlights(): JsonResponse
    {
        $stats = [
            'members' => User::where('is_verified', true)->count(),
            'content' => Content::active()->count(),
            'quizzes' => Quiz::active()->count(),
        ];

        $latestContent = Content::active()
            ->orderBy('published_at', 'desc')
            ->limit(6)
            ->get(['content_id', 'title', 'content_type', 'file_url', 'published_at']);

        $upcomingEvents = Event::upcoming()
            ->orderBy('starts_at')
            ->limit(4)
            ->get(['event_id', 'title', 'location', 'starts_at']);

        return $this->success([
            'stats' => $stats,
            'latest_content' => $latestContent,
            'upcoming_events' => $upcomingEvents,
        ]);
    }
}
