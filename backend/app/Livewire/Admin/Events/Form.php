<?php

namespace App\Livewire\Admin\Events;

use App\Livewire\Concerns\ManagesAudience;
use App\Models\Event;
use App\Support\EventManager;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Propose or change an event or campaign (EVT-REQ01).
 */
#[Layout('components.layouts.app')]
class Form extends Component
{
    use ManagesAudience;

    public ?Event $event = null;

    public string $title = '';

    public string $type = 'workshop';

    public string $mode = 'in_person';

    public string $description = '';

    public string $objective = '';

    public string $organizer = '';

    public string $location = '';

    public string $region = '';

    public string $online_url = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public bool $registration_required = true;

    public string $registration_closes_at = '';

    public ?int $capacity = null;

    public bool $waitlist_enabled = true;

    public string $registration_question = '';

    public bool $is_public = true;

    public function mount(?Event $event = null): void
    {
        abort_unless(auth()->user()->hasControl($event?->exists ? 'edit_event' : 'add_event') || auth()->user()->hasControl('add_event'), 403);

        if (! $event?->exists) {
            $this->organizer = 'TRA';

            return;
        }

        $this->event = $event;

        foreach (['title', 'type', 'mode', 'description', 'objective', 'organizer', 'location', 'region', 'online_url', 'registration_question'] as $field) {
            $this->{$field} = (string) $event->{$field};
        }

        foreach (['starts_at', 'ends_at', 'registration_closes_at'] as $field) {
            $this->{$field} = $event->{$field}?->format('Y-m-d\TH:i') ?? '';
        }

        $this->registration_required = $event->registration_required;
        $this->capacity = $event->capacity;
        $this->waitlist_enabled = $event->waitlist_enabled;
        $this->is_public = $event->is_public;
        $this->fillAudience($event->audience);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'title' => 'required|string|min:5|max:300',
            'type' => 'required|in:'.implode(',', Event::TYPES),
            'mode' => 'required|in:'.implode(',', Event::MODES),
            'description' => 'required|string|max:20000',
            'objective' => 'nullable|string|max:2000',
            'organizer' => 'nullable|string|max:200',
            'location' => 'nullable|required_unless:mode,online|string|max:300',
            'region' => 'nullable|in:'.implode(',', array_values(config('tanzania.regions'))),
            'online_url' => 'nullable|required_unless:mode,in_person|url|max:500',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'registration_closes_at' => 'nullable|date|before_or_equal:'.($this->ends_at ?: $this->starts_at ?: 'now'),
            'capacity' => 'nullable|integer|min:1|max:100000',
            'registration_question' => 'nullable|string|max:300',
        ];
    }

    public function save(bool $submit = false): mixed
    {
        $this->validate();
        $audience = $this->audienceRules();

        if ($audience === null) {
            return null;
        }

        $fields = [
            'title' => trim($this->title),
            'type' => $this->type,
            'mode' => $this->mode,
            'description' => trim($this->description),
            'objective' => trim($this->objective),
            'organizer' => trim($this->organizer),
            'location' => $this->mode === 'online' ? null : trim($this->location),
            'region' => $this->region,
            'online_url' => $this->mode === 'in_person' ? null : trim($this->online_url),
            'starts_at' => Carbon::parse($this->starts_at),
            'ends_at' => $this->ends_at ? Carbon::parse($this->ends_at) : null,
            'audience' => $audience,
            'registration_required' => $this->registration_required,
            'registration_closes_at' => $this->registration_closes_at ? Carbon::parse($this->registration_closes_at) : null,
            'capacity' => $this->capacity,
            'waitlist_enabled' => $this->waitlist_enabled,
            'registration_question' => trim($this->registration_question),
            'is_public' => $this->is_public,
        ];
        $user = auth()->user();

        try {
            if ($this->event) {
                EventManager::update($this->event, $fields, $user);
            } else {
                $this->event = EventManager::create($fields, $user);
            }

            if ($submit) {
                EventManager::submit($this->event->refresh(), $user);
            }
        } catch (InvalidArgumentException $exception) {
            $this->addError('submit', $exception->getMessage());

            return null;
        }

        session()->flash('status', $submit ? __('Sent for review.') : __('Saved.'));

        return $this->redirectRoute('admin.events.show', $this->event, navigate: true);
    }

    public function render(): mixed
    {
        return view('livewire.admin.events.form', [
            'isLive' => $this->event && $this->event->publication_status !== Event::NOT_PUBLISHED,
        ])->title($this->event ? __('Edit event') : __('New event'));
    }
}
