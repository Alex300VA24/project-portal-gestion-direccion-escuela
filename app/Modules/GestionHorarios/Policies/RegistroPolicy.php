<?php

namespace App\Modules\GestionHorarios\Policies;

use App\Shared\Policies\ModulePolicy;

final class RegistroPolicy extends ModulePolicy
{
    protected string $permission = 'gestion-horarios.view';
}
