<?php

namespace App\Livewire\Member\Ask;

use App\Models\Question;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The status and answer of the member's own question (COM-REQ04).
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public Question $question;

    public ?string $statusMessage = null;

    public function mount(string $reference): void
    {
        $this->question = Question::where('reference', $reference)->where('user_id', auth()->id())->firstOrFail();
        $this->statusMessage = session('status');
    }

    public function render(): mixed
    {
        return view('livewire.member.ask.show')->title($this->question->reference);
    }
}
