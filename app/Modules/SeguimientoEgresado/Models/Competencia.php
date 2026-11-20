<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class Competencia extends ModuleModel
{
    protected $table = 'seguimiento_egresado.competencia';

    public $timestamps = false;

    protected $fillable = [
        'id_plan_curricular',
        'codigo',
        'nombre',
        'tipo',
        'descripcion',
        'nivel_esperado',
        'vigente',
    ];

    public function planCurricular()
    {
        return $this->belongsTo(PlanCurricular::class, 'id_plan_curricular');
    }
}
