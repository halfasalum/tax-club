<?php

namespace App\Livewire\Admin\Institutions;

use App\Support\InstitutionRegistry;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Bulk-register institutions from a CSV file (INS-REQ01).
 */
#[Layout('components.layouts.app')]
class Import extends Component
{
    use WithFileUploads;

    #[Validate('required|file|mimes:csv,txt|max:5120')]
    public ?TemporaryUploadedFile $file = null;

    /** @var array{created: int, skipped: list<array{line: int, name: string, reason: string}>}|null */
    public ?array $report = null;

    public function import(): void
    {
        abort_unless(auth()->user()->hasControl('import_institutions'), 403);

        $this->validate();

        try {
            $this->report = InstitutionRegistry::import($this->file->getRealPath(), auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('file', $exception->getMessage());
        }

        $this->reset('file');
    }

    public function render(): mixed
    {
        return view('livewire.admin.institutions.import')->title(__('Import institutions'));
    }
}
