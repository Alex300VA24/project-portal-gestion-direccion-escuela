<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HistorialEstado extends ModuleModel
{
    protected $table = 'tesis.historial_estado';

    protected $primaryKey = 'id_historial';

    public $timestamps = false;

    protected $fillable = [
        'id_proyecto',
        'estado_anterior',
        'estado_nuevo',
        'cambiado_por',
        'cambiado_en',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'cambiado_en' => 'datetime',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
    }
}
