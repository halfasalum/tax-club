<?php

namespace App\Livewire\Member\Learn;

use App\Models\Content;
use App\Models\LearningModule;
use App\Support\LearningProgress;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A learning module with the member's progress (LRN-REQ06).
 */
#[Layout('components.layouts.app')]
class Module extends Component
{
    public LearningModule $module;

    public function mount(LearningModule $module): void
    {
        $member = auth()->user();
        abort_unless($module->status === LearningModule::PUBLISHED && LearningModule::whereKey($module->id)->availableTo($member)->exists(), 404);

        $this->module = $module;
    }

    public function render(): mixed
    {
        $this->module->load('lessons', 'category');
        $progress = LearningProgress::forMember(auth()->user());
        $lessons = $this->module->lessons->filter(fn (Content $lesson) => $lesson->isLive() && $lesson->isFor(auth()->user()))->values();

        return view('livewire.member.learn.module', [
            'lessons' => $lessons,
            'progress' => $progress,
            'status' => LearningProgress::moduleStatus($this->module, $progress),
            'nextLesson' => $lessons->first(fn (Content $lesson) => $progress->get($lesson->content_id)?->status !== 'completed'),
        ])->title($this->module->title);
    }
}
