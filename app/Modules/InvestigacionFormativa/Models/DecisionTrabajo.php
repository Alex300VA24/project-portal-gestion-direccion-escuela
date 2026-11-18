<?php

namespace App\Modules\InvestigacionFormativa\Models;

use App\Shared\Models\ModuleModel;

class DecisionTrabajo extends ModuleModel
{
    protected $table = 'investigacion_formativa.decision_trabajo';

    protected $primaryKey = 'id_decision';

    public $timestamps = false;

    protected $fillable = [
        'id_seccion',
        'tipo',
        'accion',
        'id_proyecto',
        'version',
        'user_id',
        'id_docente',
        'decidido_en',
        'evidencia',
        'motivo',
    ];

    protected $casts = [
        'version' => 'integer',
        'decidido_en' => 'datetime',
        'evidencia' => 'array',
    ];
}
