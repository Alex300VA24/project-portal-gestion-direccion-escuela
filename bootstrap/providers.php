<?php

use App\Modules\Bienestar\Providers\BienestarServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\ModulesServiceProvider;

return [
    AppServiceProvider::class,
    ModulesServiceProvider::class,
    BienestarServiceProvider::class,
];
