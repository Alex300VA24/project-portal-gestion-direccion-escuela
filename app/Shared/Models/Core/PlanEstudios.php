<?php

namespace App\Shared\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PlanEstudios extends Model
{
    protected $table = 'core.plan_estudios';

    protected $primaryKey = 'id_plan';

    public $timestamps = false;

    protected $fillable = [
        'id_escuela',
        'codigo',
        'nombre',
        'version',
        'anio_aprobacion',
        'resolucion_aprobacion',
        'fecha_vigencia_inicio',
        'fecha_vigencia_fin',
        'es_predeterminado',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vigencia_inicio' => 'date',
            'fecha_vigencia_fin' => 'date',
            'es_predeterminado' => 'boolean',
        ];
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(CursoPlan::class, 'id_plan', 'id_plan');
    }
}
