<?php

namespace App\Livewire\Admin\Events;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventUpdate;
use App\Support\EventManager;
use App\Support\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Review, publish and run an event: registrations, waitlist, updates,
 * attendance corrections and the participation summary (EVT-REQ02-08).
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    use WithFileUploads, WithPagination;

    public Event $event;

    public string $reviewNote = '';

    public string $publishAt = '';

    public string $reason = '';

    public string $newStartsAt = '';

    public string $newEndsAt = '';

    public string $closingNotes = '';

    #[Url(as: 'list')]
    public string $listView = 'registered';

    public string $search = '';

    public ?int $correcting = null;

    public string $correctionReason = '';

    public string $updateKind = 'update';

    public string $updateTitle = '';

    public string $updateBody = '';

    public string $updateLink = '';

    public bool $updateRegisteredOnly = false;

    /** @var TemporaryUploadedFile|null */
    public $updateFile = null;

    public ?string $statusMessage = null;

    public function mount(Event $event): void
    {
        $this->event = $event;
        $this->statusMessage = session('status');
    }

    public function updatedListView(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function submit(): void
    {
        $this->authorizeAny(['add_event', 'edit_event']);
        $this->attempt(fn () => EventManager::submit($this->event, auth()->user()), __('Sent for review.'));
    }

    public function approve(): void
    {
        $this->authorizeAny(['review_event']);
        $this->attempt(fn () => EventManager::approve($this->event, auth()->user(), trim($this->reviewNote) ?: null), __('Approved. It can now be published.'));
    }

    public function returnForChanges(): void
    {
        $this->authorizeAny(['review_event']);
        $this->attempt(fn () => EventManager::returnForChanges($this->event, auth()->user(), trim($this->reviewNote)), __('Returned to the organizer with your notes.'));
    }

    public function reject(): void
    {
        $this->authorizeAny(['review_event']);
        $this->attempt(fn () => EventManager::reject($this->event, auth()->user(), trim($this->reviewNote)), __('Rejected.'));
    }

    public function publish(): void
    {
        $this->authorizeAny(['publish_event']);
        $this->validate(['publishAt' => 'nullable|date']);
        $at = $this->publishAt ? Carbon::parse($this->publishAt) : null;
        $this->attempt(fn () => EventManager::publish($this->event, auth()->user(), $at), $at?->isFuture() ? __('Scheduled.') : __('Published. Members can see it and register.'));
    }

    public function postpone(): void
    {
        $this->authorizeAny(['publish_event']);
        $this->validate(['newStartsAt' => 'nullable|date|after:now', 'newEndsAt' => 'nullable|date|after:newStartsAt']);
        $this->attempt(fn () => EventManager::postpone(
            $this->event,
            auth()->user(),
            $this->reason,
            $this->newStartsAt ? Carbon::parse($this->newStartsAt) : null,
            $this->newEndsAt ? Carbon::parse($this->newEndsAt) : null,
        ), __('Postponed. Registered members were told.'));
    }

    public function cancel(): void
    {
        $this->authorizeAny(['publish_event']);
        $this->attempt(fn () => EventManager::cancel($this->event, auth()->user(), $this->reason), __('Cancelled. Registered members were told.'));
    }

    public function close(): void
    {
        $this->authorizeAny(['publish_event']);
        $this->attempt(fn () => EventManager::close($this->event, auth()->user(), $this->closingNotes), __('Closed. The participation summary is final.'));
    }

    public function archive(): void
    {
        $this->authorizeAny(['publish_event']);
        $this->attempt(fn () => EventManager::archive($this->event, auth()->user(), $this->reason), __('Archived.'));
    }

    public function checkIn(int $registrationId): void
    {
        $this->authorizeAny(['check_in_attendees']);
        $registration = $this->event->registrations()->findOrFail($registrationId);
        $this->attempt(fn () => EventManager::checkInManually($this->event, $registration, auth()->user()), __(':name is checked in.', ['name' => $registration->user->full_name]));
    }

    public function startCorrection(int $registrationId): void
    {
        $this->correcting = $registrationId;
        $this->correctionReason = '';
    }

    public function saveCorrection(): void
    {
        $this->authorizeAny(['check_in_attendees']);
        $registration = $this->event->registrations()->findOrFail($this->correcting);
        $attended = $registration->status !== EventRegistration::ATTENDED;

        $this->attempt(function () use ($registration, $attended) {
            EventManager::correctAttendance($registration, $attended, $this->correctionReason, auth()->user());
            $this->correcting = null;
        }, $attended ? __('Marked as attended.') : __('Attendance removed.'));
    }

    public function postUpdate(): void
    {
        $this->authorizeAny(['add_event', 'edit_event']);
        $this->validate([
            'updateKind' => 'required|in:'.implode(',', EventUpdate::KINDS),
            'updateTitle' => 'required|string|max:200',
            'updateBody' => 'nullable|string|max:5000',
            'updateLink' => 'nullable|url|max:500',
            'updateFile' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,pptx,docx|max:10240',
        ]);

        $this->attempt(function () {
            EventManager::postUpdate($this->event, [
                'kind' => $this->updateKind,
                'title' => $this->updateTitle,
                'body' => $this->updateBody,
                'link_url' => $this->updateLink,
                'registered_only' => $this->updateRegisteredOnly,
            ], auth()->user(), $this->updateFile);
            $this->reset('updateKind', 'updateTitle', 'updateBody', 'updateLink', 'updateRegisteredOnly', 'updateFile');
        }, __('Posted. Registered members were told.'));
    }

    public function removeUpdate(int $updateId): void
    {
        $this->authorizeAny(['add_event', 'edit_event']);
        $update = $this->event->updates()->findOrFail($updateId);
        $this->attempt(fn () => EventManager::removeUpdate($update, auth()->user()), __('Removed.'));
    }

    #[Computed]
    public function registrations(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return $this->event->registrations()
            ->with(['user:user_id,full_name,membership_id,user_type,institution_id,phone', 'user.institution:institution_id,name,region', 'checkedInBy:user_id,full_name'])
            ->when($this->listView !== 'all', fn ($query) => $query->where('status', $this->listView))
            ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($query) => $query->where('full_name', 'like', "%{$search}%")->orWhere('membership_id', 'like', "%{$search}%")))
            ->orderByRaw($this->listView === EventRegistration::WAITLISTED ? 'waitlisted_at asc, id asc' : 'registered_at desc')
            ->paginate(25);
    }

    private function attempt(callable $action, string $success): void
    {
        $this->resetErrorBag();

        try {
            $action();
        } catch (InvalidArgumentException $exception) {
            $this->addError('action', $exception->getMessage());

            return;
        }

        $this->reset('reviewNote', 'reason', 'publishAt', 'newStartsAt', 'newEndsAt', 'closingNotes');
        $this->statusMessage = $success;
        unset($this->registrations);
    }

    /**
     * @param  list<string>  $controls
     */
    private function authorizeAny(array $controls): void
    {
        abort_unless(collect($controls)->contains(fn ($control) => auth()->user()->hasControl($control)), 403);
    }

    public function render(): mixed
    {
        $this->event->refresh();
        $user = auth()->user();

        return view('livewire.admin.events.show', [
            'problems' => EventManager::readinessErrors($this->event),
            'summary' => EventManager::summary($this->event),
            'updates' => $this->event->updates()->with('author:user_id,full_name')->get(),
            'history' => $this->event->history(),
            'checkInOpen' => EventManager::checkInIsOpen($this->event),
            'can' => [
                'edit' => $user->hasControl('add_event') || $user->hasControl('edit_event'),
                'review' => $user->hasControl('review_event'),
                'publish' => $user->hasControl('publish_event'),
                'checkIn' => $user->hasControl('check_in_attendees'),
                'attendees' => $user->hasControl('view_event_attendees'),
            ],
            'ownSubmission' => $this->event->editorial_status === Review::IN_REVIEW && $this->event->submitted_by === $user->user_id,
        ])->title($this->event->title);
    }
}
