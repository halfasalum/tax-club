<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends BaseController
{
    /**
     * POST /api/v1/auth/register
     * Register a new user. Auto-assigns the default role.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'full_name'      => 'required|string|max:150',
            'email'          => 'required|email|unique:users,email',
            'phone'          => 'nullable|string|max:20|unique:users,phone',
            'password'       => 'required|string|min:8|confirmed',
            'user_type'      => 'required|in:secondary,college,university,alumni',
            'institution_id' => 'nullable|uuid|exists:institutions,institution_id',
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
            'is_verified'    => false,
        ]);

        // Auto-assign default role
        $defaultRole = Role::getDefault();
        if ($defaultRole) {
            $user->roles()->attach($defaultRole->role_id, [
                'assigned_by' => null,
                'assigned_at' => now(),
            ]);
        }

        $token = $user->createToken('mobile_app')->plainTextToken;

        return $this->success([
            'user'  => $this->formatUser($user),
            'token' => $token,
        ], 'Registration successful.', 201);
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password_hash)) {
            return $this->error('Invalid credentials.', 401);
        }

        if (! $user->is_verified) {
            return $this->error('Your account is not yet verified. Please verify your email or phone.', 403);
        }

        // Update last login timestamp
        $user->last_login = now();
        $user->save();

        // Revoke previous tokens (single session) — remove this line for multi-device
        $user->tokens()->delete();

        $token = $user->createToken('mobile_app')->plainTextToken;

        return $this->success([
            'user'        => $this->formatUser($user->load('roles')),
            'token'       => $token,
            'permissions' => $user->allControls(),
        ], 'Login successful.');
    }

    /**
     * POST /api/v1/auth/logout
     * Requires: auth:sanctum
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully.');
    }

    /**
     * GET /api/v1/auth/me
     * Returns authenticated user profile with roles and permissions.
     * Requires: auth:sanctum
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles.controls', 'institution']);

        return $this->success([
            'user'        => $this->formatUser($user),
            'roles'       => $user->roles->map(fn($r) => ['name' => $r->name, 'slug' => $r->slug]),
            'permissions' => $user->allControls(),
        ]);
    }

    /**
     * POST /api/v1/auth/verify
     * Mark user as verified (called after OTP confirmation).
     * Requires: auth:sanctum
     */
    public function verify(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_verified) {
            return $this->error('Account is already verified.', 409);
        }

        $user->is_verified = true;
        $user->save();

        return $this->success(null, 'Account verified successfully.');
    }

    /**
     * POST /api/v1/auth/change-password
     * Requires: auth:sanctum
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password_hash)) {
            return $this->error('Current password is incorrect.', 401);
        }

        $user->password_hash = Hash::make($request->password);
        $user->save();

        // Revoke all tokens to force re-login
        $user->tokens()->delete();

        return $this->success(null, 'Password changed successfully. Please login again.');
    }

    // ─── Private Helpers ───────────────────────────────────────────

    private function formatUser(User $user): array
    {
        return [
            'user_id'       => $user->user_id,
            'full_name'     => $user->full_name,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'user_type'     => $user->user_type,
            'membership_id' => $user->membership_id,
            'is_verified'   => $user->is_verified,
            'created_at'    => $user->created_at,
            'last_login'    => $user->last_login,
            'institution'   => $user->institution ? [
                'institution_id' => $user->institution->institution_id,
                'name'           => $user->institution->name,
            ] : null,
        ];
    }
}