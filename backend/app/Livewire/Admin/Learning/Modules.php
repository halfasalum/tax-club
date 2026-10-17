<?php

namespace App\Livewire\Admin\Learning;

use App\Models\Content;
use App\Models\ContentCategory;
use App\Models\LearningModule;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Group published lessons into ordered modules (LRN-REQ06). Lessons are
 * reviewed individually, so modules are managed by publishers directly.
 */
#[Layout('components.layouts.app')]
class Modules extends Component
{
    public ?int $editingId = null;

    public bool $editing = false;

    public string $title = '';

    public string $summary = '';

    public ?string $category_id = null;

    public string $level = 'beginner';

    /** @var list<string> */
    public array $audience = [];

    /**
     * Selected lessons in order: [{content_id, is_required}].
     *
     * @var list<array{content_id: string, is_required: bool}>
     */
    public array $items = [];

    public string $addLesson = '';

    public ?string $statusMessage = null;

    public function create(): void
    {
        $this->authorizePublish();
        $this->resetForm();
        $this->editing = true;
    }

    public function edit(int $id): void
    {
        $this->authorizePublish();
        $module = LearningModule::with('lessons')->findOrFail($id);

        $this->editingId = $id;
        $this->editing = true;
        $this->title = $module->title;
        $this->summary = (string) $module->summary;
        $this->category_id = $module->category_id ? (string) $module->category_id : null;
        $this->level = $module->level;
        $this->audience = $module->audience ?? [];
        $this->items = $module->lessons->map(fn (Content $lesson) => ['content_id' => $lesson->content_id, 'is_required' => (bool) $lesson->pivot->is_required])->all();
    }

    public function updatedAddLesson(string $contentId): void
    {
        if ($contentId !== '' && ! collect($this->items)->contains('content_id', $contentId)) {
            $this->items[] = ['content_id' => $contentId, 'is_required' => true];
        }

        $this->addLesson = '';
    }

    public function move(int $index, int $direction): void
    {
        $target = $index + $direction;

        if (isset($this->items[$index], $this->items[$target])) {
            [$this->items[$index], $this->items[$target]] = [$this->items[$target], $this->items[$index]];
        }
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(bool $publish = false): void
    {
        $this->authorizePublish();
        $this->validate([
            'title' => 'required|string|min:3|max:200',
            'summary' => 'nullable|string|max:1000',
            'category_id' => 'nullable|exists:content_categories,id',
            'level' => 'required|in:'.implode(',', Content::LEVELS),
            'audience.*' => 'in:'.implode(',', Content::AUDIENCES),
            'items' => $publish ? 'array|min:1' : 'array',
            'items.*.content_id' => 'exists:content,content_id',
        ], ['items.min' => __('Add at least one lesson before publishing.')]);

        $module = $this->editingId ? LearningModule::findOrFail($this->editingId) : new LearningModule(['created_by' => auth()->id()]);
        $module->fill([
            'title' => trim($this->title),
            'summary' => trim($this->summary) ?: null,
            'category_id' => $this->category_id ?: null,
            'level' => $this->level,
            'audience' => $this->audience ?: null,
        ]);

        if ($publish) {
            $module->status = LearningModule::PUBLISHED;
            $module->published_at ??= now();
        }

        $module->save();
        $module->lessons()->sync(collect($this->items)->values()->mapWithKeys(fn (array $item, int $position) => [
            $item['content_id'] => ['position' => $position, 'is_required' => (bool) $item['is_required']],
        ])->all());

        $this->statusMessage = $publish ? __('Module published.') : __('Module saved.');
        $this->resetForm();
    }

    public function setStatus(int $id, string $status): void
    {
        $this->authorizePublish();
        abort_unless(in_array($status, [LearningModule::DRAFT, LearningModule::PUBLISHED, LearningModule::ARCHIVED], true), 400);

        LearningModule::findOrFail($id)->update(['status' => $status]);
        $this->statusMessage = __('Module updated.');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    /**
     * Lessons that may go into a module: live or approved, not archived.
     *
     * @return Collection<int, Content>
     */
    #[Computed]
    public function availableLessons(): Collection
    {
        return Content::whereNotNull('current_version')->where('publication_status', '!=', Content::ARCHIVED)->orderBy('title')->get(['content_id', 'title', 'code', 'publication_status']);
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'editing', 'title', 'summary', 'category_id', 'level', 'audience', 'items', 'addLesson');
    }

    private function authorizePublish(): void
    {
        abort_unless(auth()->user()->hasControl('publish_content'), 403);
    }

    public function render(): mixed
    {
        return view('livewire.admin.learning.modules', [
            'modules' => LearningModule::withCount('lessons')->with('category')->orderBy('sort_order')->latest('updated_at')->get(),
            'categories' => ContentCategory::active()->get(),
            'lessonTitles' => $this->availableLessons->pluck('title', 'content_id'),
            'canPublish' => auth()->user()->hasControl('publish_content'),
        ])->title(__('Modules'));
    }
}
