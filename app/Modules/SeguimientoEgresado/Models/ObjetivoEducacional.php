<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class ObjetivoEducacional extends ModuleModel
{
    protected $table = 'seguimiento_egresado.objetivo_educacional';

    public $timestamps = false;

    protected $fillable = [
        'id_plan_curricular',
        'codigo',
        'titulo',
        'descripcion',
        'vigente',
    ];

    public function planCurricular()
    {
        return $this->belongsTo(PlanCurricular::class, 'id_plan_curricular');
    }

    public function evaluaciones()
    {
        return $this->hasMany(EvaluacionOe::class, 'id_objetivo');
    }
}
