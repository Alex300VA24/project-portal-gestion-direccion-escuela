<?php

namespace App\Modules\GestionPracticas\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ObservacionConvocatoria extends ModuleModel
{
    public const PENDIENTE = 'pendiente';

    public const SUBSANADA = 'subsanada';

    protected $table = 'gestion_practicas.observacion_convocatoria';

    protected $primaryKey = 'id_observacion';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['registrado_en' => 'datetime', 'subsanada_en' => 'datetime'];
    }

    public function convocatoria(): BelongsTo
    {
        return $this->belongsTo(Convocatoria::class, 'id_convocatoria', 'id_convocatoria');
    }
}
