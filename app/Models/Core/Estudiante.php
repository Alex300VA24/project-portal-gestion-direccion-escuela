<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Estudiante extends Model
{
    protected $table = 'core.estudiante';

    protected $primaryKey = 'id_estudiante';

    public $timestamps = false;

    protected $fillable = [
        'id_persona',
        'codigo_universitario',
        'id_escuela',
        'id_plan',
        'id_periodo_ingreso',
        'id_modalidad_ingreso',
        'fecha_egreso',
        'estado',
    ];

    protected function casts(): array
    {
        return ['fecha_egreso' => 'date'];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'id_persona', 'id_persona');
    }

    public function planEstudios(): BelongsTo
    {
        return $this->belongsTo(PlanEstudios::class, 'id_plan', 'id_plan');
    }
}
