<?php

namespace App\Modules\SeguimientoEstudiante\Policies;

use App\Shared\Policies\ModulePolicy;

final class RegistroPolicy extends ModulePolicy
{
    protected string $permission = 'seguimiento-estudiante.view';
}
