<?php

namespace App\Modules\GestionPracticas\Models;

use App\Shared\Models\Core\Estudiante;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Postulacion extends ModuleModel
{
    public const POSTULADO = 'postulado';

    public const SELECCIONADO = 'seleccionado';

    public const NO_SELECCIONADO = 'no_seleccionado';

    public const DESISTIDO = 'desistido';

    protected $table = 'gestion_practicas.postulacion';

    protected $primaryKey = 'id_postulacion';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['postulado_en' => 'datetime', 'resultado_en' => 'datetime'];
    }

    public function convocatoria(): BelongsTo
    {
        return $this->belongsTo(Convocatoria::class, 'id_convocatoria', 'id_convocatoria');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    public function practica(): BelongsTo
    {
        return $this->belongsTo(Practica::class, 'id_practica', 'id_practica');
    }
}
