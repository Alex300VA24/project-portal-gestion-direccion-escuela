<?php

namespace App\Modules\SeguimientoEgresado\Policies;

use App\Shared\Policies\ModulePolicy;

final class RegistroPolicy extends ModulePolicy
{
    protected string $permission = 'seguimiento-egresado.view';
}
