<?php

namespace App\Livewire\Admin\Learning;

use App\Models\Content;
use App\Models\ContentCategory;
use App\Support\ContentWorkflow;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Write or change a lesson (LRN-REQ01, LRN-REQ04, LRN-REQ08). Changes are
 * saved to a revision; live content only changes when it is published.
 */
#[Layout('components.layouts.app')]
class Form extends Component
{
    use WithFileUploads;

    public ?Content $content = null;

    public string $title = '';

    public string $summary = '';

    public string $content_type = 'article';

    public ?string $category_id = null;

    public string $level = 'beginner';

    public string $language = 'sw';

    public string $objectives = '';

    public string $body_markdown = '';

    public string $file_url = '';

    public ?string $media_path = null;

    /** @var TemporaryUploadedFile|null */
    public $media = null;

    public string $keywords = '';

    /** @var list<string> */
    public array $audience = [];

    public ?int $estimated_minutes = null;

    public bool $is_public = false;

    public string $change_note = '';

    public function mount(?Content $content = null): void
    {
        abort_unless(auth()->user()->hasControl('upload_content') || auth()->user()->hasControl('edit_content'), 403);

        if (! $content?->exists) {
            return;
        }

        $this->content = $content;
        $revision = ContentWorkflow::openRevision($content);
        $fields = $revision?->fields ?? ContentWorkflow::liveFields($content);

        foreach (['title', 'summary', 'content_type', 'level', 'language', 'objectives', 'file_url', 'keywords'] as $field) {
            $this->{$field} = (string) ($fields[$field] ?? $this->{$field});
        }

        $this->category_id = isset($fields['category_id']) ? (string) $fields['category_id'] : null;
        $this->body_markdown = (string) ($fields['body_markdown'] ?? $fields['body_text'] ?? '');
        $this->media_path = $fields['media_path'] ?? null;
        $this->audience = $fields['audience'] ?? [];
        $this->estimated_minutes = $fields['estimated_minutes'] ?? null;
        $this->is_public = (bool) ($fields['is_public'] ?? false);
        $this->change_note = $revision?->change_note ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'title' => 'required|string|min:5|max:300',
            'summary' => 'nullable|string|max:500',
            'content_type' => 'required|in:'.implode(',', Content::TYPES),
            'category_id' => 'nullable|exists:content_categories,id',
            'level' => 'required|in:'.implode(',', Content::LEVELS),
            'language' => 'required|in:'.implode(',', Content::LANGUAGES),
            'objectives' => 'nullable|string|max:2000',
            'body_markdown' => 'nullable|string|max:100000',
            'file_url' => 'nullable|url|max:500',
            'media' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'keywords' => 'nullable|string|max:300',
            'audience' => 'array',
            'audience.*' => 'in:'.implode(',', Content::AUDIENCES),
            'estimated_minutes' => 'nullable|integer|min:1|max:600',
            'change_note' => 'nullable|string|max:1000',
        ];
    }

    public function removeMedia(): void
    {
        $this->media_path = null;
        $this->media = null;
    }

    public function save(bool $submit = false): mixed
    {
        $this->validate();

        if ($this->media) {
            $this->media_path = $this->media->storePubliclyAs('learning', Str::slug(Str::limit($this->title, 40, '')).'-'.Str::lower(Str::random(6)).'.'.$this->media->extension(), 'public');
            $this->media = null;
        }

        $fields = [
            'title' => trim($this->title),
            'summary' => trim($this->summary) ?: null,
            'content_type' => $this->content_type,
            'category_id' => $this->category_id ? (int) $this->category_id : null,
            'level' => $this->level,
            'language' => $this->language,
            'objectives' => trim($this->objectives) ?: null,
            'body_markdown' => $this->body_markdown ?: null,
            'body_text' => $this->body_markdown ? HtmlSanitizer::clean(Str::markdown($this->body_markdown, ['html_input' => 'allow', 'allow_unsafe_links' => false])) : null,
            'file_url' => trim($this->file_url) ?: null,
            'media_path' => $this->media_path,
            'keywords' => collect(explode(',', $this->keywords))->map(fn ($word) => trim($word))->filter()->unique()->implode(', ') ?: null,
            'audience' => $this->audience,
            'estimated_minutes' => $this->estimated_minutes ?: null,
            'is_public' => $this->is_public,
        ];

        $user = auth()->user();

        if ($this->content) {
            ContentWorkflow::saveDraft($this->content, $fields, $user, trim($this->change_note) ?: null);
        } else {
            $this->content = ContentWorkflow::create($fields, $user);
        }

        if ($submit) {
            try {
                ContentWorkflow::submit($this->content, $user, trim($this->change_note) ?: null);
            } catch (InvalidArgumentException $exception) {
                $this->addError('submit', $exception->getMessage());

                return null;
            }

            session()->flash('status', __('Sent for review.'));
        } else {
            session()->flash('status', __('Draft saved.'));
        }

        return $this->redirectRoute('admin.learning.show', $this->content, navigate: true);
    }

    public function render(): mixed
    {
        return view('livewire.admin.learning.form', [
            'categories' => ContentCategory::active()->get(),
            'isRevision' => $this->content?->current_version !== null,
        ])->title($this->content ? __('Edit lesson') : __('New lesson'));
    }
}
