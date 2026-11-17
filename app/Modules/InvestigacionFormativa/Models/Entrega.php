<?php

namespace App\Modules\InvestigacionFormativa\Models;

use App\Shared\Models\ModuleModel;

class Entrega extends ModuleModel
{
    protected $table = 'investigacion_formativa.entrega';

    protected $primaryKey = 'id_entrega';

    public $timestamps = false;

    protected $fillable = [
        'id_proyecto',
        'id_entregable',
        'version',
        'id_archivo',
        'entregado_en',
    ];

    protected $casts = [
        'version' => 'integer',
        'entregado_en' => 'datetime',
    ];
}
