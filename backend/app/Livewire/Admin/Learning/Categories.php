<?php

namespace App\Livewire\Admin\Learning;

use App\Models\ContentCategory;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Learning categories (LRN-REQ04).
 */
#[Layout('components.layouts.app')]
class Categories extends Component
{
    public ?int $editingId = null;

    public string $name_sw = '';

    public string $name_en = '';

    public string $description = '';

    public int $sort_order = 0;

    public bool $is_active = true;

    public ?string $statusMessage = null;

    public function edit(int $id): void
    {
        $category = ContentCategory::findOrFail($id);
        $this->editingId = $id;
        $this->fill($category->only(['name_sw', 'name_en', 'sort_order', 'is_active']));
        $this->description = (string) $category->description;
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'name_sw', 'name_en', 'description', 'sort_order', 'is_active');
    }

    public function save(): void
    {
        $this->validate([
            'name_sw' => ['required', 'string', 'max:120', Rule::unique('content_categories', 'name_sw')->ignore($this->editingId)],
            'name_en' => ['required', 'string', 'max:120', Rule::unique('content_categories', 'name_en')->ignore($this->editingId)],
            'description' => 'nullable|string|max:500',
            'sort_order' => 'integer|min:0|max:999',
        ]);

        $values = [
            'name_sw' => trim($this->name_sw),
            'name_en' => trim($this->name_en),
            'description' => trim($this->description) ?: null,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            ContentCategory::findOrFail($this->editingId)->update($values);
        } else {
            ContentCategory::create($values + ['slug' => Str::slug($this->name_en)]);
        }

        $this->statusMessage = __('Category saved.');
        $this->cancel();
    }

    public function render(): mixed
    {
        return view('livewire.admin.learning.categories', [
            'categories' => ContentCategory::withCount('contents')->orderBy('sort_order')->orderBy('name_en')->get(),
        ])->title(__('Categories'));
    }
}
