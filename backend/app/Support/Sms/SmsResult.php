<?php

namespace App\Support\Sms;

final readonly class SmsResult
{
    public function __construct(
        public bool $accepted,
        public ?string $reference = null,
        public ?string $error = null,
    ) {}

    public static function accepted(?string $reference = null): self
    {
        return new self(true, $reference);
    }

    public static function failed(string $error): self
    {
        return new self(false, null, $error);
    }
}
