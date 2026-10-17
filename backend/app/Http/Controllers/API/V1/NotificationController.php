<?php

namespace App\Http\Controllers\API\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * In-app notification inbox for the mobile app (NOT-REQ06).
 */
class NotificationController extends BaseController
{
    /**
     * GET /api/v1/notifications?unread=1
     */
    public function index(Request $request): JsonResponse
    {
        $page = $request->user()->notifications()
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->paginate(20);

        return $this->success([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'notifications' => collect($page->items())->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                'key' => $notification->data['key'] ?? null,
                'message' => $notification->data['message'] ?? '',
                'url' => $notification->data['url'] ?? null,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at->toIso8601String(),
            ]),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/notifications/{id}/read
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->find($id);

        if (! $notification) {
            return $this->error('Notification not found.', 404);
        }

        $notification->markAsRead();

        return $this->success(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    /**
     * POST /api/v1/notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->success(['unread_count' => 0]);
    }
}
