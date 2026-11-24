<?php

namespace App\Modules\InvestigacionFormativa\Models;

use App\Shared\Models\ModuleModel;

class CriterioNivel extends ModuleModel
{
    protected $table = 'investigacion_formativa.criterio_nivel';

    public $incrementing = false;

    protected $primaryKey = null;

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $fillable = [
        'id_criterio',
        'nivel',
        'descripcion',
        'puntaje',
    ];

    protected $casts = [
        'nivel' => 'integer',
        'puntaje' => 'decimal:2',
    ];
}
