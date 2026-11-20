<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AsesorProyecto extends ModuleModel
{
    protected $table = 'tesis.asesor_proyecto';

    protected $primaryKey = 'id_asesor';

    public $timestamps = false;

    protected $fillable = [
        'id_proyecto',
        'id_docente',
        'tipo',
        'id_resolucion',
        'estado',
        'fecha_inicio',
        'fecha_fin',
        'motivo_cambio',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
    }

    public function resolucion(): BelongsTo
    {
        return $this->belongsTo(Resolucion::class, 'id_resolucion', 'id_resolucion');
    }
}
