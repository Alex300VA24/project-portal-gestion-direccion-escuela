<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PartidaPresupuesto extends ModuleModel
{
    protected $table = 'tesis.partida_presupuesto';

    protected $primaryKey = 'id_partida';

    public $timestamps = false;

    protected $fillable = [
        'id_recurso',
        'id_proyecto',
        'codigo_clasificador',
        'descripcion',
        'cantidad',
        'unidad',
        'costo_unitario',
        'fuente_financiamiento',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
    }

    public function monto(): float
    {
        return round((float) $this->cantidad * (float) $this->costo_unitario, 2);
    }
}
