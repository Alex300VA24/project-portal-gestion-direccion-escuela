<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Integrante del proyecto. Clave compuesta (id_proyecto, id_estudiante):
 * se inserta con create() y se actualiza con consultas filtradas por ambas columnas.
 */
final class ProyectoEstudiante extends ModuleModel
{
    protected $table = 'tesis.proyecto_estudiante';

    protected $primaryKey = 'id_proyecto';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_proyecto',
        'id_estudiante',
        'orden_autor',
        'estado',
        'fecha_incorporacion',
        'fecha_retiro',
        'motivo_retiro',
    ];

    protected function casts(): array
    {
        return [
            'orden_autor' => 'integer',
            'fecha_incorporacion' => 'date',
            'fecha_retiro' => 'date',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
    }
}
