<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class Cohorte extends ModuleModel
{
    protected $table = 'seguimiento_egresado.cohorte';

    public $timestamps = false;

    protected $fillable = [
        'codigo_promocion',
        'anio_ingreso',
        'semestre_ingreso',
        'total_ingresantes',
        'duracion_esperada_anios',
        'id_plan_curricular',
        'observacion',
    ];

    public function planCurricular()
    {
        return $this->belongsTo(PlanCurricular::class, 'id_plan_curricular');
    }

    public function egresados()
    {
        return $this->hasMany(Egresado::class, 'id_cohorte');
    }

    public function campanias()
    {
        return $this->belongsToMany(CampaniaSeguimiento::class, 'seguimiento_egresado.campania_cohorte', 'id_cohorte', 'id_campania');
    }

    public function evaluacionesOe()
    {
        return $this->hasMany(EvaluacionOe::class, 'id_cohorte');
    }

    public function evaluacionesCompetencia()
    {
        return $this->hasMany(EvaluacionCompetencia::class, 'id_cohorte');
    }

    /**
     * Total de egresados acreditados en la cohorte (EGRESADO, BACHILLER, TITULADO)
     */
    public function getEgresadosAcreditadosCountAttribute(): int
    {
        return $this->egresados()
            ->whereHas('estadoVigente', function ($q) {
                $q->whereHas('estadoAcademico', function ($q2) {
                    $q2->whereIn('codigo', ['EGRESADO', 'BACHILLER', 'TITULADO']);
                });
            })->count();
    }

    /**
     * Indicador I1 de la cohorte: (Total Egresados Acreditados / Total Ingresantes) * 100
     */
    public function getPorcentajeI1Attribute(): ?float
    {
        if ($this->total_ingresantes <= 0) {
            return null;
        }

        return round(($this->egresados_acreditados_count / $this->total_ingresantes) * 100, 2);
    }
}
