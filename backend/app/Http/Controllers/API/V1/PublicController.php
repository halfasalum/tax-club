<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Content;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Question;
use App\Models\User;
use App\Support\AskTra;
use App\Support\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The public website's pages for the mobile app, so visitors can look
 * around before they sign in. Shows only what the website shows.
 */
class PublicController extends BaseController
{
    /**
     * GET /api/v1/public/home
     */
    public function home(): JsonResponse
    {
        return $this->success([
            'member_count' => User::where('account_status', User::STATUS_ACTIVE)->count(),
            'institution_count' => Institution::count(),
            'lessons' => Content::publiclyVisible()->latest('published_at')->limit(3)->get()->map(fn (Content $lesson) => $this->presentLesson($lesson)),
            'events' => Event::publiclyListed()->orderBy('starts_at')->limit(3)->get()->map(fn (Event $event) => $this->presentEvent($event)),
        ]);
    }

    /**
     * GET /api/v1/public/lessons?search=
     */
    public function lessons(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $page = Content::publiclyVisible()
            ->where(fn ($query) => $query->where('language', app()->getLocale())->orWhereNull('language'))
            ->when($search !== '', fn ($query) => $query->where('title', 'like', '%'.$search.'%'))
            ->latest('published_at')
            ->paginate(12);

        $page->getCollection()->transform(fn (Content $lesson) => $this->presentLesson($lesson));

        return $this->paginated($page);
    }

    /**
     * GET /api/v1/public/lessons/{slug}
     */
    public function lesson(string $slug): JsonResponse
    {
        $content = Content::publiclyVisible()->where('slug', $slug)->first();

        if (! $content) {
            return $this->error('Content not found.', 404);
        }

        return $this->success($this->presentLesson($content) + [
            'body_html' => HtmlSanitizer::clean($content->body_text),
            'file_url' => $content->mediaUrl() ?? $content->file_url,
            'embed_url' => $content->embedUrl(),
        ]);
    }

    /**
     * GET /api/v1/public/events
     */
    public function events(): JsonResponse
    {
        $page = Event::publiclyListed()->orderBy('starts_at')->paginate(12);
        $page->getCollection()->transform(fn (Event $event) => $this->presentEvent($event));

        return $this->paginated($page);
    }

    /**
     * GET /api/v1/public/events/{slug}
     */
    public function event(string $slug): JsonResponse
    {
        $event = Event::live()->where('is_public', true)->where('slug', $slug)->first();

        if (! $event) {
            return $this->error('Event not found.', 404);
        }

        return $this->success($this->presentEvent($event) + ['description' => $event->description]);
    }

    /**
     * GET /api/v1/public/answers?search=
     */
    public function answers(Request $request): JsonResponse
    {
        $page = AskTra::knowledgeBase((string) $request->query('search'), perPage: 12);
        $page->getCollection()->transform(fn (Question $question) => [
            'slug' => $question->slug,
            'question' => $question->public_question,
            'answer' => $question->public_answer,
            'published_at' => $question->published_at?->toIso8601String(),
        ]);

        return $this->paginated($page);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentLesson(Content $lesson): array
    {
        return [
            'content_id' => $lesson->content_id,
            'slug' => $lesson->slug,
            'title' => $lesson->title,
            'summary' => $lesson->summary,
            'content_type' => $lesson->content_type,
            'content_type_label' => __('content_type.'.$lesson->content_type),
            'estimated_minutes' => $lesson->estimated_minutes,
            'published_at' => $lesson->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentEvent(Event $event): array
    {
        return [
            'event_id' => $event->event_id,
            'slug' => $event->slug,
            'title' => $event->title,
            'location' => $event->location,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
        ];
    }
}
