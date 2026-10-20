<?php

namespace App\Livewire\Member\Quizzes;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Quizzes open to the member, and their results (GAM-REQ05).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    /** Quiz language; defaults to the language the member uses the site in. */
    #[Url(as: 'lang_filter')]
    public ?string $language = null;

    public function mount(): void
    {
        // Until something is published in the member's language, show everything.
        $this->language ??= Quiz::open()->where('language', app()->getLocale())->exists() ? app()->getLocale() : 'all';
    }

    public function render(): mixed
    {
        $member = auth()->user();
        $results = QuizAttempt::where('user_id', $member->user_id)->where('is_best_attempt', true)->get()->keyBy('quiz_id');

        return view('livewire.member.quizzes.index', [
            'open' => Quiz::availableTo($member)
                ->when(in_array($this->language, ['sw', 'en'], true), fn ($query) => $query->where(fn ($query) => $query->where('language', $this->language)->orWhereNull('language')))
                ->with('category')->withCount(['questions' => fn ($query) => $query->where('is_active', true)])->orderByRaw('closes_at is null')->orderBy('closes_at')->latest('published_at')->get(),
            'results' => $results,
            'past' => Quiz::whereIn('quiz_id', $results->keys())->get()->reject(fn (Quiz $quiz) => $quiz->isAvailableTo($member)),
        ])->title(__('Quizzes'));
    }
}
