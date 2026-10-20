<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A digital certificate with a public verification link (CER-REQ01/02).
 */
class Certificate extends Model
{
    use HasUuids;

    public const KINDS = ['competition_winner', 'competition', 'module', 'event', 'other'];

    public const VALID = 'valid';

    public const REVOKED = 'revoked';

    protected $primaryKey = 'certificate_id';

    protected $fillable = ['number', 'token', 'user_id', 'kind', 'title_sw', 'title_en', 'subject', 'source_type', 'source_id', 'issued_at', 'issued_by'];

    protected $casts = [
        'issued_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function title(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'en' ? $this->title_en : $this->title_sw;
    }

    public function isValid(): bool
    {
        return $this->status === self::VALID;
    }

    public function getRouteKeyName(): string
    {
        return 'certificate_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
