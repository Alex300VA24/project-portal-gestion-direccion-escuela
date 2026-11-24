<?php

namespace App\Modules\InvestigacionFormativa\Models;

use App\Shared\Models\ModuleModel;

class EvaluacionCriterio extends ModuleModel
{
    protected $table = 'investigacion_formativa.evaluacion_criterio';

    public $incrementing = false;

    protected $primaryKey = null;

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $fillable = [
        'id_evaluacion',
        'id_criterio',
        'nivel',
        'puntaje',
    ];

    protected $casts = [
        'nivel' => 'integer',
        'puntaje' => 'decimal:2',
    ];
}
