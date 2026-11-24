<?php

namespace App\Modules\GestionPracticas\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ObservacionPractica extends ModuleModel
{
    public const PENDIENTE = 'pendiente';

    public const SUBSANADA = 'subsanada';

    protected $table = 'gestion_practicas.observacion_practica';

    protected $primaryKey = 'id_observacion';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['registrado_en' => 'datetime', 'subsanada_en' => 'datetime'];
    }

    public function practica(): BelongsTo
    {
        return $this->belongsTo(Practica::class, 'id_practica', 'id_practica');
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoPractica::class, 'id_documento', 'id_documento');
    }
}
