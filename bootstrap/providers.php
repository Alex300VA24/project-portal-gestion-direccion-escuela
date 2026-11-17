<?php

use App\Modules\InvestigacionFormativa\Providers\InvestigacionFormativaServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\ModulesServiceProvider;

return [
    AppServiceProvider::class,
    ModulesServiceProvider::class,
    InvestigacionFormativaServiceProvider::class,
];
