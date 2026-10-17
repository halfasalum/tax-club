<?php

namespace App\Support;

use App\Models\Institution;
use App\Models\MembershipVerification;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membership numbers, digital cards and card verification (MEM-REQ01-04).
 *
 * Numbers follow the format proposed for decision D-03: TC-YY-RRR-NNNNNN-C,
 * i.e. year issued, institution region, yearly running number and a Luhn
 * check digit so mistyped numbers are caught before any lookup.
 */
class MembershipCard
{
    public const RESULT_VALID = 'valid';

    public const RESULT_NOT_VALID = 'not_valid';

    public const RESULT_NOT_FOUND = 'not_found';

    public const RESULT_MISTYPED = 'mistyped';

    public const UNKNOWN_REGION = 'TZA';

    private const NUMBER_PATTERN = '/^TC-(\d{2})-([A-Z]{3})-(\d{6})-(\d)$/';

    /**
     * Give an approved member a number and a card. Members who already have
     * a number keep it (numbers are never reused or changed, MEM-REQ01).
     */
    public static function issue(User $user): void
    {
        $user->forceFill([
            'membership_id' => $user->membership_id ?: self::nextNumber($user->institution),
            'card_token' => $user->card_token ?: self::newToken(),
            'membership_issued_at' => $user->membership_issued_at ?? now(),
        ])->save();
    }

    /**
     * Replace the QR code, e.g. after a card was lost or shared. The number
     * stays the same; printouts of the old card stop verifying.
     */
    public static function reissue(User $user): void
    {
        $user->forceFill(['card_token' => self::newToken()])->save();
    }

    public static function nextNumber(?Institution $institution, ?\DateTimeInterface $date = null): string
    {
        $year = (int) ($date ?? now())->format('Y');
        $region = self::regionCode($institution?->region);

        $sequence = DB::transaction(function () use ($year): int {
            DB::table('membership_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);
            $current = DB::table('membership_sequences')->where('year', $year)->lockForUpdate()->value('last_number');
            DB::table('membership_sequences')->where('year', $year)->update(['last_number' => $current + 1]);

            return $current + 1;
        });

        $yearDigits = substr((string) $year, -2);
        $serial = str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);

        return sprintf('TC-%s-%s-%s-%d', $yearDigits, $region, $serial, self::checkDigit($yearDigits.$serial));
    }

    public static function regionCode(?string $regionName): string
    {
        $code = array_search($regionName, config('tanzania.regions'), true);

        return $code === false ? self::UNKNOWN_REGION : $code;
    }

    /**
     * Luhn (mod 10) check digit.
     */
    public static function checkDigit(string $digits): int
    {
        $sum = 0;
        foreach (array_reverse(str_split($digits)) as $position => $digit) {
            $value = (int) $digit * ($position % 2 === 0 ? 2 : 1);
            $sum += $value > 9 ? $value - 9 : $value;
        }

        return (10 - $sum % 10) % 10;
    }

    /**
     * Accept numbers typed with spaces, lowercase or no dashes.
     */
    public static function normalizeNumber(string $input): string
    {
        $compact = preg_replace('/[^A-Z0-9]/', '', Str::upper($input));

        if (preg_match('/^TC(\d{2})([A-Z]{3})(\d{6})(\d)$/', $compact, $m)) {
            return "TC-{$m[1]}-{$m[2]}-{$m[3]}-{$m[4]}";
        }

        if (preg_match('/^TC(\d{2})([A-Z0-9]{8})$/', $compact, $m)) {
            return "TC-{$m[1]}-{$m[2]}"; // numbers issued before the D-03 format
        }

        return $compact;
    }

    public static function isMistyped(string $number): bool
    {
        return preg_match(self::NUMBER_PATTERN, $number, $m) === 1
            && self::checkDigit($m[1].$m[3]) !== (int) $m[4];
    }

    public static function isValid(User $user): bool
    {
        return $user->account_status === User::STATUS_ACTIVE && filled($user->membership_id);
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            User::STATUS_ACTIVE => __('Active member'),
            User::STATUS_PENDING_VERIFICATION => __('Awaiting verification'),
            User::STATUS_SUSPENDED => __('Suspended'),
            User::STATUS_DEACTIVATED => __('Deactivated'),
            User::STATUS_REJECTED => __('Not approved'),
            default => __('Not a member'),
        };
    }

    public static function verifyUrl(User $user): string
    {
        return route('membership.verify', ['token' => $user->card_token]);
    }

    /**
     * The card's QR code as inline SVG (no external service is called).
     */
    public static function qrSvg(User $user, int $size = 240): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));

        return trim(preg_replace('/^<\?xml[^>]*\?>/', '', $writer->writeString(self::verifyUrl($user))));
    }

    /**
     * Check a card by QR token or typed number and record the check.
     *
     * Everyone gets the status and the member's short name (decision D-13);
     * officers with the verify_membership permission also get full details.
     *
     * @return array{result: string, status: ?string, display_name: ?string, member: ?User, full_details: bool}
     */
    public static function verify(string $input, string $method, ?User $verifier = null, ?string $ip = null): array
    {
        $member = null;
        $result = self::RESULT_NOT_FOUND;

        if ($method === MembershipVerification::METHOD_QR) {
            $member = strlen($input) === 32 ? User::where('card_token', $input)->first() : null;
        } else {
            $number = self::normalizeNumber($input);
            if (self::isMistyped($number)) {
                $result = self::RESULT_MISTYPED;
            } else {
                $member = User::where('membership_id', $number)->first();
            }
        }

        if ($member) {
            $result = self::isValid($member) ? self::RESULT_VALID : self::RESULT_NOT_VALID;
        }

        $fullDetails = $member !== null && (bool) $verifier?->hasControl('verify_membership');

        MembershipVerification::create([
            'member_id' => $member?->user_id,
            'verified_by' => $verifier?->user_id,
            'method' => $method,
            'result' => $result,
            'full_details' => $fullDetails,
            'ip' => $ip,
        ]);

        return [
            'result' => $result,
            'status' => $member?->account_status,
            'display_name' => $member?->publicName(),
            'member' => $fullDetails ? $member->load('institution:institution_id,name,region') : null,
            'full_details' => $fullDetails,
        ];
    }

    private static function newToken(): string
    {
        do {
            $token = Str::random(32);
        } while (User::where('card_token', $token)->exists());

        return $token;
    }
}
