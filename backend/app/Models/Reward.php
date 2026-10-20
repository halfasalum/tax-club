<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * A reward or recognition that can be given to members (RWD-REQ01).
 */
class Reward extends Model
{
    use Auditable;

    public const TYPES = ['trophy', 'medal', 'merchandise', 'airtime', 'books', 'scholarship', 'visit', 'other'];

    protected $fillable = ['name_sw', 'name_en', 'type', 'description', 'value_tzs', 'stock', 'needs_approval', 'is_active'];

    protected $casts = [
        'needs_approval' => 'boolean',
        'is_active' => 'boolean',
        'value_tzs' => 'integer',
        'stock' => 'integer',
    ];

    public function name(): string
    {
        return app()->getLocale() === 'en' ? $this->name_en : $this->name_sw;
    }
}
