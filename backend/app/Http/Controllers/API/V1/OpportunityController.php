<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Opportunity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OpportunityController extends BaseController
{
    /**
     * GET /api/v1/opportunities
     * Students see active/open listings. Admins see all.
     * Requires: auth:sanctum
     */
    public function index(Request $request): JsonResponse
    {
        $user    = $request->user();
        $isAdmin = $user->hasAnyRole(['admin', 'super_admin']);

        $query = Opportunity::with('postedBy:user_id,full_name')
            ->when(! $isAdmin, fn($q) => $q->open())
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%")
                  ->orWhere('organization', 'like', "%{$request->search}%");
            }))
            ->orderBy('created_at', 'desc');

        return $this->paginated($query->paginate($request->per_page ?? 15));
    }

    /**
     * GET /api/v1/opportunities/{id}
     * Requires: auth:sanctum
     */
    public function show(string $id): JsonResponse
    {
        $opportunity = Opportunity::with('postedBy:user_id,full_name')->find($id);

        if (! $opportunity) {
            return $this->error('Opportunity not found.', 404);
        }

        return $this->success($opportunity);
    }

    /**
     * POST /api/v1/opportunities
     * Requires: add_opportunity
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'        => 'required|string|max:200',
            'organization' => 'required|string|max:200',
            'type'         => 'required|in:job,internship,volunteer',
            'description'  => 'nullable|string',
            'deadline'     => 'nullable|date|after_or_equal:today',
            'is_active'    => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $opportunity = Opportunity::create([
            ...$request->only(['title', 'organization', 'type', 'description', 'deadline']),
            'posted_by' => $request->user()->user_id,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->success($opportunity, 'Opportunity posted successfully.', 201);
    }

    /**
     * PUT /api/v1/opportunities/{id}
     * Requires: edit_opportunity
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $opportunity = Opportunity::find($id);

        if (! $opportunity) {
            return $this->error('Opportunity not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'title'        => 'sometimes|string|max:200',
            'organization' => 'sometimes|string|max:200',
            'type'         => 'sometimes|in:job,internship,volunteer',
            'description'  => 'nullable|string',
            'deadline'     => 'nullable|date',
            'is_active'    => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $opportunity->fill($request->only(['title', 'organization', 'type', 'description', 'deadline', 'is_active']))->save();

        return $this->success($opportunity, 'Opportunity updated successfully.');
    }

    /**
     * DELETE /api/v1/opportunities/{id}
     * Requires: delete_opportunity
     */
    public function destroy(string $id): JsonResponse
    {
        $opportunity = Opportunity::find($id);

        if (! $opportunity) {
            return $this->error('Opportunity not found.', 404);
        }

        $opportunity->delete();

        return $this->success(null, 'Opportunity deleted successfully.');
    }
}