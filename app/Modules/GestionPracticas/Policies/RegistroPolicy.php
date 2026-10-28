<?php

namespace App\Modules\GestionPracticas\Policies;

use App\Shared\Policies\ModulePolicy;

final class RegistroPolicy extends ModulePolicy
{
    protected string $permission = 'gestion-practicas.view';
}
