<?php

namespace App\Modules\PreMatricula\Policies;

use App\Shared\Policies\ModulePolicy;

final class RegistroPolicy extends ModulePolicy
{
    protected string $permission = 'pre-matricula.view';
}
