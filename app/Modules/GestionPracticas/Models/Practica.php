<?php

namespace App\Modules\GestionPracticas\Models;

use App\Shared\Models\Core\Estudiante;
use App\Shared\Models\Core\PeriodoAcademico;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Practica extends ModuleModel
{
    public const REGISTRADA = 'registrada';

    public const EN_REVISION = 'en_revision';

    public const OBSERVADA = 'observada';

    public const VALIDADA = 'validada';

    public const EN_CURSO = 'en_curso';

    public const FINALIZADA = 'finalizada';

    public const ANULADA = 'anulada';

    protected $table = 'gestion_practicas.practica';

    protected $primaryKey = 'id_practica';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'horas_totales' => 'integer',
            'nota_final' => 'decimal:2',
            'registrada_en' => 'datetime',
            'enviada_en' => 'datetime',
            'validada_en' => 'datetime',
            'supervisor_asignado_en' => 'datetime',
            'cerrada_en' => 'datetime',
        ];
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class, 'id_periodo', 'id_periodo');
    }

    public function postulacion(): HasOne
    {
        return $this->hasOne(Postulacion::class, 'id_practica', 'id_practica');
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(Seguimiento::class, 'id_practica', 'id_practica');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoPractica::class, 'id_practica', 'id_practica');
    }

    public function observaciones(): HasMany
    {
        return $this->hasMany(ObservacionPractica::class, 'id_practica', 'id_practica');
    }

    public function evaluaciones(): HasMany
    {
        return $this->hasMany(Evaluacion::class, 'id_practica', 'id_practica');
    }
}
