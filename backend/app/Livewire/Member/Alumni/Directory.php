<?php

namespace App\Livewire\Member\Alumni;

use App\Models\User;
use App\Support\AlumniNetwork;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Find and connect with alumni (ALM-REQ02).
 */
#[Layout('components.layouts.app')]
class Directory extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public ?string $connecting = null;

    public string $message = '';

    public ?string $statusMessage = null;

    public function mount(): void
    {
        abort_unless(AlumniNetwork::canUseDirectory(auth()->user()), 403, __('The alumni directory is open to members aged 18 and over.'));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function connect(): void
    {
        $this->validate(['message' => 'nullable|string|max:500']);

        try {
            AlumniNetwork::connect(auth()->user(), User::findOrFail($this->connecting), $this->message);
        } catch (InvalidArgumentException $exception) {
            $this->addError('message', $exception->getMessage());

            return;
        }

        $this->reset('connecting', 'message');
        $this->statusMessage = __('Request sent. You will see their contact details if they accept.');
    }

    public function respond(int $connectionId, bool $accept): void
    {
        try {
            AlumniNetwork::respond(auth()->user(), $connectionId, $accept);
        } catch (InvalidArgumentException $exception) {
            $this->addError('message', $exception->getMessage());

            return;
        }

        $this->statusMessage = $accept ? __('Connected. You can now see each other\'s contact details.') : __('Request declined.');
    }

    public function render(): mixed
    {
        $viewer = auth()->user();
        $profiles = AlumniNetwork::directory($viewer, $this->search);

        return view('livewire.member.alumni.directory', [
            'profiles' => $profiles,
            'requests' => AlumniNetwork::pendingRequestsFor($viewer),
            'connection' => fn (User $alumnus) => AlumniNetwork::connectionBetween($viewer, $alumnus),
            'contact' => fn (User $alumnus) => AlumniNetwork::contactFor($viewer, $alumnus),
        ])->title(__('Alumni directory'));
    }
}
