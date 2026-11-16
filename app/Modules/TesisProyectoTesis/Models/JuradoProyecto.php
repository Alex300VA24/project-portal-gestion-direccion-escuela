<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class JuradoProyecto extends ModuleModel
{
    protected $table = 'tesis.jurado_proyecto';

    protected $primaryKey = 'id_jurado';

    public $timestamps = false;

    protected $fillable = [
        'id_proyecto',
        'id_docente',
        'cargo',
        'id_resolucion',
        'estado',
        'asignado_por',
        'fecha_asignacion',
        'fecha_fin',
        'motivo_cambio',
    ];

    protected function casts(): array
    {
        return [
            'fecha_asignacion' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
    }
}
