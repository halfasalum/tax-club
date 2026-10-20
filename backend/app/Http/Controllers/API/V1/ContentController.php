<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Content;
use App\Support\ContentWorkflow;
use App\Support\HtmlSanitizer;
use App\Support\LearningSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Learning content for the mobile app (LRN-REQ05, LRN-REQ07). Members only
 * see live content meant for their category; authors work through
 * drafts and review (LRN-REQ01-03).
 */
class ContentController extends BaseController
{
    /**
     * GET /api/v1/content?search=&category=&type=&level=
     */
    public function index(Request $request): JsonResponse
    {
        $page = LearningSearch::query($request->user(), $request->only('search', 'category', 'type', 'level'))
            ->with('category')
            ->paginate(min((int) ($request->per_page ?? 15), 50));

        $progress = $request->user()->progress()->whereIn('content_id', collect($page->items())->pluck('content_id'))->get()->keyBy('content_id');

        $page->getCollection()->transform(fn (Content $content) => $this->summary($content) + [
            'progress' => $progress->get($content->content_id)?->only(['status', 'progress_percent', 'completed_at']),
        ]);

        return $this->paginated($page);
    }

    /**
     * GET /api/v1/content/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $content = Content::with(['category', 'quiz'])->live()->availableTo($request->user())->find($id);

        if (! $content) {
            return $this->error('Content not found.', 404);
        }

        return $this->success([
            'content' => $this->summary($content) + [
                'objectives' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $content->objectives)))),
                'body_html' => HtmlSanitizer::clean($content->body_text),
                'file_url' => $content->mediaUrl() ?? $content->file_url,
                'embed_url' => $content->embedUrl(),
                'version' => $content->current_version,
                'quiz_id' => $content->quiz?->quiz_id,
            ],
            'progress' => $request->user()->progress()->where('content_id', $id)->first(),
        ]);
    }

    /**
     * POST /api/v1/content (upload_content) — creates a draft.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $content = ContentWorkflow::create($this->fields($validator->validated()), $request->user());

        return $this->success(['content_id' => $content->content_id, 'code' => $content->code], 'Draft created.', 201);
    }

    /**
     * PUT /api/v1/content/{id} (edit_content) — saves a new or open revision.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $content = Content::find($id);

        if (! $content) {
            return $this->error('Content not found.', 404);
        }

        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $revision = ContentWorkflow::saveDraft($content, $this->fields($validator->validated()), $request->user(), $request->input('change_note'));

        return $this->success(['version' => $revision->version, 'status' => $revision->status], 'Draft saved.');
    }

    /**
     * POST /api/v1/content/{id}/submit — sends the open revision for review.
     */
    public function submit(Request $request, string $id): JsonResponse
    {
        $content = Content::find($id);

        if (! $content) {
            return $this->error('Content not found.', 404);
        }

        try {
            ContentWorkflow::submit($content, $request->user(), $request->input('change_note'));
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(null, 'Sent for review.');
    }

    /**
     * DELETE /api/v1/content/{id} — only drafts that were never published.
     */
    public function destroy(string $id): JsonResponse
    {
        $content = Content::find($id);

        if (! $content) {
            return $this->error('Content not found.', 404);
        }

        if ($content->current_version !== null) {
            return $this->error('Published content cannot be deleted. Archive it instead.', 409);
        }

        $content->delete();

        return $this->success(null, 'Draft deleted.');
    }

    /**
     * PATCH /api/v1/content/{id}/toggle — replaced by review and publishing.
     */
    public function toggle(): JsonResponse
    {
        return $this->error('Publishing now goes through review. Use the Administration Portal.', 410);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Content $content): array
    {
        return [
            'content_id' => $content->content_id,
            'code' => $content->code,
            'slug' => $content->slug,
            'title' => $content->title,
            'summary' => $content->summary,
            'content_type' => $content->content_type,
            'level' => $content->level,
            'language' => $content->language,
            'category' => $content->category ? ['id' => $content->category->id, 'name' => $content->category->name()] : null,
            'keywords' => $content->keywordList(),
            'estimated_minutes' => $content->estimated_minutes,
            'published_at' => $content->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'title' => 'required|string|max:300',
            'summary' => 'nullable|string|max:500',
            'content_type' => 'required|in:'.implode(',', Content::TYPES),
            'category_id' => 'nullable|exists:content_categories,id',
            'level' => 'nullable|in:'.implode(',', Content::LEVELS),
            'language' => 'nullable|in:'.implode(',', Content::LANGUAGES),
            'body' => 'nullable|string',
            'objectives' => 'nullable|string',
            'file_url' => 'nullable|url',
            'keywords' => 'nullable|string|max:300',
            'audience' => 'nullable|array',
            'audience.*' => 'in:'.implode(',', Content::AUDIENCES),
            'estimated_minutes' => 'nullable|integer|min:1|max:600',
            'is_public' => 'sometimes|boolean',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        return [
            ...$data,
            'level' => $data['level'] ?? 'beginner',
            'language' => $data['language'] ?? 'sw',
            'body_markdown' => $data['body'] ?? null,
            'body_text' => isset($data['body']) ? HtmlSanitizer::clean(Str::markdown($data['body'], ['html_input' => 'allow', 'allow_unsafe_links' => false])) : null,
            'is_public' => (bool) ($data['is_public'] ?? false),
        ];
    }
}
