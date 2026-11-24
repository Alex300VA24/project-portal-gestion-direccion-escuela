<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marca cumple / no cumple de un criterio. Clave compuesta (id_revision, id_criterio): se inserta y se borra por revisión.
 */
final class DetalleEvaluacion extends ModuleModel
{
    protected $table = 'tesis.detalle_evaluacion';

    protected $primaryKey = 'id_revision';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_revision',
        'id_criterio',
        'puntaje',
        'cumple',
        'no_aplica',
    ];

    protected function casts(): array
    {
        return [
            'cumple' => 'boolean',
            'no_aplica' => 'boolean',
        ];
    }

    public function criterio(): BelongsTo
    {
        return $this->belongsTo(CriterioRubrica::class, 'id_criterio', 'id_criterio');
    }
}
