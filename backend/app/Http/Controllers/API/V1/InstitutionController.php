<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Institution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InstitutionController extends BaseController
{
    /**
     * GET /api/v1/institutions
     * Public — used during registration to populate dropdown.
     */
    public function index(Request $request): JsonResponse
    {
        $institutions = Institution::query()
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->region, fn($q) => $q->where('region', $request->region))
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('name')
            ->paginate($request->per_page ?? 20);

        return $this->paginated($institutions);
    }

    /**
     * GET /api/v1/institutions/{id}
     */
    public function show(string $id): JsonResponse
    {
        $institution = Institution::withCount('users')->find($id);

        if (! $institution) {
            return $this->error('Institution not found.', 404);
        }

        return $this->success($institution);
    }

    /**
     * POST /api/v1/institutions
     * Requires: add_institution
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:200|unique:institutions,name',
            'type'     => 'required|in:secondary,college,university',
            'region'   => 'required|string|max:100',
            'district' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $institution = Institution::create($request->only(['name', 'type', 'region', 'district']));

        return $this->success($institution, 'Institution created successfully.', 201);
    }

    /**
     * PUT /api/v1/institutions/{id}
     * Requires: edit_institution
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $institution = Institution::find($id);

        if (! $institution) {
            return $this->error('Institution not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'name'     => "sometimes|string|max:200|unique:institutions,name,{$institution->institution_id},institution_id",
            'type'     => 'sometimes|in:secondary,college,university',
            'region'   => 'sometimes|string|max:100',
            'district' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $institution->fill($request->only(['name', 'type', 'region', 'district']))->save();

        return $this->success($institution, 'Institution updated successfully.');
    }

    /**
     * DELETE /api/v1/institutions/{id}
     * Requires: delete_institution
     */
    public function destroy(string $id): JsonResponse
    {
        $institution = Institution::withCount('users')->find($id);

        if (! $institution) {
            return $this->error('Institution not found.', 404);
        }

        if ($institution->users_count > 0) {
            return $this->error('Cannot delete institution with existing members.', 409);
        }

        $institution->delete();

        return $this->success(null, 'Institution deleted successfully.');
    }
}