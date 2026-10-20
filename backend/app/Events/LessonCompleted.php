<?php

namespace App\Events;

use App\Models\Content;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A member finished a lesson for the first time.
 */
class LessonCompleted
{
    use Dispatchable;

    public function __construct(public User $member, public Content $lesson) {}
}
