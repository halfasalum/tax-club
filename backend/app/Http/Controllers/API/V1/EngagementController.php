<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Certificate;
use App\Models\Competition;
use App\Models\Mentorship;
use App\Models\Question;
use App\Models\RewardAward;
use App\Support\AlumniNetwork;
use App\Support\AskTra;
use App\Support\Certificates;
use App\Support\CompetitionDesk;
use App\Support\MentorshipDesk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Ask TRA, competitions, achievements, alumni and mentorship for the
 * mobile app (COM-REQ01-04, CMP-REQ03-05, RWD-REQ02, CER-REQ01/02,
 * ALM-REQ01-03, MNT-REQ02-05).
 */
class EngagementController extends BaseController
{
    // ─── Ask TRA ───────────────────────────────────────────────────

    public function questions(Request $request): JsonResponse
    {
        return $this->success(Question::where('user_id', $request->user()->user_id)->latest()->limit(50)->get()->map(fn (Question $question) => [
            'reference' => $question->reference, 'subject' => $question->subject, 'status' => $question->status,
            'answer' => $question->status === Question::ANSWERED ? $question->answer : null,
            'decline_reason' => $question->decline_reason, 'created_at' => $question->created_at?->toIso8601String(),
        ]));
    }

    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate(['subject' => 'required|string|min:8|max:200', 'body' => 'required|string|min:15|max:3000', 'category_id' => 'nullable|exists:content_categories,id', 'allow_publish' => 'sometimes|boolean']);

        try {
            $question = AskTra::ask($request->user(), $data);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 429);
        }

        return $this->success(['reference' => $question->reference], 'Sent to TRA.', 201);
    }

    public function answers(Request $request): JsonResponse
    {
        $answers = AskTra::knowledgeBase((string) $request->search, $request->integer('category_id') ?: null, $request->language);
        $answers->getCollection()->transform(fn (Question $question) => ['slug' => $question->slug, 'question' => $question->public_question, 'answer' => $question->public_answer, 'published_at' => $question->published_at?->toIso8601String()]);

        return $this->paginated($answers);
    }

    // ─── Competitions ──────────────────────────────────────────────

    public function competitions(Request $request): JsonResponse
    {
        $member = $request->user();

        return $this->success(Competition::live()->orderByDesc('starts_at')->limit(100)->get()
            ->filter(fn (Competition $competition) => $competition->isVisibleTo($member))
            ->values()
            ->map(fn (Competition $competition) => $this->presentCompetition($competition, $member)));
    }

    public function competition(Request $request, string $slug): JsonResponse
    {
        $competition = Competition::live()->where('slug', $slug)->first();

        if (! $competition || ! $competition->isVisibleTo($request->user())) {
            return $this->error('Competition not found.', 404);
        }

        return $this->success([
            ...$this->presentCompetition($competition, $request->user()),
            'description' => $competition->description,
            'rules' => $competition->rules,
            'results' => $competition->results_status === Competition::RESULTS_PUBLISHED
                ? CompetitionDesk::results($competition)->map(fn ($entry) => ['rank' => $entry->rank, 'name' => $entry->user?->publicName(), 'institution' => $entry->user?->institution?->name, 'score' => $entry->score])
                : [],
        ]);
    }

    public function registerForCompetition(Request $request, string $slug): JsonResponse
    {
        $competition = Competition::live()->where('slug', $slug)->first();

        if (! $competition) {
            return $this->error('Competition not found.', 404);
        }

        try {
            CompetitionDesk::register($competition, $request->user());
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(null, 'Registered.', 201);
    }

    public function submitCompetitionEntry(Request $request, string $slug): JsonResponse
    {
        $competition = Competition::live()->where('slug', $slug)->first();
        $request->validate(['text' => 'nullable|string|max:20000', 'url' => 'nullable|url|max:500', 'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240']);

        if (! $competition) {
            return $this->error('Competition not found.', 404);
        }

        try {
            CompetitionDesk::submitEntry($competition, $request->user(), $request->input('text'), $request->input('url'), $request->file('file'));
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(null, 'Entry received.');
    }

    // ─── Achievements ──────────────────────────────────────────────

    public function achievements(Request $request): JsonResponse
    {
        $member = $request->user();

        return $this->success([
            'total_points' => (int) $member->total_points,
            'certificates' => Certificate::where('user_id', $member->user_id)->latest('issued_at')->get()->map(fn (Certificate $certificate) => [
                'number' => $certificate->number, 'kind' => $certificate->kind, 'title' => $certificate->title(), 'subject' => $certificate->subject,
                'issued_at' => $certificate->issued_at->toIso8601String(), 'valid' => $certificate->isValid(), 'verify_url' => Certificates::verifyUrl($certificate),
            ]),
            'rewards' => RewardAward::where('user_id', $member->user_id)->where('status', '!=', RewardAward::PENDING_APPROVAL)->with('reward')->latest()->get()->map(fn (RewardAward $award) => [
                'reward' => $award->reward?->name(), 'reason' => $award->reason, 'status' => $award->status,
            ]),
        ]);
    }

    /**
     * POST /api/v1/certificates/verify {number or token} — public.
     */
    public function verifyCertificate(Request $request): JsonResponse
    {
        $request->validate(['number' => 'required_without:token|string|max:40', 'token' => 'required_without:number|string|size:32']);
        $certificate = Certificates::find((string) ($request->token ?? $request->number));

        if (! $certificate) {
            return $this->success(['result' => 'not_found']);
        }

        return $this->success([
            'result' => $certificate->isValid() ? 'valid' : 'revoked',
            'holder' => $certificate->user?->publicName(),
            'title' => $certificate->title(),
            'subject' => $certificate->subject,
            'issued_at' => $certificate->issued_at->toDateString(),
        ]);
    }

    // ─── Alumni and mentorship ─────────────────────────────────────

    public function alumniDirectory(Request $request): JsonResponse
    {
        try {
            $profiles = AlumniNetwork::directory($request->user(), (string) $request->search);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 403);
        }

        $profiles->getCollection()->transform(fn ($profile) => [
            'user_id' => $profile->user_id, 'name' => $profile->user?->full_name, 'occupation' => $profile->occupation, 'organization' => $profile->organization,
            'field' => $profile->field, 'institution' => $profile->user?->institution?->name, 'graduation_year' => $profile->graduation_year, 'open_to_mentoring' => $profile->open_to_mentoring,
        ]);

        return $this->paginated($profiles);
    }

    public function mentorships(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success(Mentorship::where(fn ($query) => $query->where('mentor_id', $user->user_id)->orWhere('mentee_id', $user->user_id))->with(['mentor:user_id,full_name', 'mentee:user_id,full_name'])->get()->map(fn (Mentorship $mentorship) => [
            'id' => $mentorship->id, 'role' => $mentorship->mentor_id === $user->user_id ? 'mentor' : 'mentee',
            'mentor' => $mentorship->mentor?->full_name, 'mentee' => $mentorship->mentee?->publicName(), 'status' => $mentorship->status,
            'started_at' => $mentorship->started_at?->toIso8601String(),
        ]));
    }

    public function requestMentor(Request $request): JsonResponse
    {
        $data = $request->validate(['interests' => 'required|array|min:1', 'goals' => 'required|string|min:20|max:2000', 'mode' => 'sometimes|in:online,in_person,either', 'guardian_consent' => 'sometimes|boolean']);

        try {
            MentorshipDesk::request($request->user(), $data);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(null, 'Request sent.', 201);
    }

    public function logMentorshipSession(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['held_on' => 'required|date', 'mode' => 'required|string', 'duration_minutes' => 'nullable|integer|min:5|max:480', 'topic' => 'required|string|max:200', 'summary' => 'nullable|string|max:2000', 'milestone' => 'sometimes|boolean']);
        $mentorship = Mentorship::find($id);

        if (! $mentorship || ! $mentorship->involves($request->user())) {
            return $this->error('Mentorship not found.', 404);
        }

        try {
            MentorshipDesk::logSession($mentorship, $request->user(), $data);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(null, 'Session recorded.', 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCompetition(Competition $competition, $member): array
    {
        $entry = CompetitionDesk::entryFor($competition, $member);

        return [
            'competition_id' => $competition->competition_id, 'slug' => $competition->slug, 'title' => $competition->title, 'type' => $competition->type,
            'submission_kind' => $competition->submission_kind, 'quiz_id' => $competition->quiz_id, 'status' => $competition->displayStatus(),
            'starts_at' => $competition->starts_at?->toIso8601String(), 'ends_at' => $competition->ends_at?->toIso8601String(),
            'registration_closes_at' => $competition->registration_closes_at?->toIso8601String(),
            'my_entry' => $entry ? ['status' => $entry->status, 'rank' => $competition->results_status === Competition::RESULTS_PUBLISHED ? $entry->rank : null] : null,
        ];
    }
}
