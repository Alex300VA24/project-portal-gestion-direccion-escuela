<?php

namespace App\Modules\InvestigacionFormativa\Models;

use App\Shared\Models\ModuleModel;

class Observacion extends ModuleModel
{
    protected $table = 'investigacion_formativa.observacion';

    protected $primaryKey = 'id_observacion';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $fillable = [
        'id_entrega',
        'id_evaluacion',
        'version_evaluacion',
        'descripcion',
        'estado',
        'registrado_por',
        'registrado_en',
    ];

    protected $casts = [
        'id_evaluacion' => 'integer',
        'version_evaluacion' => 'integer',
        'registrado_en' => 'datetime',
    ];
}
