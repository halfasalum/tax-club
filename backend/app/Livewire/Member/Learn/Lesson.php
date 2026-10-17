<?php

namespace App\Livewire\Member\Learn;

use App\Models\Content;
use App\Models\LearningModule;
use App\Support\LearningProgress;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A lesson for a signed-in member (LRN-REQ05, LRN-REQ06).
 */
#[Layout('components.layouts.app')]
class Lesson extends Component
{
    public Content $lesson;

    public ?string $statusMessage = null;

    public function mount(Content $lesson): void
    {
        abort_unless($lesson->isLive() && $lesson->isFor(auth()->user()), 404);

        $this->lesson = $lesson;
        LearningProgress::open(auth()->user(), $lesson);
    }

    public function complete(): void
    {
        LearningProgress::complete(auth()->user(), $this->lesson);
        $this->statusMessage = __('Lesson completed. Well done!');
    }

    public function render(): mixed
    {
        $member = auth()->user();
        $progress = $member->progress()->where('content_id', $this->lesson->content_id)->first();
        $module = LearningModule::published()->availableTo($member)
            ->whereHas('lessons', fn ($query) => $query->where('content.content_id', $this->lesson->content_id))
            ->with('lessons')
            ->first();
        $next = $module?->lessons->filter(fn (Content $lesson) => $lesson->isLive())->values()
            ->skipUntil(fn (Content $lesson) => $lesson->content_id === $this->lesson->content_id)->skip(1)->first();

        return view('livewire.member.learn.lesson', [
            'progress' => $progress,
            'module' => $module,
            'next' => $next,
            'quiz' => $this->lesson->quiz,
            'updatedSinceCompleted' => $progress?->status === 'completed' && $progress->content_version && $progress->content_version < $this->lesson->current_version,
        ])->title($this->lesson->title);
    }
}
