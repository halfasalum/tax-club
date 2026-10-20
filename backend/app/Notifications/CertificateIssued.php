<?php

namespace App\Notifications;

/**
 * A certificate is ready to download (CER-REQ01). Inbox only by default.
 */
class CertificateIssued extends TaxClubNotification
{
    public function __construct(public string $titleSw, public string $titleEn, public string $certificateId)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'certificate_issued';
    }

    public static function label(): string
    {
        return __('Certificate issued');
    }

    public static function placeholders(): array
    {
        return ['title'];
    }

    public static function smsByDefault(): bool
    {
        return false;
    }

    public static function preferenceCategory(): ?string
    {
        return 'achievements';
    }

    public function message(): string
    {
        return __('TRA Tax Club: Your certificate ":title" is ready. Download it from My achievements.');
    }

    public function replacements(object $notifiable): array
    {
        return ['title' => app()->getLocale() === 'en' ? $this->titleEn : $this->titleSw];
    }

    public function url(object $notifiable): ?string
    {
        return route('member.certificates.show', $this->certificateId);
    }
}
