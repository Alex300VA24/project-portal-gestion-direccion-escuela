<?php

namespace App\Modules\InvestigacionFormativa\Models;

use App\Shared\Models\ModuleModel;

class Encuesta extends ModuleModel
{
    protected $table = 'investigacion_formativa.encuesta';

    protected $primaryKey = 'id_encuesta';

    public $timestamps = false;

    protected $fillable = [
        'id_seccion',
        'estado',
        'creada_por',
        'publicada_en',
        'cerrada_en',
        'version',
    ];

    protected $casts = [
        'publicada_en' => 'datetime',
        'cerrada_en' => 'datetime',
        'version' => 'integer',
    ];
}
