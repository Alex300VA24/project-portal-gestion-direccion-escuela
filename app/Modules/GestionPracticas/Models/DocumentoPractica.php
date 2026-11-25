<?php

namespace App\Modules\GestionPracticas\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DocumentoPractica extends ModuleModel
{
    public const PENDIENTE = 'pendiente';

    public const APROBADO = 'aprobado';

    public const OBSERVADO = 'observado';

    public const REEMPLAZADO = 'reemplazado';

    protected $table = 'gestion_practicas.documento_practica';

    protected $primaryKey = 'id_documento';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['subido_en' => 'datetime', 'revisado_en' => 'datetime'];
    }

    public function practica(): BelongsTo
    {
        return $this->belongsTo(Practica::class, 'id_practica', 'id_practica');
    }
}
