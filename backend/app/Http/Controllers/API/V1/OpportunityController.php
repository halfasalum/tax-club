<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Opportunity;
use App\Models\User;
use App\Support\Audience;
use App\Support\OpportunityBoard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Opportunities for the mobile app (OPP-REQ01-07).
 */
class OpportunityController extends BaseController
{
    /**
     * GET /api/v1/opportunities?search=&type=&closing_soon=1&saved=1
     * Members see open opportunities meant for them. Officers (add or edit
     * opportunities) may pass ?all=1.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->boolean('all') && ($user->hasControl('add_opportunity') || $user->hasControl('edit_opportunity'))) {
            return $this->paginated(Opportunity::latest('updated_at')->paginate(min(50, (int) ($request->per_page ?? 15))));
        }

        $items = $request->boolean('saved')
            ? OpportunityBoard::savedBy($user)
            : OpportunityBoard::browse($user, (string) $request->search, (string) $request->type, $request->boolean('closing_soon'));

        return $this->success($items->map(fn (Opportunity $opportunity) => $this->present($opportunity, $user)));
    }

    /**
     * GET /api/v1/opportunities/career-resources
     */
    public function careerResources(Request $request): JsonResponse
    {
        return $this->success(OpportunityBoard::careerResources($request->user(), 20)->map(fn ($lesson) => [
            'content_id' => $lesson->content_id,
            'slug' => $lesson->slug,
            'title' => $lesson->title,
            'summary' => $lesson->summary,
        ]));
    }

    /**
     * GET /api/v1/opportunities/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $opportunity = Opportunity::find($id) ?? Opportunity::where('slug', $id)->first();

        if (! $opportunity || ! ($opportunity->isVisibleTo($user) || OpportunityBoard::isSaved($opportunity, $user))) {
            return $this->error('Opportunity not found.', 404);
        }

        OpportunityBoard::record($opportunity, $user, 'view');

        return $this->success([
            ...$this->present($opportunity, $user),
            'description' => $opportunity->description,
            'eligibility' => $opportunity->eligibility,
            'apply_instructions' => $opportunity->apply_instructions,
            'attachment_url' => $opportunity->attachment_path ? Storage::disk(OpportunityBoard::ATTACHMENT_DISK)->url($opportunity->attachment_path) : null,
        ]);
    }

    /**
     * POST /api/v1/opportunities/{id}/save and DELETE to unsave.
     */
    public function save(Request $request, string $id): JsonResponse
    {
        $opportunity = Opportunity::find($id);

        if (! $opportunity) {
            return $this->error('Opportunity not found.', 404);
        }

        try {
            $request->isMethod('delete') ? OpportunityBoard::unsave($opportunity, $request->user()) : OpportunityBoard::save($opportunity, $request->user());
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(['saved' => OpportunityBoard::isSaved($opportunity, $request->user())]);
    }

    /**
     * POST /api/v1/opportunities/{id}/apply — records the click and
     * returns where to apply.
     */
    public function apply(Request $request, string $id): JsonResponse
    {
        $opportunity = Opportunity::open()->find($id);

        if (! $opportunity || ! $opportunity->isVisibleTo($request->user()) || ! $opportunity->applyTarget()) {
            return $this->error('Opportunity not open.', 404);
        }

        OpportunityBoard::record($opportunity, $request->user(), 'apply');

        return $this->success(['apply_to' => $opportunity->applyTarget()]);
    }

    /**
     * POST /api/v1/opportunities — create a draft. Requires: add_opportunity
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $opportunity = OpportunityBoard::create($validator->validated(), $request->user());

        if ($request->boolean('submit')) {
            try {
                OpportunityBoard::submit($opportunity, $request->user());
            } catch (InvalidArgumentException $exception) {
                return $this->error($exception->getMessage(), 422);
            }
        }

        return $this->success($opportunity->fresh(), 'Opportunity created.', 201);
    }

    /**
     * PUT /api/v1/opportunities/{id}. Requires: edit_opportunity
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $opportunity = Opportunity::find($id);

        if (! $opportunity) {
            return $this->error('Opportunity not found.', 404);
        }

        $validator = Validator::make($request->all(), $this->rules(partial: true));

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        try {
            OpportunityBoard::update($opportunity, $validator->validated(), $request->user());
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success($opportunity->fresh(), 'Opportunity updated.');
    }

    /**
     * DELETE /api/v1/opportunities/{id}. Only unpublished drafts; live ones
     * are withdrawn instead. Requires: delete_opportunity
     */
    public function destroy(string $id): JsonResponse
    {
        $opportunity = Opportunity::find($id);

        if (! $opportunity) {
            return $this->error('Opportunity not found.', 404);
        }

        if ($opportunity->publication_status !== Opportunity::NOT_PUBLISHED) {
            return $this->error('Published opportunities cannot be deleted. Withdraw or archive it instead.', 422);
        }

        $opportunity->delete();

        return $this->success(null, 'Opportunity deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Opportunity $opportunity, User $user): array
    {
        return [
            'opportunity_id' => $opportunity->opportunity_id,
            'code' => $opportunity->code,
            'slug' => $opportunity->slug,
            'title' => $opportunity->title,
            'organization' => $opportunity->organization,
            'type' => $opportunity->type,
            'location' => $opportunity->location,
            'region' => $opportunity->region,
            'work_mode' => $opportunity->work_mode,
            'deadline' => $opportunity->deadline?->toDateString(),
            'is_open' => $opportunity->isOpen(),
            'closing_soon' => $opportunity->isClosingSoon(),
            'apply_method' => $opportunity->apply_method,
            'audience' => Audience::describe($opportunity->audience),
            'saved' => OpportunityBoard::isSaved($opportunity, $user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'title' => "{$required}|string|min:5|max:200",
            'organization' => "{$required}|string|max:200",
            'type' => "{$required}|in:".implode(',', Opportunity::TYPES),
            'description' => "{$required}|string|max:20000",
            'eligibility' => 'nullable|string|max:3000',
            'location' => 'nullable|string|max:200',
            'region' => 'nullable|in:'.implode(',', array_values(config('tanzania.regions'))),
            'work_mode' => 'nullable|in:'.implode(',', Opportunity::WORK_MODES),
            'audience' => 'nullable|array',
            'deadline' => 'nullable|date',
            'apply_method' => 'sometimes|in:'.implode(',', Opportunity::APPLY_METHODS),
            'apply_url' => 'nullable|url|max:500',
            'apply_email' => 'nullable|email|max:200',
            'apply_instructions' => 'nullable|string|max:3000',
        ];
    }
}
