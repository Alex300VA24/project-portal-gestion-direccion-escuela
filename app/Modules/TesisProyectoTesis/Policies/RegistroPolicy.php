<?php

namespace App\Modules\TesisProyectoTesis\Policies;

use App\Shared\Policies\ModulePolicy;

final class RegistroPolicy extends ModulePolicy
{
    protected string $permission = 'tesis-proyecto-tesis.view';
}
