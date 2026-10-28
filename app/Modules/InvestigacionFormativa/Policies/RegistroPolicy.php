<?php

namespace App\Modules\InvestigacionFormativa\Policies;

use App\Shared\Policies\ModulePolicy;

final class RegistroPolicy extends ModulePolicy
{
    protected string $permission = 'investigacion-formativa.view';
}
