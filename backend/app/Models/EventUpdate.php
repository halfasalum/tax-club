<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An update, instruction or material posted for an event (EVT-REQ07).
 */
class EventUpdate extends Model
{
    public const KINDS = ['update', 'instructions', 'material'];

    /** Private disk for uploaded materials; served through a checked route. */
    public const DISK = 'local';

    protected $fillable = ['event_id', 'kind', 'title', 'body', 'link_url', 'file_path', 'file_name', 'registered_only', 'posted_by'];

    protected $casts = ['registered_only' => 'boolean'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id', 'event_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by', 'user_id');
    }
}
