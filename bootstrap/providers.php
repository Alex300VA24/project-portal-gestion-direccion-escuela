<?php

use App\Providers\AppServiceProvider;
use App\Providers\ModulesServiceProvider;

return [
    AppServiceProvider::class,
    ModulesServiceProvider::class,
    \App\Modules\GestionHorarios\Providers\AccesoIntegracionDemoProvider::class,
];
