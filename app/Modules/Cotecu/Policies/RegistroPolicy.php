<?php

namespace App\Modules\Cotecu\Policies;

use App\Shared\Policies\ModulePolicy;

final class RegistroPolicy extends ModulePolicy
{
    protected string $permission = 'cotecu.view';
}
