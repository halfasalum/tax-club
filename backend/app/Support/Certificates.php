<?php

namespace App\Support;

use App\Models\Certificate;
use App\Models\Competition;
use App\Models\Event;
use App\Models\LearningModule;
use App\Models\User;
use App\Notifications\CertificateIssued;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Digital certificates (CER-REQ01/02). Issued automatically for completed
 * modules, attended events and competition results, or by an officer.
 * Each has a number and a QR code linking to the public check page; a
 * revoked certificate shows as not valid there.
 */
final class Certificates
{
    public static function issue(User $member, string $kind, string $titleSw, string $titleEn, string $subject, ?string $sourceType = null, ?string $sourceId = null, ?User $by = null): Certificate
    {
        if (! in_array($kind, Certificate::KINDS, true)) {
            throw new InvalidArgumentException(__('Unknown certificate type.'));
        }

        $existing = Certificate::where('user_id', $member->user_id)->where('kind', $kind)->where('source_type', $sourceType)->where('source_id', $sourceId)->first();

        if ($existing && $sourceType !== null) {
            return $existing;
        }

        $certificate = Certificate::create([
            'number' => self::newNumber(),
            'token' => self::newToken(),
            'user_id' => $member->user_id,
            'kind' => $kind,
            'title_sw' => $titleSw,
            'title_en' => $titleEn,
            'subject' => Str::limit($subject, 245, ''),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'issued_at' => now(),
            'issued_by' => $by?->user_id,
        ]);

        Audit::record('certificate.issued', $certificate, after: ['number' => $certificate->number, 'kind' => $kind, 'member' => $member->membership_id], actor: $by);
        Notify::send($member, new CertificateIssued($titleSw, $titleEn, $certificate->certificate_id));

        return $certificate;
    }

    public static function forModule(User $member, LearningModule $module): Certificate
    {
        return self::issue($member, 'module', 'Cheti cha Kukamilisha Moduli', 'Certificate of Completion', $module->title, 'module', (string) $module->id);
    }

    public static function forEvent(User $member, Event $event): Certificate
    {
        return self::issue($member, 'event', 'Cheti cha Ushiriki', 'Certificate of Participation', $event->title, 'event', $event->event_id);
    }

    public static function forCompetition(User $member, Competition $competition, ?int $rank): Certificate
    {
        $winner = $rank !== null && $rank <= $competition->winners_count;

        return self::issue(
            $member,
            $winner ? 'competition_winner' : 'competition',
            $winner ? 'Cheti cha Ushindi' : 'Cheti cha Ushiriki',
            $winner ? 'Certificate of Achievement' : 'Certificate of Participation',
            $winner ? $competition->title.' · '.self::ordinal($rank) : $competition->title,
            'competition',
            $competition->competition_id,
        );
    }

    public static function revoke(Certificate $certificate, string $reason, User $by): void
    {
        $certificate->forceFill(['status' => Certificate::REVOKED, 'revoked_reason' => Review::requireReason($reason), 'revoked_at' => now(), 'revoked_by' => $by->user_id])->save();
        Audit::record('certificate.revoked', $certificate, ['status' => 'valid'], ['status' => 'revoked'], $reason, $by);
    }

    public static function reinstate(Certificate $certificate, string $reason, User $by): void
    {
        $certificate->forceFill(['status' => Certificate::VALID, 'revoked_reason' => null, 'revoked_at' => null, 'revoked_by' => null])->save();
        Audit::record('certificate.reinstated', $certificate, ['status' => 'revoked'], ['status' => 'valid'], Review::requireReason($reason), $by);
    }

    /**
     * Find a certificate by its QR token or typed number.
     */
    public static function find(string $input): ?Certificate
    {
        $input = trim($input);

        if (preg_match('/^[A-Za-z0-9]{32}$/', $input)) {
            return Certificate::where('token', $input)->first();
        }

        return Certificate::where('number', strtoupper(preg_replace('/\s+/', '', $input)))->first();
    }

    public static function verifyUrl(Certificate $certificate): string
    {
        return route(app()->getLocale().'.certificates.verify', ['token' => $certificate->token]);
    }

    public static function qrSvg(Certificate $certificate, int $size = 180): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));

        return trim(preg_replace('/^<\?xml[^>]*\?>/', '', $writer->writeString(route('sw.certificates.verify', ['token' => $certificate->token]))));
    }

    /**
     * @return array<string, string>
     */
    public static function kindLabels(): array
    {
        return [
            'competition_winner' => __('Competition winner'),
            'competition' => __('Competition participation'),
            'module' => __('Module completed'),
            'event' => __('Event participation'),
            'other' => __('Recognition'),
        ];
    }

    public static function ordinal(int $rank): string
    {
        return app()->getLocale() === 'en'
            ? match (true) {
                $rank % 100 >= 11 && $rank % 100 <= 13 => $rank.'th place',
                $rank % 10 === 1 => $rank.'st place',
                $rank % 10 === 2 => $rank.'nd place',
                $rank % 10 === 3 => $rank.'rd place',
                default => $rank.'th place',
            }
        : __('Nafasi ya :rank', ['rank' => $rank]);
    }

    private static function newNumber(): string
    {
        do {
            $number = 'TCC-'.now()->format('y').'-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
            $number = strtr($number, ['O' => '0', 'I' => '1', 'L' => '7']);
        } while (Certificate::where('number', $number)->exists());

        return $number;
    }

    private static function newToken(): string
    {
        do {
            $token = Str::random(32);
        } while (Certificate::where('token', $token)->exists());

        return $token;
    }
}
