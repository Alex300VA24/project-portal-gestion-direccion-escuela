<?php

namespace App\Modules\InvestigacionFormativa\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluacion extends ModuleModel
{
    protected $table = 'investigacion_formativa.evaluacion';

    protected $primaryKey = 'id_evaluacion';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $fillable = [
        'id_entrega',
        'id_docente_evaluador',
        'comentario',
        'version',
        'sin_observaciones',
        'evaluado_en',
    ];

    protected $casts = [
        'version' => 'integer',
        'sin_observaciones' => 'boolean',
        'evaluado_en' => 'datetime',
    ];

    public function criterios(): HasMany
    {
        return $this->hasMany(EvaluacionCriterio::class, 'id_evaluacion', 'id_evaluacion');
    }

    public function observaciones(): HasMany
    {
        return $this->hasMany(Observacion::class, 'id_evaluacion', 'id_evaluacion');
    }
}
