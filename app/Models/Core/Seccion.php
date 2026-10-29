<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Seccion extends Model
{
    protected $table = 'core.seccion';

    protected $primaryKey = 'id_seccion';

    public $timestamps = false;

    protected $fillable = [
        'id_periodo',
        'id_curso_plan',
        'codigo',
        'capacidad',
        'id_docente_responsable',
        'estado',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class, 'id_periodo', 'id_periodo');
    }

    public function cursoPlan(): BelongsTo
    {
        return $this->belongsTo(CursoPlan::class, 'id_curso_plan', 'id_curso_plan');
    }
}
