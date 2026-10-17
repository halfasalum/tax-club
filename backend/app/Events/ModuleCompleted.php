<?php

namespace App\Events;

use App\Models\LearningModule;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A member finished every required lesson of a module.
 */
class ModuleCompleted
{
    use Dispatchable;

    public function __construct(public User $member, public LearningModule $module) {}
}
