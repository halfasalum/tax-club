<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Institution;
use App\Support\InstitutionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class InstitutionController extends BaseController
{
    /**
     * Fields safe to show without signing in (no contact details).
     *
     * @var list<string>
     */
    private const PUBLIC_FIELDS = ['institution_id', 'code', 'name', 'type', 'region', 'district'];

    /**
     * GET /api/v1/institutions
     * Public — used during registration to populate dropdown.
     */
    public function index(Request $request): JsonResponse
    {
        $institutions = Institution::query()
            ->active()
            ->select(self::PUBLIC_FIELDS)
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->region, fn ($q) => $q->where('region', $request->region))
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('name')
            ->paginate($request->per_page ?? 20);

        return $this->paginated($institutions);
    }

    /**
     * GET /api/v1/institutions/{id}
     */
    public function show(string $id): JsonResponse
    {
        $institution = Institution::active()->select(self::PUBLIC_FIELDS)->find($id);

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
        $validator = Validator::make($request->all(), InstitutionRegistry::rules());

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        try {
            $institution = InstitutionRegistry::create($validator->validated(), $request->user());
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 409);
        }

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

        $validator = Validator::make([...$institution->only(['name', 'type', 'region', 'district', 'contact_name', 'contact_phone', 'contact_email']), ...$request->all()], InstitutionRegistry::rules());

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        try {
            $institution = InstitutionRegistry::update($institution, $validator->validated(), $request->user());
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 409);
        }

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
