<?php

namespace App\Modules\InvestigacionFormativa\Models;

use App\Shared\Models\ModuleModel;

class Criterio extends ModuleModel
{
    protected $table = 'investigacion_formativa.criterio';

    protected $primaryKey = 'id_criterio';

    public $timestamps = false;

    protected $fillable = [
        'id_rubrica',
        'descripcion',
        'puntaje_maximo',
        'orden',
    ];

    protected $casts = [
        'puntaje_maximo' => 'decimal:2',
        'orden' => 'integer',
    ];
}
