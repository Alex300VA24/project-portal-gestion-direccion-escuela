<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class PlanCurricular extends ModuleModel
{
    protected $table = 'seguimiento_egresado.plan_curricular';

    public $timestamps = false;

    protected $fillable = ['codigo_version', 'vigencia_desde', 'vigencia_hasta', 'estado'];

    public function cohortes()
    {
        return $this->hasMany(Cohorte::class, 'id_plan_curricular');
    }

    public function competencias()
    {
        return $this->hasMany(Competencia::class, 'id_plan_curricular');
    }

    public function objetivosEducacionales()
    {
        return $this->hasMany(ObjetivoEducacional::class, 'id_plan_curricular');
    }

    public function mejorasCurriculares()
    {
        return $this->hasMany(MejoraCurricular::class, 'id_plan_curricular');
    }
}
