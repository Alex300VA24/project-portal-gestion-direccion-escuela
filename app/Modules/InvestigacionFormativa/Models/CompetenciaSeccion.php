<?php

namespace App\Modules\InvestigacionFormativa\Models;

use App\Shared\Models\ModuleModel;

final class CompetenciaSeccion extends ModuleModel
{
    protected $table = 'investigacion_formativa.competencia_seccion';

    protected $primaryKey = 'id_competencia';

    public $timestamps = false;

    protected $fillable = [
        'id_seccion',
        'codigo',
        'descripcion',
        'fuente',
        'seleccionada',
    ];

    protected $casts = [
        'seleccionada' => 'boolean',
    ];
}
