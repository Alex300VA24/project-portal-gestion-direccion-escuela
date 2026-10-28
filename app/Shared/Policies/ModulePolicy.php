<?php

namespace App\Shared\Policies;

use App\Models\User;

abstract class ModulePolicy
{
    protected string $permission = '';

    public function viewAny(User $user): bool
    {
        return $user->can($this->permission);
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }
}
