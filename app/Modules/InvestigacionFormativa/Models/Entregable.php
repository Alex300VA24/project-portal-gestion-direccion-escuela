<?php

namespace App\Modules\InvestigacionFormativa\Models;

use App\Shared\Models\ModuleModel;

class Entregable extends ModuleModel
{
    protected $table = 'investigacion_formativa.entregable';

    protected $primaryKey = 'id_entregable';

    public $timestamps = false;

    protected $fillable = [
        'id_seccion',
        'nombre',
        'descripcion',
        'fecha_limite',
        'orden',
        'id_rubrica',
    ];

    protected $casts = [
        'fecha_limite' => 'datetime',
        'orden' => 'integer',
    ];
}
