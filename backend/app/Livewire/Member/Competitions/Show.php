<?php

namespace App\Livewire\Member\Competitions;

use App\Models\Competition;
use App\Support\CompetitionDesk;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * One competition: register, take part, see results (CMP-REQ03-05).
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    use WithFileUploads;

    public Competition $competition;

    public string $text = '';

    public string $url = '';

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    public ?string $statusMessage = null;

    public function mount(string $slug): void
    {
        $this->competition = Competition::live()->where('slug', $slug)->firstOrFail();
        $member = auth()->user();
        abort_unless($this->competition->isVisibleTo($member) || CompetitionDesk::entryFor($this->competition, $member), 404);
        $entry = CompetitionDesk::entryFor($this->competition, $member);
        $this->text = (string) $entry?->submission_text;
        $this->url = (string) $entry?->submission_url;
    }

    public function register(): void
    {
        $this->attempt(fn () => CompetitionDesk::register($this->competition, auth()->user()), __('You are registered. Good luck!'));
    }

    public function withdraw(): void
    {
        $this->attempt(fn () => CompetitionDesk::withdraw($this->competition, auth()->user()), __('You withdrew from the competition.'));
    }

    public function submitEntry(): void
    {
        $this->validate([
            'text' => 'nullable|string|max:20000',
            'url' => 'nullable|url|max:500',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);
        $this->attempt(fn () => CompetitionDesk::submitEntry($this->competition, auth()->user(), $this->text, $this->url, $this->file), __('Your entry was received. You can change it until the competition ends.'));
        $this->file = null;
    }

    private function attempt(callable $action, string $success): void
    {
        $this->resetErrorBag();

        try {
            $action();
        } catch (InvalidArgumentException $exception) {
            $this->addError('entry', $exception->getMessage());

            return;
        }

        $this->statusMessage = $success;
    }

    public function render(): mixed
    {
        $this->competition->refresh();
        $member = auth()->user();

        return view('livewire.member.competitions.show', [
            'entry' => CompetitionDesk::entryFor($this->competition, $member),
            'problem' => CompetitionDesk::registrationProblem($this->competition, $member),
            'results' => $this->competition->results_status === Competition::RESULTS_PUBLISHED ? CompetitionDesk::results($this->competition) : collect(),
            'prizes' => $this->competition->prizes()->with('reward')->get(),
        ])->title($this->competition->title);
    }
}
