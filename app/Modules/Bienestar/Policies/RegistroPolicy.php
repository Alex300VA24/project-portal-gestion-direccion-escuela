<?php

namespace App\Modules\Bienestar\Policies;

use App\Shared\Policies\ModulePolicy;

final class RegistroPolicy extends ModulePolicy
{
    protected string $permission = 'bienestar.view';
}
