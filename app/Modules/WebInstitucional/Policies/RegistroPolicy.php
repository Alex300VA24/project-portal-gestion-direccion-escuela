<?php

namespace App\Modules\WebInstitucional\Policies;

use App\Shared\Policies\ModulePolicy;

final class RegistroPolicy extends ModulePolicy
{
    protected string $permission = 'web-institucional.view';
}
