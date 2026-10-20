<?php

namespace App\Livewire\Member\Ask;

use App\Models\Question;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A published question and answer from the knowledge base (COM-REQ04).
 */
#[Layout('components.layouts.app')]
class Answer extends Component
{
    public Question $question;

    public function mount(string $slug): void
    {
        $this->question = Question::published()->where('slug', $slug)->firstOrFail();
    }

    public function render(): mixed
    {
        return view('livewire.member.ask.answer')->title($this->question->public_question);
    }
}
