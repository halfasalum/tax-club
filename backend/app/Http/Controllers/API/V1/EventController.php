<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Support\Audience;
use App\Support\EventManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Events for the mobile app (EVT-REQ01-08). Members see published events
 * meant for them; organizers manage events through the same workflow as
 * the Administration Portal.
 */
class EventController extends BaseController
{
    /**
     * GET /api/v1/events
     * Members: upcoming events for their audience. Organizers (add_event,
     * edit_event) may pass ?all=1 to list every event.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizer = $user->hasControl('add_event') || $user->hasControl('edit_event');

        if ($organizer && $request->boolean('all')) {
            $query = Event::query()
                ->when($request->search, fn ($query) => $query->where('title', 'like', "%{$request->search}%"))
                ->latest('starts_at');

            return $this->paginated($query->paginate(min(50, (int) ($request->per_page ?? 15))));
        }

        $registrations = EventRegistration::where('user_id', $user->user_id)->get()->keyBy('event_id');
        $events = Event::upcoming()
            ->when($request->type, fn ($query) => $query->where('type', $request->type))
            ->when($request->search, fn ($query) => $query->where('title', 'like', "%{$request->search}%"))
            ->orderBy('starts_at')
            ->limit(100)
            ->get()
            ->filter(fn (Event $event) => $event->isVisibleTo($user))
            ->values()
            ->map(fn (Event $event) => $this->present($event, $user, $registrations->get($event->event_id)));

        return $this->success($events);
    }

    /**
     * GET /api/v1/events/mine — events the member registered for.
     */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        $registrations = EventRegistration::where('user_id', $user->user_id)->where('status', '!=', EventRegistration::CANCELLED)->get()->keyBy('event_id');

        return $this->success(Event::live()->whereIn('event_id', $registrations->keys())->orderByDesc('starts_at')->get()
            ->map(fn (Event $event) => $this->present($event, $user, $registrations->get($event->event_id))));
    }

    /**
     * GET /api/v1/events/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $event = Event::find($id) ?? Event::where('slug', $id)->first();

        if (! $event || (! $event->isVisibleTo($user) && ! $this->isOrganizer($user))) {
            return $this->error('Event not found.', 404);
        }

        $registration = EventManager::registrationFor($event, $user);

        return $this->success([
            ...$this->present($event, $user, $registration),
            'description' => $event->description,
            'objective' => $event->objective,
            'registration_question' => $event->registration_question,
            'registration_problem' => $registration?->isActive() ? null : EventManager::registrationProblem($event, $user),
            'online_url' => $registration && in_array($registration->status, [EventRegistration::REGISTERED, EventRegistration::ATTENDED], true) || ! $event->registration_required ? $event->online_url : null,
            'updates' => EventManager::updatesFor($event, $user)->map(fn ($update) => [
                'id' => $update->id,
                'kind' => $update->kind,
                'title' => $update->title,
                'body' => $update->body,
                'link_url' => $update->link_url,
                'file_url' => $update->file_path ? route('events.material', [$event, $update]) : null,
                'posted_at' => $update->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * POST /api/v1/events — create a draft. Requires: add_event
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $event = EventManager::create($this->fields($validator->validated()), $request->user());

        if ($request->boolean('submit')) {
            try {
                EventManager::submit($event, $request->user());
            } catch (InvalidArgumentException $exception) {
                return $this->error($exception->getMessage(), 422);
            }
        }

        return $this->success($event->fresh(), 'Event created.', 201);
    }

    /**
     * PUT /api/v1/events/{id}. Requires: edit_event
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $event = Event::find($id);

        if (! $event) {
            return $this->error('Event not found.', 404);
        }

        $validator = Validator::make($request->all(), $this->rules(partial: true));

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        try {
            EventManager::update($event, $this->fields($validator->validated()), $request->user());
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success($event->fresh(), 'Event updated.');
    }

    /**
     * DELETE /api/v1/events/{id}. Only drafts nobody registered for can be
     * deleted; others are cancelled or archived. Requires: delete_event
     */
    public function destroy(string $id): JsonResponse
    {
        $event = Event::find($id);

        if (! $event) {
            return $this->error('Event not found.', 404);
        }

        if ($event->publication_status !== Event::NOT_PUBLISHED || $event->registrations()->exists()) {
            return $this->error('Published events cannot be deleted. Cancel or archive the event instead.', 422);
        }

        $event->delete();

        return $this->success(null, 'Event deleted.');
    }

    /**
     * POST /api/v1/events/{id}/register — body: { answer? }
     */
    public function register(Request $request, string $id): JsonResponse
    {
        $event = Event::live()->find($id);

        if (! $event || ! $event->isVisibleTo($request->user())) {
            return $this->error('Event not found or not available.', 404);
        }

        try {
            $registration = EventManager::register($event, $request->user(), $request->string('answer')->toString());
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success([
            'status' => $registration->status,
            'waitlist_position' => $registration->waitlistPosition(),
        ], $registration->status === EventRegistration::WAITLISTED ? 'Added to the waitlist.' : 'Registered.', 201);
    }

    /**
     * DELETE /api/v1/events/{id}/register
     */
    public function cancelRegistration(Request $request, string $id): JsonResponse
    {
        $event = Event::find($id);

        if (! $event) {
            return $this->error('Event not found.', 404);
        }

        try {
            EventManager::cancelRegistration($event, $request->user());
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(null, 'Registration cancelled.');
    }

    /**
     * GET /api/v1/events/{id}/attendees. Requires: view_event_attendees
     */
    public function attendees(Request $request, string $id): JsonResponse
    {
        $event = Event::find($id);

        if (! $event) {
            return $this->error('Event not found.', 404);
        }

        $registrations = $event->registrations()
            ->with('user:user_id,full_name,membership_id,user_type,institution_id')
            ->when($request->status, fn ($query) => $query->where('status', $request->status))
            ->orderBy('registered_at')
            ->paginate(min(100, (int) ($request->per_page ?? 20)));

        return $this->paginated($registrations);
    }

    /**
     * GET /api/v1/events/{id}/summary. Requires: view_event_attendees
     */
    public function summary(string $id): JsonResponse
    {
        $event = Event::find($id);

        return $event ? $this->success(EventManager::summary($event)) : $this->error('Event not found.', 404);
    }

    /**
     * POST /api/v1/events/{id}/check-in — body: { card: scanned QR text or
     * membership number, walk_in?: bool }. Requires: check_in_attendees
     */
    public function checkIn(Request $request, string $id): JsonResponse
    {
        $event = Event::find($id);

        if (! $event) {
            return $this->error('Event not found.', 404);
        }

        $request->validate(['card' => 'required|string|max:300', 'walk_in' => 'sometimes|boolean']);

        try {
            $result = EventManager::checkIn($event, $request->string('card')->toString(), $request->user(), $request->boolean('walk_in'));
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        $member = $result['member'];

        return $this->success([
            'result' => $result['result'],
            'member' => $member ? [
                'full_name' => $member->full_name,
                'membership_id' => $member->membership_id,
                'institution' => $member->institution?->name,
                'is_minor' => $member->isMinor(),
            ] : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Event $event, User $user, ?EventRegistration $registration): array
    {
        return [
            'event_id' => $event->event_id,
            'code' => $event->code,
            'slug' => $event->slug,
            'title' => $event->title,
            'type' => $event->type,
            'mode' => $event->mode,
            'organizer' => $event->organizer,
            'location' => $event->location,
            'region' => $event->region,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'status' => $event->displayStatus(),
            'audience' => Audience::describe($event->audience),
            'registration_required' => $event->registration_required,
            'registration_closes_at' => $event->registrationDeadline()?->toIso8601String(),
            'capacity' => $event->capacity,
            'places_left' => $event->placesLeft(),
            'waitlist_enabled' => $event->waitlist_enabled,
            'my_registration' => $registration && $registration->status !== EventRegistration::CANCELLED ? [
                'status' => $registration->status,
                'waitlist_position' => $registration->waitlistPosition(),
                'checked_in_at' => $registration->checked_in_at?->toIso8601String(),
            ] : null,
        ];
    }

    private function isOrganizer(User $user): bool
    {
        return collect(['add_event', 'edit_event', 'review_event', 'publish_event', 'view_event_attendees', 'check_in_attendees'])->contains(fn ($control) => $user->hasControl($control));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'title' => "{$required}|string|min:5|max:300",
            'type' => 'sometimes|in:'.implode(',', Event::TYPES),
            'mode' => 'sometimes|in:'.implode(',', Event::MODES),
            'description' => "{$required}|string|max:20000",
            'objective' => 'nullable|string|max:2000',
            'organizer' => 'nullable|string|max:200',
            'location' => 'nullable|string|max:300',
            'region' => 'nullable|in:'.implode(',', array_values(config('tanzania.regions'))),
            'online_url' => 'nullable|url|max:500',
            'starts_at' => "{$required}|date",
            'ends_at' => 'nullable|date|after:starts_at',
            'audience' => 'nullable|array',
            'registration_required' => 'sometimes|boolean',
            'registration_closes_at' => 'nullable|date',
            'capacity' => 'nullable|integer|min:1',
            'waitlist_enabled' => 'sometimes|boolean',
            'registration_question' => 'nullable|string|max:300',
            'is_public' => 'sometimes|boolean',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        foreach (['starts_at', 'ends_at', 'registration_closes_at'] as $date) {
            if (! empty($data[$date])) {
                $data[$date] = Carbon::parse($data[$date]);
            }
        }

        return $data;
    }
}
