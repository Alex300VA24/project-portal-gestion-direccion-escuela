<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\Core\Persona;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Docente extends ModuleModel
{
    protected $table = 'core.docente';

    protected $primaryKey = 'id_docente';

    public $timestamps = false;

    protected $fillable = [
        'id_persona',
        'codigo_docente',
        'id_departamento',
        'categoria',
        'condicion',
        'dedicacion',
        'grado_academico',
        'horas_maximas',
        'orcid',
        'codigo_renacyt',
        'estado',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'id_persona', 'id_persona');
    }

    public function alertasReportadas(): HasMany
    {
        return $this->hasMany(AlertaRiesgo::class, 'id_docente', 'id_docente');
    }
}
