<?php

namespace App\Modules\GestionMatriculas\Policies;

use App\Shared\Policies\ModulePolicy;

final class RegistroPolicy extends ModulePolicy
{
    protected string $permission = 'gestion-matriculas.view';
}
