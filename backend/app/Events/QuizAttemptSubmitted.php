<?php

namespace App\Events;

use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A member's quiz attempt was scored.
 */
class QuizAttemptSubmitted
{
    use Dispatchable;

    public function __construct(public User $member, public QuizAttempt $attempt) {}
}
