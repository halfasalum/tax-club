<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RoleController extends BaseController
{
    /**
     * GET /api/v1/roles
     * Requires: view_roles
     */
    public function index(Request $request): JsonResponse
    {
        $roles = Role::with(['controls.module'])
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('name')
            ->paginate($request->per_page ?? 20);

        return $this->paginated($roles);
    }

    /**
     * GET /api/v1/roles/{id}
     * Requires: view_roles
     */
    public function show(string $id): JsonResponse
    {
        $role = Role::with(['controls.module', 'creator'])->find($id);

        if (! $role) {
            return $this->error('Role not found.', 404);
        }

        return $this->success($role);
    }

    /**
     * POST /api/v1/roles
     * Requires: add_role
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:100|unique:roles,name',
            'description' => 'nullable|string',
            'is_default'  => 'sometimes|boolean',
            'is_active'   => 'sometimes|boolean',
            'control_ids' => 'nullable|array',
            'control_ids.*' => 'uuid|exists:module_controls,control_id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        DB::beginTransaction();

        try {
            // If this role is set as default, unset all others first
            if ($request->boolean('is_default')) {
                Role::where('is_default', true)->update(['is_default' => false]);
            }

            $role = Role::create([
                'name'        => $request->name,
                'slug'        => Str::slug($request->name, '_'),
                'description' => $request->description,
                'is_default'  => $request->boolean('is_default', false),
                'is_active'   => $request->boolean('is_active', true),
                'created_by'  => $request->user()->user_id,
            ]);

            // Attach controls if provided
            if ($request->filled('control_ids')) {
                $syncData = collect($request->control_ids)->mapWithKeys(fn($cid) => [
                    $cid => ['granted_by' => $request->user()->user_id],
                ])->toArray();

                $role->controls()->attach($syncData);
            }

            DB::commit();

            return $this->success($role->load('controls.module'), 'Role created successfully.', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create role: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/v1/roles/{id}
     * Requires: edit_role
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $role = Role::find($id);

        if (! $role) {
            return $this->error('Role not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'name'        => "sometimes|string|max:100|unique:roles,name,{$role->role_id},role_id",
            'description' => 'nullable|string',
            'is_default'  => 'sometimes|boolean',
            'is_active'   => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        DB::beginTransaction();

        try {
            if ($request->boolean('is_default')) {
                Role::where('is_default', true)->where('role_id', '!=', $id)->update(['is_default' => false]);
            }

            if ($request->has('name')) {
                $role->slug = Str::slug($request->name, '_');
            }

            $role->fill($request->only(['name', 'description', 'is_default', 'is_active']))->save();

            DB::commit();

            return $this->success($role->load('controls.module'), 'Role updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update role: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/v1/roles/{id}
     * Requires: delete_role
     */
    public function destroy(string $id): JsonResponse
    {
        $role = Role::withCount('users')->find($id);

        if (! $role) {
            return $this->error('Role not found.', 404);
        }

        if ($role->is_default) {
            return $this->error('Cannot delete the default role. Set another role as default first.', 409);
        }

        if ($role->users_count > 0) {
            return $this->error("Cannot delete role assigned to {$role->users_count} user(s).", 409);
        }

        $role->delete();

        return $this->success(null, 'Role deleted successfully.');
    }

    /**
     * POST /api/v1/roles/{id}/controls
     * Assign module controls to a role. Requires: edit_role
     */
    public function assignControls(Request $request, string $id): JsonResponse
    {
        $role = Role::find($id);

        if (! $role) {
            return $this->error('Role not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'control_ids'   => 'required|array|min:1',
            'control_ids.*' => 'uuid|exists:module_controls,control_id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $syncData = collect($request->control_ids)->mapWithKeys(fn($cid) => [
            $cid => ['granted_by' => $request->user()->user_id],
        ])->toArray();

        $role->controls()->syncWithoutDetaching($syncData);

        return $this->success($role->load('controls.module'), 'Controls assigned to role successfully.');
    }

    /**
     * DELETE /api/v1/roles/{id}/controls
     * Remove controls from a role. Requires: edit_role
     */
    public function removeControls(Request $request, string $id): JsonResponse
    {
        $role = Role::find($id);

        if (! $role) {
            return $this->error('Role not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'control_ids'   => 'required|array|min:1',
            'control_ids.*' => 'uuid|exists:module_controls,control_id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $role->controls()->detach($request->control_ids);

        return $this->success($role->load('controls.module'), 'Controls removed from role successfully.');
    }
}