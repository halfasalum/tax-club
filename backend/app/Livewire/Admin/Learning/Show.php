<?php

namespace App\Livewire\Admin\Learning;

use App\Models\Content;
use App\Models\ContentCategory;
use App\Models\ContentVersion;
use App\Models\EditorialEvent;
use App\Support\ContentWorkflow;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Preview, review and publish a lesson (LRN-REQ02, LRN-REQ03, LRN-REQ08).
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public Content $content;

    public string $reviewNote = '';

    public string $publishAt = '';

    public string $expiresAt = '';

    public string $offlineReason = '';

    public ?string $statusMessage = null;

    /** Which revision to preview: the working one, or the live one. */
    public string $preview = 'working';

    public function mount(Content $content): void
    {
        $this->content = $content;
        $this->statusMessage = session('status');
    }

    public function submit(): void
    {
        $this->authorizeAny(['upload_content', 'edit_content']);
        $this->attempt(fn () => ContentWorkflow::submit($this->content, auth()->user()), __('Sent for review.'));
    }

    public function approve(): void
    {
        $this->authorizeAny(['review_content']);
        $this->attempt(fn () => ContentWorkflow::approve($this->content, auth()->user(), trim($this->reviewNote) ?: null), __('Approved. It can now be published.'));
    }

    public function returnForChanges(): void
    {
        $this->authorizeAny(['review_content']);
        $this->attempt(fn () => ContentWorkflow::returnForChanges($this->content, auth()->user(), trim($this->reviewNote)), __('Returned to the author with your notes.'));
    }

    public function reject(): void
    {
        $this->authorizeAny(['review_content']);
        $this->attempt(fn () => ContentWorkflow::reject($this->content, auth()->user(), trim($this->reviewNote)), __('Rejected.'));
    }

    public function publish(): void
    {
        $this->authorizeAny(['publish_content']);
        $this->validate([
            'publishAt' => 'nullable|date',
            'expiresAt' => 'nullable|date',
        ]);

        $this->attempt(fn () => ContentWorkflow::publish(
            $this->content,
            auth()->user(),
            $this->publishAt ? Carbon::parse($this->publishAt) : null,
            $this->expiresAt ? Carbon::parse($this->expiresAt) : null,
        ), $this->publishAt && Carbon::parse($this->publishAt)->isFuture() ? __('Scheduled.') : __('Published. Members can see it now.'));
    }

    public function unpublish(): void
    {
        $this->authorizeAny(['publish_content']);
        $this->attempt(fn () => ContentWorkflow::unpublish($this->content, auth()->user(), $this->offlineReason), __('Unpublished. Members no longer see it.'));
    }

    public function archive(): void
    {
        $this->authorizeAny(['publish_content']);
        $this->attempt(fn () => ContentWorkflow::archive($this->content, auth()->user(), $this->offlineReason), __('Archived.'));
    }

    private function attempt(callable $action, string $success): void
    {
        $this->resetErrorBag();

        try {
            $action();
        } catch (InvalidArgumentException $exception) {
            $this->addError('action', $exception->getMessage());

            return;
        }

        $this->reset('reviewNote', 'offlineReason', 'publishAt', 'expiresAt');
        $this->statusMessage = $success;
    }

    /**
     * @param  list<string>  $controls
     */
    private function authorizeAny(array $controls): void
    {
        abort_unless(collect($controls)->contains(fn ($control) => auth()->user()->hasControl($control)), 403);
    }

    public function render(): mixed
    {
        $this->content->refresh()->load('category');
        $revision = ContentWorkflow::openRevision($this->content);
        $user = auth()->user();

        $shown = $this->preview === 'live' || ! $revision ? ContentWorkflow::liveFields($this->content) : $revision->fields;
        $live = ContentWorkflow::liveFields($this->content);
        $changed = $revision && $this->content->current_version
            ? collect(Content::VERSIONED_FIELDS)->reject(fn ($field) => in_array($field, ['body_text'], true))->filter(fn ($field) => ($revision->fields[$field] ?? null) != ($live[$field] ?? null))->values()->all()
            : [];

        return view('livewire.admin.learning.show', [
            'revision' => $revision,
            'shown' => $shown,
            'shownCategory' => isset($shown['category_id']) ? ContentCategory::find($shown['category_id']) : null,
            'changed' => $changed,
            'versions' => $this->content->versions()->with(['author:user_id,full_name', 'reviewer:user_id,full_name'])->get(),
            'events' => EditorialEvent::where('subject_type', 'content')->where('subject_id', $this->content->content_id)->with('user:user_id,full_name')->latest('id')->limit(30)->get(),
            'can' => [
                'edit' => $user->hasControl('upload_content') || $user->hasControl('edit_content'),
                'review' => $user->hasControl('review_content'),
                'publish' => $user->hasControl('publish_content'),
            ],
            'ownSubmission' => $revision?->status === ContentVersion::IN_REVIEW && $revision->submitted_by === $user->user_id && ContentWorkflow::fourEyes(),
        ])->title($this->content->title);
    }
}
