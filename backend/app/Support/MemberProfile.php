<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\PhoneChangeCode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * What members may change about themselves (PRO-REQ02) and who may see
 * what (PRO-REQ03). Name, date of birth, institution and category are
 * corrected by patrons or coordinators, not by the member.
 */
final class MemberProfile
{
    public const PHOTO_DISK = 'local';

    public const PHOTO_SIZE = 512;

    public const PHONE_CODE_MINUTES = 10;

    public const LOCALES = ['sw', 'en'];

    /**
     * @return array<string, mixed>
     */
    public static function rules(User $member): array
    {
        return [
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email,'.$member->user_id.',user_id'],
            'gender' => ['nullable', 'in:female,male'],
            'preferred_locale' => ['required', 'in:'.implode(',', self::LOCALES)],
        ];
    }

    /**
     * @param  array{email?: ?string, gender?: ?string, preferred_locale: string}  $data
     */
    public static function update(User $member, array $data): void
    {
        $member->forceFill([
            'email' => filled($data['email'] ?? null) ? Str::lower(trim($data['email'])) : null,
            'gender' => $data['gender'] ?: null,
            'preferred_locale' => $data['preferred_locale'],
        ])->save();
    }

    /**
     * Store a square, resized copy of the photo. Re-encoding removes EXIF
     * data such as the GPS location where the photo was taken.
     */
    public static function storePhoto(User $member, UploadedFile $file): void
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw new InvalidArgumentException(__('This file is not a photo we can read. Use a JPG, PNG or WebP image.'));
        }

        $side = min(imagesx($source), imagesy($source));
        $square = imagecreatetruecolor(self::PHOTO_SIZE, self::PHOTO_SIZE);
        imagecopyresampled(
            $square, $source, 0, 0,
            (int) ((imagesx($source) - $side) / 2), (int) ((imagesy($source) - $side) / 2),
            self::PHOTO_SIZE, self::PHOTO_SIZE, $side, $side,
        );

        ob_start();
        imagejpeg($square, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($square);

        $path = 'profile-photos/'.$member->user_id.'-'.Str::random(8).'.jpg';
        Storage::disk(self::PHOTO_DISK)->put($path, $jpeg);

        $old = $member->photo_path;
        $member->forceFill(['photo_path' => $path])->save();

        if ($old) {
            Storage::disk(self::PHOTO_DISK)->delete($old);
        }
    }

    public static function removePhoto(User $member): void
    {
        if ($member->photo_path) {
            Storage::disk(self::PHOTO_DISK)->delete($member->photo_path);
            $member->forceFill(['photo_path' => null])->save();
        }
    }

    /**
     * Send a code to the new number; the change happens once it is entered.
     */
    public static function requestPhoneChange(User $member, string $newPhone, ?string $ip = null): void
    {
        $phone = MemberRegistration::normalizePhone($newPhone);

        if ($phone === $member->phone) {
            throw new InvalidArgumentException(__('This is already your number.'));
        }

        if (User::where('phone', $phone)->exists()) {
            throw new InvalidArgumentException(__('This number is already used by another account.'));
        }

        $key = 'phone-change|'.$member->user_id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw new InvalidArgumentException(__('Too many codes requested. Try again in :minutes minutes.', [
                'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60),
            ]));
        }
        RateLimiter::hit($key, 15 * 60);

        $code = OneTimeCode::issue($member, OneTimeCode::PHONE_CHANGE, self::PHONE_CODE_MINUTES, $ip, ['phone' => $phone]);

        Notification::route('sms', $phone)
            ->notify((new PhoneChangeCode($code, self::PHONE_CODE_MINUTES))->locale($member->preferredLocale()));
    }

    public static function confirmPhoneChange(User $member, string $code): string
    {
        $meta = OneTimeCode::consume($member, OneTimeCode::PHONE_CHANGE, $code);

        if ($meta === null || empty($meta['phone'])) {
            throw new InvalidArgumentException(__('The code is incorrect or has expired. Request a new code and try again.'));
        }

        if (User::where('phone', $meta['phone'])->where('user_id', '!=', $member->user_id)->exists()) {
            throw new InvalidArgumentException(__('This number is already used by another account.'));
        }

        $member->forceFill(['phone' => $meta['phone']])->save();

        return $meta['phone'];
    }

    /**
     * Profile fields the viewer may see (PRO-REQ03, decision D-04).
     *
     *  - The member and officers who look after them: everything.
     *  - Other members: short name, category, points and when they joined;
     *    the institution only for adults. Never contact details, date of
     *    birth, guardian details or photo.
     *
     * @return array<string, mixed>
     */
    public static function visibleTo(?User $viewer, User $member): array
    {
        $public = [
            'user_id' => $member->user_id,
            'display_name' => $member->publicName(),
            'user_type' => $member->user_type,
            'total_points' => (int) $member->total_points,
            'member_since' => $member->membership_issued_at?->toDateString(),
            'institution' => $member->isMinor() ? null : $member->institution?->only(['name', 'region']),
            'region' => $member->institution?->region,
        ];

        $isSelf = $viewer?->is($member) ?? false;
        $isOfficer = $viewer && $viewer->hasControl('view_members') && $viewer->canLookAfter($member);

        if (! $isSelf && ! $isOfficer) {
            return $public;
        }

        return $public + [
            'full_name' => $member->full_name,
            'membership_id' => $member->membership_id,
            'phone' => $member->phone,
            'email' => $member->email,
            'gender' => $member->gender,
            'date_of_birth' => $member->date_of_birth?->toDateString(),
            'is_minor' => $member->isMinor(),
            'account_status' => $member->account_status,
            'preferred_locale' => $member->preferredLocale(),
            'has_photo' => filled($member->photo_path),
            'alumni_requested_at' => $member->alumni_requested_at?->toIso8601String(),
            'guardian' => $member->guardian_name ? [
                'name' => $member->guardian_name,
                'phone' => $member->guardian_phone,
                'relationship' => $member->guardian_relationship,
            ] : null,
        ];
    }

    public static function canSeePhoto(?User $viewer, User $member): bool
    {
        return $viewer !== null && ($viewer->is($member) || ($viewer->canAccessAdmin() && $viewer->canLookAfter($member)));
    }
}
