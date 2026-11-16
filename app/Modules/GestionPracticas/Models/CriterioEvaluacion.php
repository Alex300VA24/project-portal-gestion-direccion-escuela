<?php

namespace App\Modules\GestionPracticas\Models;

use App\Shared\Models\ModuleModel;

final class CriterioEvaluacion extends ModuleModel
{
    protected $table = 'gestion_practicas.criterio_evaluacion';

    protected $primaryKey = 'id_criterio';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['peso' => 'decimal:2', 'activo' => 'boolean'];
    }
}
