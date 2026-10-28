<?php

namespace App\Modules\Calidad\Policies;

use App\Shared\Policies\ModulePolicy;

final class RegistroPolicy extends ModulePolicy
{
    protected string $permission = 'calidad.view';
}
