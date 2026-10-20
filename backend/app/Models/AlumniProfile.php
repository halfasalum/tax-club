<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What an alumnus shares with the Tax Club network (ALM-REQ01/02).
 */
class AlumniProfile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'graduation_year', 'occupation', 'organization', 'field', 'bio', 'skills', 'linkedin_url', 'show_in_directory', 'open_to_mentoring'];

    protected $casts = [
        'show_in_directory' => 'boolean',
        'open_to_mentoring' => 'boolean',
        'graduation_year' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
