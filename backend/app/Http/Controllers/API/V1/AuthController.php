<?php

namespace App\Http\Controllers\API\V1;

use App\Models\User;
use App\Support\MemberRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends BaseController
{
    /**
     * POST /api/v1/auth/register
     * Submit a membership registration. The account stays pending until a
     * patron or TRA coordinator approves it, so no token is issued here.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make(MemberRegistration::normalizeInput($request->all()), MemberRegistration::rules());

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $user = MemberRegistration::register($validator->validated());

        return $this->success([
            'registration_reference' => $user->registration_reference,
            'account_status' => $user->account_status,
        ], 'Registration submitted. You can sign in after your membership is confirmed.', 201);
    }

    /**
     * POST /api/v1/auth/login
     * Accepts a phone number or email as `identifier` (`email` is still
     * accepted for older app builds).
     */
    public function login(Request $request): JsonResponse
    {
        $request->merge(['identifier' => $request->input('identifier', $request->input('email'))]);

        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string|max:150',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $user = User::findByLoginIdentifier($request->identifier);

        if (! $user || ! Hash::check($request->password, $user->password_hash)) {
            return $this->error('Invalid credentials.', 401);
        }

        if (! $user->isActive()) {
            return $this->error(MemberRegistration::blockedLoginMessage($user), 403);
        }

        // Update last login timestamp
        $user->last_login = now();
        $user->save();

        // Revoke previous tokens (single session) — remove this line for multi-device
        $user->tokens()->delete();

        $token = $user->createToken('mobile_app')->plainTextToken;

        return $this->success([
            'user' => $this->formatUser($user->load('roles')),
            'token' => $token,
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
            'user' => $this->formatUser($user),
            'roles' => $user->roles->map(fn ($r) => ['name' => $r->name, 'slug' => $r->slug]),
            'permissions' => $user->allControls(),
        ]);
    }

    /**
     * POST /api/v1/auth/verify
     * Members can no longer verify themselves: membership is confirmed by a
     * patron or TRA coordinator (REG-REQ02). Kept so older app builds get a
     * clear answer instead of a 404.
     */
    public function verify(Request $request): JsonResponse
    {
        return $this->error('Membership is confirmed by your patron or TRA coordinator.', 410);
    }

    /**
     * POST /api/v1/auth/change-password
     * Requires: auth:sanctum
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
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
            'user_id' => $user->user_id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'user_type' => $user->user_type,
            'membership_id' => $user->membership_id,
            'is_verified' => $user->is_verified,
            'account_status' => $user->account_status,
            'created_at' => $user->created_at,
            'last_login' => $user->last_login,
            'institution' => $user->institution ? [
                'institution_id' => $user->institution->institution_id,
                'name' => $user->institution->name,
            ] : null,
        ];
    }
}
