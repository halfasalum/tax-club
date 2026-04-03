<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EventController extends BaseController
{
    /**
     * GET /api/v1/events
     * Students see upcoming active events. Admins see all.
     * Requires: auth:sanctum
     */
    public function index(Request $request): JsonResponse
    {
        $isAdmin = $request->user()->hasAnyRole(['admin', 'super_admin']);

        $query = Event::with('creator:user_id,full_name')
            ->withCount('registeredUsers')
            ->when(! $isAdmin, fn($q) => $q->upcoming())
            ->when($request->search, fn($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->orderBy('starts_at', 'asc');

        return $this->paginated($query->paginate($request->per_page ?? 15));
    }

    /**
     * GET /api/v1/events/{id}
     * Requires: auth:sanctum
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $event = Event::with('creator:user_id,full_name')->withCount('registeredUsers')->find($id);

        if (! $event) {
            return $this->error('Event not found.', 404);
        }

        // Check if the authenticated user is registered
        $isRegistered = $event->registeredUsers()
            ->where('users.user_id', $request->user()->user_id)
            ->exists();

        return $this->success([
            'event'         => $event,
            'is_registered' => $isRegistered,
        ]);
    }

    /**
     * POST /api/v1/events
     * Requires: add_event
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:300',
            'description' => 'nullable|string',
            'location'    => 'nullable|string|max:300',
            'starts_at'   => 'required|date|after_or_equal:now',
            'ends_at'     => 'nullable|date|after:starts_at',
            'is_active'   => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $event = Event::create([
            ...$request->only(['title', 'description', 'location', 'starts_at', 'ends_at']),
            'created_by' => $request->user()->user_id,
            'is_active'  => $request->boolean('is_active', true),
        ]);

        return $this->success($event, 'Event created successfully.', 201);
    }

    /**
     * PUT /api/v1/events/{id}
     * Requires: edit_event
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $event = Event::find($id);

        if (! $event) {
            return $this->error('Event not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'title'       => 'sometimes|string|max:300',
            'description' => 'nullable|string',
            'location'    => 'nullable|string|max:300',
            'starts_at'   => 'sometimes|date',
            'ends_at'     => 'nullable|date|after:starts_at',
            'is_active'   => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $event->fill($request->only(['title', 'description', 'location', 'starts_at', 'ends_at', 'is_active']))->save();

        return $this->success($event, 'Event updated successfully.');
    }

    /**
     * DELETE /api/v1/events/{id}
     * Requires: delete_event
     */
    public function destroy(string $id): JsonResponse
    {
        $event = Event::find($id);

        if (! $event) {
            return $this->error('Event not found.', 404);
        }

        $event->registeredUsers()->detach(); // clean up registrations
        $event->delete();

        return $this->success(null, 'Event deleted successfully.');
    }

    // ─── Registrations ─────────────────────────────────────────────

    /**
     * POST /api/v1/events/{id}/register
     * Student registers for an event. Requires: auth:sanctum
     */
    public function register(Request $request, string $id): JsonResponse
    {
        $event = Event::find($id);

        if (! $event || ! $event->is_active) {
            return $this->error('Event not found or not available.', 404);
        }

        $user = $request->user();

        if ($event->registeredUsers()->where('users.user_id', $user->user_id)->exists()) {
            return $this->error('You are already registered for this event.', 409);
        }

        $event->registeredUsers()->attach($user->user_id, [
            'status'        => 'registered',
            'registered_at' => now(),
        ]);

        return $this->success(null, 'Registered for event successfully.');
    }

    /**
     * DELETE /api/v1/events/{id}/register
     * Student cancels registration. Requires: auth:sanctum
     */
    public function cancelRegistration(Request $request, string $id): JsonResponse
    {
        $event = Event::find($id);

        if (! $event) {
            return $this->error('Event not found.', 404);
        }

        $event->registeredUsers()->updateExistingPivot($request->user()->user_id, ['status' => 'cancelled']);

        return $this->success(null, 'Registration cancelled successfully.');
    }

    /**
     * GET /api/v1/events/{id}/attendees
     * Admin views all registered users for an event. Requires: view_event_attendees
     */
    public function attendees(Request $request, string $id): JsonResponse
    {
        $event = Event::find($id);

        if (! $event) {
            return $this->error('Event not found.', 404);
        }

        $attendees = $event->registeredUsers()
            ->select('users.user_id', 'full_name', 'email', 'membership_id')
            ->withPivot('status', 'registered_at')
            ->when($request->status, fn($q) => $q->wherePivot('status', $request->status))
            ->paginate($request->per_page ?? 20);

        return $this->paginated($attendees);
    }
}