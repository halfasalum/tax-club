<?php

namespace App\Http\Controllers\API\V1;

use App\Models\User;
use App\Support\Audit;
use App\Support\MemberRegistration;
use App\Support\PasswordRecovery;
use App\Support\StaffSignIn;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class AuthController extends BaseController
{
    /**
     * POST /api/v1/auth/register
     * Submit a membership registration. The account stays pending until a
     * patron or TRA coordinator approves it, so no token is issued here.
     */
    public function register(Request $request): JsonResponse
    {
        $input = MemberRegistration::normalizeInput($request->all());
        $validator = Validator::make($input, MemberRegistration::rules($input), MemberRegistration::messages());

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
            StaffSignIn::recordFailure($request->identifier, $user, 'api');

            return $this->error('Invalid credentials.', 401);
        }

        if (! $user->isActive()) {
            return $this->error(MemberRegistration::blockedLoginMessage($user), 403);
        }

        if (StaffSignIn::required($user)) {
            try {
                StaffSignIn::sendCode($user, $request->ip());
            } catch (InvalidArgumentException $exception) {
                return $this->error($exception->getMessage(), 429);
            }

            return $this->success([
                'two_factor_required' => true,
                'challenge' => Crypt::encryptString(json_encode([
                    'user_id' => $user->user_id,
                    'expires_at' => now()->addMinutes(StaffSignIn::CODE_MINUTES)->timestamp,
                ])),
                'expires_in_minutes' => StaffSignIn::CODE_MINUTES,
            ], 'Enter the code sent by SMS to finish signing in.');
        }

        return $this->issueToken($user, twoFactor: false);
    }

    /**
     * POST /api/v1/auth/login/verify
     * Second step for staff accounts: the challenge from login plus the SMS code.
     */
    public function verifyLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'challenge' => 'required|string',
            'code' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        try {
            $challenge = json_decode(Crypt::decryptString($request->challenge), true);
        } catch (DecryptException) {
            $challenge = null;
        }

        $user = $challenge && $challenge['expires_at'] > now()->timestamp ? User::find($challenge['user_id']) : null;

        if (! $user || ! $user->isActive()) {
            return $this->error('Your sign-in code has expired. Sign in again.', 401);
        }

        if (! StaffSignIn::confirm($user, $request->code)) {
            return $this->error('The code is incorrect or has expired.', 422);
        }

        return $this->issueToken($user, twoFactor: true);
    }

    private function issueToken(User $user, bool $twoFactor): JsonResponse
    {
        StaffSignIn::recordLogin($user, 'api', $twoFactor);

        // Revoke previous tokens (single session) — remove this line for multi-device
        $user->tokens()->delete();

        $token = $user->createToken('mobile_app')->plainTextToken;

        return $this->success([
            'user' => $this->formatUser($user->load('roles')),
            'token' => $token,
            'expires_at' => config('sanctum.expiration') ? now()->addMinutes((int) config('sanctum.expiration'))->toIso8601String() : null,
            'permissions' => $user->allControls(),
        ], 'Login successful.');
    }

    /**
     * POST /api/v1/auth/recover
     * Send a password reset code by SMS. The answer is the same whether or
     * not the account exists.
     */
    public function recover(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['identifier' => 'required|string|max:150']);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        try {
            PasswordRecovery::requestCode($request->identifier, $request->ip());
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 429);
        }

        return $this->success(['expires_in_minutes' => PasswordRecovery::CODE_MINUTES], 'If the account exists, a reset code has been sent by SMS.');
    }

    /**
     * POST /api/v1/auth/recover/reset
     * Set a new password with the SMS code. Signs out all devices.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string|max:150',
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        try {
            PasswordRecovery::reset($request->identifier, $request->code, $request->password);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(null, 'Password changed. Please sign in again.');
    }

    /**
     * POST /api/v1/auth/logout
     * Requires: auth:sanctum
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        Audit::record('auth.logout', $request->user(), after: ['channel' => 'api']);

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
