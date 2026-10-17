<?php

namespace App\Http\Controllers\API\V1;

use App\Models\MembershipVerification;
use App\Support\MembershipCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MembershipController extends BaseController
{
    /**
     * GET /api/v1/membership/card
     * The signed-in member's digital card (MEM-REQ02), including the QR
     * code as SVG and the URL it encodes, so the app can show it offline.
     */
    public function card(Request $request): JsonResponse
    {
        $member = $request->user()->load('institution:institution_id,name,region');
        $valid = MembershipCard::isValid($member);

        if ($valid && ! $member->card_token) {
            MembershipCard::issue($member);
        }

        return $this->success([
            'full_name' => $member->full_name,
            'membership_id' => $member->membership_id,
            'user_type' => $member->user_type,
            'institution' => $member->institution?->only(['institution_id', 'name', 'region']),
            'member_since' => $member->membership_issued_at?->toDateString(),
            'status' => $member->account_status,
            'status_label' => MembershipCard::statusLabel($member->account_status),
            'is_valid' => $valid,
            'verify_url' => $valid ? MembershipCard::verifyUrl($member) : null,
            'qr_svg' => $valid ? MembershipCard::qrSvg($member) : null,
        ]);
    }

    /**
     * POST /api/v1/membership/card/reissue
     * Replace the QR code after a card was lost or shared.
     */
    public function reissue(Request $request): JsonResponse
    {
        $member = $request->user();
        if (! MembershipCard::isValid($member)) {
            return $this->error('Only active members have a card.', 403);
        }

        MembershipCard::reissue($member);

        return $this->success(['verify_url' => MembershipCard::verifyUrl($member)], 'Your card has a new QR code.');
    }

    /**
     * POST /api/v1/membership/verify
     * Check a card by QR token or membership number (MEM-REQ03). Public and
     * rate-limited: everyone gets status and short name; a signed-in officer
     * with verify_membership also gets full details.
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required_without:membership_number|nullable|string|max:64',
            'membership_number' => 'required_without:token|nullable|string|max:40',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $method = $request->filled('token') ? MembershipVerification::METHOD_QR : MembershipVerification::METHOD_NUMBER;
        $input = (string) ($request->filled('token') ? $request->token : $request->membership_number);

        $check = MembershipCard::verify($input, $method, $request->user('sanctum'), $request->ip());
        $member = $check['member'];

        return $this->success([
            'result' => $check['result'],
            'is_valid' => $check['result'] === MembershipCard::RESULT_VALID,
            'status' => $check['status'],
            'display_name' => $check['display_name'],
            'details' => $member ? [
                'full_name' => $member->full_name,
                'membership_id' => $member->membership_id,
                'user_type' => $member->user_type,
                'institution' => $member->institution?->only(['institution_id', 'name', 'region']),
                'member_since' => $member->membership_issued_at?->toDateString(),
                'is_minor' => $member->isMinor(),
            ] : null,
        ]);
    }
}
