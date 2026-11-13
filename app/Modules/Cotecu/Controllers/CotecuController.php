<?php

namespace App\Modules\Cotecu\Controllers;

use App\Shared\Controllers\ModuleController;

final class CotecuController extends ModuleController
{
    protected const MODULE = 'COTECU';

    protected const SCHEMA = 'cotecu';

    protected const VIEW = 'modules.cotecu.index';
}
