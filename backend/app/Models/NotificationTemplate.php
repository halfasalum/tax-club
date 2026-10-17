<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Administrator settings for one notification (NOT-REQ01).
 */
class NotificationTemplate extends Model
{
    use Auditable;

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'sms_enabled',
        'inbox_enabled',
        'body_sw',
        'body_en',
        'updated_by',
    ];

    protected $casts = [
        'sms_enabled' => 'boolean',
        'inbox_enabled' => 'boolean',
    ];

    public function bodyFor(string $locale): ?string
    {
        $body = $locale === 'en' ? $this->body_en : $this->body_sw;

        return filled($body) ? $body : null;
    }
}
