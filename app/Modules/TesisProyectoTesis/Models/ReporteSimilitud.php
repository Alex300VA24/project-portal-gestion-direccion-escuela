<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Modules\TesisProyectoTesis\Models\Concerns\UsaUuid;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReporteSimilitud extends ModuleModel
{
    use UsaUuid;

    protected $table = 'tesis.reporte_similitud';

    protected $primaryKey = 'id_reporte';

    public $timestamps = false;

    protected $fillable = [
        'id_entrega',
        'porcentaje',
        'herramienta',
        'id_archivo',
        'id_docente',
        'indicaciones',
        'resultado',
    ];

    protected function casts(): array
    {
        return [
            'porcentaje' => 'decimal:2',
            'generado_en' => 'datetime',
        ];
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class, 'id_entrega', 'id_entrega');
    }
}
