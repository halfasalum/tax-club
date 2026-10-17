<?php

namespace App\Http\Controllers\API\V1;

use App\Models\MemberStatusChange;
use App\Models\User;
use App\Support\MemberAccounts;
use App\Support\MemberProfile;
use App\Support\MemberRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Member profile for the mobile app (PRO-REQ01-03, REG-REQ07).
 */
class ProfileController extends BaseController
{
    /**
     * GET /api/v1/profile
     */
    public function show(Request $request): JsonResponse
    {
        $member = $request->user()->load('institution');

        return $this->success(MemberProfile::visibleTo($member, $member) + [
            'photo_url' => $member->photo_path ? route('members.photo', ['member' => $member, 'v' => md5($member->photo_path)]) : null,
            'can_request_alumni' => MemberAccounts::canBecomeAlumni($member),
            'history' => $member->statusHistory()->limit(20)->get()->map(fn (MemberStatusChange $change) => [
                'field' => $change->field,
                'from' => $change->from_value,
                'to' => $change->to_value,
                'at' => $change->changed_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * PUT /api/v1/profile
     * Email, gender and SMS language. Other details are corrected by officers.
     */
    public function update(Request $request): JsonResponse
    {
        $member = $request->user();
        $validator = Validator::make($request->all(), MemberProfile::rules($member));

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        MemberProfile::update($member, $validator->validated());

        return $this->show($request);
    }

    /**
     * POST /api/v1/profile/photo (multipart, field "photo")
     */
    public function updatePhoto(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096']);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        try {
            MemberProfile::storePhoto($request->user(), $request->file('photo'));
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->show($request);
    }

    /**
     * DELETE /api/v1/profile/photo
     */
    public function deletePhoto(Request $request): JsonResponse
    {
        MemberProfile::removePhoto($request->user());

        return $this->show($request);
    }

    /**
     * POST /api/v1/profile/phone  { phone }
     * Sends a code to the new number.
     */
    public function requestPhoneChange(Request $request): JsonResponse
    {
        $request->merge(['phone' => MemberRegistration::compactPhone((string) $request->input('phone'))]);
        $validator = Validator::make($request->all(), ['phone' => ['required', 'regex:'.MemberRegistration::PHONE_PATTERN]]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        try {
            MemberProfile::requestPhoneChange($request->user(), $request->phone, $request->ip());
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(['expires_in_minutes' => MemberProfile::PHONE_CODE_MINUTES], 'A code was sent to the new number.');
    }

    /**
     * POST /api/v1/profile/phone/confirm  { code }
     */
    public function confirmPhoneChange(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['code' => 'required|digits:6']);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        try {
            MemberProfile::confirmPhoneChange($request->user(), $request->code);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->show($request);
    }

    /**
     * POST /api/v1/profile/alumni-request
     */
    public function requestAlumni(Request $request): JsonResponse
    {
        try {
            MemberAccounts::requestAlumni($request->user());
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->show($request);
    }

    /**
     * DELETE /api/v1/profile/alumni-request
     */
    public function cancelAlumniRequest(Request $request): JsonResponse
    {
        MemberAccounts::cancelAlumniRequest($request->user());

        return $this->show($request);
    }

    /**
     * GET /api/v1/members/{id}
     * Another member's profile, limited by the privacy rules (PRO-REQ03).
     */
    public function member(Request $request, string $id): JsonResponse
    {
        $member = User::with('institution')
            ->where('user_type', '!=', 'staff')
            ->where('account_status', User::STATUS_ACTIVE)
            ->find($id);

        if (! $member) {
            return $this->error('Member not found.', 404);
        }

        return $this->success(MemberProfile::visibleTo($request->user(), $member));
    }
}
