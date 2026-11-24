<?php

namespace App\Modules\GestionPracticas\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Clave primaria compuesta (id_evaluacion, id_criterio): se consulta por relación, no por find(). */
final class EvaluacionCriterio extends ModuleModel
{
    protected $table = 'gestion_practicas.evaluacion_criterio';

    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['nota' => 'decimal:2'];
    }

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class, 'id_evaluacion', 'id_evaluacion');
    }

    public function criterio(): BelongsTo
    {
        return $this->belongsTo(CriterioEvaluacion::class, 'id_criterio', 'id_criterio');
    }
}
