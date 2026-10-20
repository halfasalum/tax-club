<?php

namespace App\Livewire\Admin\Opportunities;

use App\Livewire\Concerns\ManagesAudience;
use App\Models\Opportunity;
use App\Support\OpportunityBoard;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Post or change an opportunity with its eligibility, audience and
 * application channel (OPP-REQ01, OPP-REQ05, OPP-REQ06).
 */
#[Layout('components.layouts.app')]
class Form extends Component
{
    use ManagesAudience, WithFileUploads;

    public ?Opportunity $opportunity = null;

    public string $title = '';

    public string $organization = 'Tanzania Revenue Authority';

    public string $type = 'internship';

    public string $description = '';

    public string $eligibility = '';

    public string $location = '';

    public string $region = '';

    public string $work_mode = '';

    public string $deadline = '';

    public string $apply_method = 'link';

    public string $apply_url = '';

    public string $apply_email = '';

    public string $apply_instructions = '';

    /** @var TemporaryUploadedFile|null */
    public $attachment = null;

    public function mount(?Opportunity $opportunity = null): void
    {
        abort_unless(auth()->user()->hasControl('add_opportunity') || auth()->user()->hasControl('edit_opportunity'), 403);

        if (! $opportunity?->exists) {
            return;
        }

        $this->opportunity = $opportunity;

        foreach (['title', 'organization', 'type', 'description', 'eligibility', 'location', 'region', 'work_mode', 'apply_method', 'apply_url', 'apply_email', 'apply_instructions'] as $field) {
            $this->{$field} = (string) $opportunity->{$field};
        }

        $this->deadline = $opportunity->deadline?->format('Y-m-d') ?? '';
        $this->fillAudience($opportunity->audience);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'title' => 'required|string|min:5|max:200',
            'organization' => 'required|string|max:200',
            'type' => 'required|in:'.implode(',', Opportunity::TYPES),
            'description' => 'required|string|max:20000',
            'eligibility' => 'nullable|string|max:3000',
            'location' => 'nullable|string|max:200',
            'region' => 'nullable|in:'.implode(',', array_values(config('tanzania.regions'))),
            'work_mode' => 'nullable|in:'.implode(',', Opportunity::WORK_MODES),
            'deadline' => 'nullable|date',
            'apply_method' => 'required|in:'.implode(',', Opportunity::APPLY_METHODS),
            'apply_url' => 'nullable|required_if:apply_method,link|url|max:500',
            'apply_email' => 'nullable|required_if:apply_method,email|email|max:200',
            'apply_instructions' => 'nullable|required_if:apply_method,in_person|string|max:3000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
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
            ...collect(['title', 'organization', 'type', 'description', 'eligibility', 'location', 'region', 'work_mode', 'apply_method', 'apply_url', 'apply_email', 'apply_instructions'])
                ->mapWithKeys(fn ($field) => [$field => trim($this->{$field})])->all(),
            'deadline' => $this->deadline ?: null,
            'audience' => $audience,
        ];
        $user = auth()->user();

        try {
            if ($this->opportunity) {
                OpportunityBoard::update($this->opportunity, $fields, $user, $this->attachment);
            } else {
                $this->opportunity = OpportunityBoard::create($fields, $user, $this->attachment);
            }

            if ($submit) {
                OpportunityBoard::submit($this->opportunity->refresh(), $user);
            }
        } catch (InvalidArgumentException $exception) {
            $this->addError('submit', $exception->getMessage());

            return null;
        }

        session()->flash('status', $submit ? __('Sent for review.') : __('Saved.'));

        return $this->redirectRoute('admin.opportunities.show', $this->opportunity, navigate: true);
    }

    public function render(): mixed
    {
        return view('livewire.admin.opportunities.form', [
            'isLive' => $this->opportunity && $this->opportunity->publication_status !== Opportunity::NOT_PUBLISHED,
        ])->title($this->opportunity ? __('Edit opportunity') : __('New opportunity'));
    }
}
