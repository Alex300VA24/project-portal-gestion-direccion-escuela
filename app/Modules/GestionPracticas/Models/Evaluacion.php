<?php

namespace App\Modules\GestionPracticas\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Evaluacion extends ModuleModel
{
    public const SUPERVISOR = 'supervisor';

    public const EMPRESA = 'empresa';

    protected $table = 'gestion_practicas.evaluacion';

    protected $primaryKey = 'id_evaluacion';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['evaluado_en' => 'datetime'];
    }

    public function practica(): BelongsTo
    {
        return $this->belongsTo(Practica::class, 'id_practica', 'id_practica');
    }

    public function notas(): HasMany
    {
        return $this->hasMany(EvaluacionCriterio::class, 'id_evaluacion', 'id_evaluacion');
    }
}
