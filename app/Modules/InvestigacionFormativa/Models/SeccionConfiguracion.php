<?php

namespace App\Modules\InvestigacionFormativa\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SeccionConfiguracion extends ModuleModel
{
    protected $table = 'investigacion_formativa.seccion_configuracion';

    protected $primaryKey = 'id_configuracion';

    public $timestamps = false;

    protected $fillable = [
        'id_seccion',
        'habilitada',
        'id_rubrica',
        'configurado_en',
        'version',
        'actualizado_por',
        'actualizado_en',
    ];

    protected $casts = [
        'habilitada' => 'boolean',
        'configurado_en' => 'datetime',
        'version' => 'integer',
        'actualizado_en' => 'datetime',
    ];

    public function competencias(): HasMany
    {
        return $this->hasMany(CompetenciaSeccion::class, 'id_seccion', 'id_seccion');
    }
}
