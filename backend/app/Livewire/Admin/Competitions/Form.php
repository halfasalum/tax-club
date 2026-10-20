<?php

namespace App\Livewire\Admin\Competitions;

use App\Livewire\Concerns\ManagesAudience;
use App\Models\Competition;
use App\Models\Quiz;
use App\Support\CompetitionDesk;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Create or change a competition (CMP-REQ01).
 */
#[Layout('components.layouts.app')]
class Form extends Component
{
    use ManagesAudience;

    public ?Competition $competition = null;

    public string $title = '';

    public string $description = '';

    public string $rules = '';

    public string $type = 'quiz';

    public ?string $quiz_id = null;

    public string $submission_kind = 'text';

    public ?int $max_participants = null;

    public string $registration_closes_at = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public int $winners_count = 3;

    public function mount(?Competition $competition = null): void
    {
        abort_unless(auth()->user()->hasControl('create_competition'), 403);

        if (! $competition?->exists) {
            return;
        }

        $this->competition = $competition;

        foreach (['title', 'description', 'rules', 'type', 'submission_kind'] as $field) {
            $this->{$field} = (string) ($competition->{$field} ?? $this->{$field});
        }

        foreach (['registration_closes_at', 'starts_at', 'ends_at'] as $field) {
            $this->{$field} = $competition->{$field}?->format('Y-m-d\TH:i') ?? '';
        }

        $this->quiz_id = $competition->quiz_id;
        $this->max_participants = $competition->max_participants;
        $this->winners_count = $competition->winners_count;
        $this->fillAudience($competition->audience);
    }

    public function save(bool $submit = false): mixed
    {
        $this->validate([
            'title' => 'required|string|min:5|max:200',
            'description' => 'required|string|max:10000',
            'rules' => 'nullable|string|max:10000',
            'type' => 'required|in:'.implode(',', Competition::TYPES),
            'quiz_id' => 'nullable|required_if:type,quiz|exists:quizzes,quiz_id',
            'submission_kind' => 'required_if:type,submission|in:'.implode(',', Competition::SUBMISSION_KINDS),
            'max_participants' => 'nullable|integer|min:1',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'registration_closes_at' => 'nullable|date|before_or_equal:ends_at',
            'winners_count' => 'required|integer|min:1|max:50',
        ]);
        $audience = $this->audienceRules();

        if ($audience === null) {
            return null;
        }

        $fields = [
            'title' => trim($this->title),
            'description' => trim($this->description),
            'rules' => trim($this->rules),
            'type' => $this->type,
            'quiz_id' => $this->quiz_id,
            'submission_kind' => $this->submission_kind,
            'audience' => $audience,
            'max_participants' => $this->max_participants,
            'registration_closes_at' => $this->registration_closes_at ? Carbon::parse($this->registration_closes_at) : null,
            'starts_at' => Carbon::parse($this->starts_at),
            'ends_at' => Carbon::parse($this->ends_at),
            'winners_count' => $this->winners_count,
        ];
        $user = auth()->user();

        try {
            if ($this->competition) {
                CompetitionDesk::update($this->competition, $fields, $user);
            } else {
                $this->competition = CompetitionDesk::create($fields, $user);
            }

            if ($submit) {
                CompetitionDesk::submit($this->competition->refresh(), $user);
            }
        } catch (InvalidArgumentException $exception) {
            $this->addError('submit', $exception->getMessage());

            return null;
        }

        session()->flash('status', $submit ? __('Sent for review.') : __('Saved.'));

        return $this->redirectRoute('admin.competitions.show', $this->competition, navigate: true);
    }

    public function render(): mixed
    {
        return view('livewire.admin.competitions.form', [
            'quizzes' => Quiz::whereIn('editorial_status', [Quiz::APPROVED])->whereNot('publication_status', Quiz::ARCHIVED)->orderBy('title')->get(['quiz_id', 'title', 'code']),
        ])->title($this->competition ? __('Edit competition') : __('New competition'));
    }
}
