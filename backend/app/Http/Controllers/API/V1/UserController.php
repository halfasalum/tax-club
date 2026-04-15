<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UserController extends BaseController
{
    /**
     * GET /api/v1/users
     * List all users with filters. Requires: view_users
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['institution', 'roles'])
            ->when($request->user_type, fn($q) => $q->where('user_type', $request->user_type))
            ->when($request->institution_id, fn($q) => $q->where('institution_id', $request->institution_id))
            ->when($request->is_verified !== null, fn($q) => $q->where('is_verified', filter_var($request->is_verified, FILTER_VALIDATE_BOOLEAN)))
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('full_name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('membership_id', 'like', "%{$request->search}%");
            }))
            ->orderBy('created_at', 'desc');

        return $this->paginated($query->paginate($request->per_page ?? 15));
    }

    /**
     * GET /api/v1/users/{id}
     * Show a single user. Requires: view_users
     */
    public function show(string $id): JsonResponse
    {
        $user = User::with(['institution', 'roles.controls', 'quizAttempts', 'progress'])
                    ->find($id);

        if (! $user) {
            return $this->error('User not found.', 404);
        }

        return $this->success([
            'user'        => $user,
            'permissions' => $user->allControls(),
        ]);
    }

    /**
     * POST /api/v1/users
     * Admin creates a user manually. Requires: add_user
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'full_name'      => 'required|string|max:150',
            'email'          => 'required|email|unique:users,email',
            'phone'          => 'nullable|string|max:20|unique:users,phone',
            'password'       => 'required|string|min:8',
            'user_type'      => 'required|in:secondary,college,university,alumni',
            'institution_id' => 'nullable|uuid|exists:institutions,institution_id',
            'role_ids'       => 'nullable|array',
            'role_ids.*'     => 'nullable|uuid|exists:roles,role_id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $user = User::create([
            'full_name'      => $request->full_name,
            'email'          => $request->email,
            'phone'          => $request->phone,
            'password_hash'  => Hash::make($request->password),
            'user_type'      => $request->user_type,
            'institution_id' => $request->institution_id,
            'membership_id'  => strtoupper('TRA-' . Str::random(8)),
            'is_verified'    => true, // admin-created users are pre-verified
        ]);

        // Assign specified roles or fall back to default
        $roleIds = $request->role_ids ?? [];
        if (empty($roleIds)) {
            $default = Role::getDefault();
            if ($default) $roleIds = [$default->role_id];
        }

        foreach ($roleIds as $roleId) {
            $user->roles()->attach($roleId, [
                'assigned_by' => $request->user()->user_id,
                'assigned_at' => now(),
            ]);
        }

        return $this->success($user->load('roles'), 'User created successfully.', 201);
    }

    /**
     * PUT /api/v1/users/{id}
     * Update a user. Requires: edit_user
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return $this->error('User not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'full_name'      => 'sometimes|string|max:150',
            'phone'          => "sometimes|string|max:20|unique:users,phone,{$user->user_id},user_id",
            'user_type'      => 'sometimes|in:secondary,college,university,alumni',
            'institution_id' => 'nullable|uuid|exists:institutions,institution_id',
            'is_verified'    => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $user->fill($request->only(['full_name', 'phone', 'user_type', 'institution_id', 'is_verified']));
        $user->save();

        return $this->success($user->fresh('institution', 'roles'), 'User updated successfully.');
    }

    /**
     * DELETE /api/v1/users/{id}
     * Soft-delete a user (revoke tokens + deactivate). Requires: delete_user
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return $this->error('User not found.', 404);
        }

        if ($user->user_id === $request->user()->user_id) {
            return $this->error('You cannot delete your own account.', 403);
        }

        // Revoke all tokens before deleting
        $user->tokens()->delete();
        $user->delete();

        return $this->success(null, 'User deleted successfully.');
    }

    /**
     * POST /api/v1/users/{id}/roles
     * Assign roles to a user. Requires: assign_role
     */
    public function assignRoles(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return $this->error('User not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'role_ids'   => 'required|array|min:1',
            'role_ids.*' => 'uuid|exists:roles,role_id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $syncData = collect($request->role_ids)->mapWithKeys(fn($rid) => [
            $rid => ['assigned_by' => $request->user()->user_id, 'assigned_at' => now()],
        ])->toArray();

        $user->roles()->syncWithoutDetaching($syncData);

        return $this->success($user->load('roles'), 'Roles assigned successfully.');
    }

    /**
     * DELETE /api/v1/users/{id}/roles
     * Remove roles from a user. Requires: assign_role
     */
    public function removeRoles(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return $this->error('User not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'role_ids'   => 'required|array|min:1',
            'role_ids.*' => 'uuid|exists:roles,role_id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $user->roles()->detach($request->role_ids);

        return $this->success($user->load('roles'), 'Roles removed successfully.');
    }
}