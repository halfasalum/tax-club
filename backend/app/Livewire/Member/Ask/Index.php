<?php

namespace App\Livewire\Member\Ask;

use App\Models\ContentCategory;
use App\Models\Question;
use App\Support\AskTra;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Ask TRA a tax question, follow your questions and search answers
 * (COM-REQ01, COM-REQ04).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public string $subject = '';

    public string $body = '';

    public ?string $category_id = null;

    public bool $allow_publish = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function ask(): mixed
    {
        $this->validate([
            'subject' => 'required|string|min:8|max:200',
            'body' => 'required|string|min:15|max:3000',
            'category_id' => 'nullable|exists:content_categories,id',
        ]);

        try {
            $question = AskTra::ask(auth()->user(), [
                'subject' => $this->subject,
                'body' => $this->body,
                'category_id' => $this->category_id ? (int) $this->category_id : null,
                'allow_publish' => $this->allow_publish,
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->addError('body', $exception->getMessage());

            return null;
        }

        session()->flash('status', __('Your question was sent to TRA. Your reference is :reference.', ['reference' => $question->reference]));

        return $this->redirectRoute('member.ask.show', $question->reference, navigate: true);
    }

    public function render(): mixed
    {
        return view('livewire.member.ask.index', [
            'mine' => Question::where('user_id', auth()->id())->latest()->limit(20)->get(),
            'answers' => AskTra::knowledgeBase($this->search, perPage: 10),
            'categories' => ContentCategory::active()->get(),
        ])->title(__('Ask TRA'));
    }
}
