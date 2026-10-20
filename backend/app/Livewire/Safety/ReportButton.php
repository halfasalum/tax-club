<?php

namespace App\Livewire\Safety;

use App\Models\ModerationCase;
use App\Support\SafetyDesk;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Report" link for any post, profile, story or person (SAF-REQ01,
 * COM-REQ07). Opens a short form; the report goes to moderators.
 */
class ReportButton extends Component
{
    #[Locked]
    public string $type;

    #[Locked]
    public string $subjectId;

    public bool $open = false;

    public string $category = '';

    public string $details = '';

    public ?string $sent = null;

    public function mount(string $type, string $subjectId): void
    {
        $this->type = $type;
        $this->subjectId = $subjectId;
    }

    public function send(): void
    {
        $this->validate([
            'category' => 'required|in:'.implode(',', ModerationCase::CATEGORIES),
            'details' => 'nullable|string|max:2000',
        ], ['category.required' => __('Choose what is wrong.')]);

        try {
            $case = SafetyDesk::report(auth()->user(), $this->type, $this->subjectId, $this->category, $this->details);
        } catch (InvalidArgumentException $exception) {
            $this->addError('category', $exception->getMessage());

            return;
        }

        $this->reset('category', 'details', 'open');
        $this->sent = $case->reference;
    }

    public function render(): mixed
    {
        return view('livewire.safety.report-button', ['categories' => SafetyDesk::categoryLabels()]);
    }
}
