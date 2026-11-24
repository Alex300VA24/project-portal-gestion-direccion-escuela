<?php

namespace App\Modules\SeguimientoEgresado\Services;

use App\Modules\SeguimientoEgresado\Models\CampaniaSeguimiento;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\EvaluacionOe;
use App\Modules\SeguimientoEgresado\Models\MejoraCurricular;
use App\Modules\SeguimientoEgresado\Models\ObjetivoEducacional;
use Illuminate\Support\Facades\DB;

class IndicadoresService
{
    /**
     * I1 · Tasa de graduación / egresados por cohorte de ingreso
     * Numerador: Egresados acreditados (EGRESADO, BACHILLER, TITULADO)
     * Denominador: Total de ingresantes de la cohorte (denominador oficial)
     */
    public function getIndicadorI1(?int $cohorteId = null): array
    {
        if (DB::getDriverName() === 'pgsql') {
            $query = DB::table('seguimiento_egresado.vw_i1_cohorte_actual');
            if ($cohorteId) {
                $query->where('id_cohorte', $cohorteId);
            }
            $registros = $query->orderBy('anio_ingreso', 'desc')->get();
        } else {
            // Respaldo Eloquent para SQLite / pruebas
            $query = Cohorte::query();
            if ($cohorteId) {
                $query->where('id', $cohorteId);
            }
            $registros = $query->orderByDesc('anio_ingreso')->get()->map(function ($c) {
                return (object) [
                    'id_cohorte' => $c->id,
                    'codigo_promocion' => $c->codigo_promocion,
                    'anio_ingreso' => $c->anio_ingreso,
                    'total_egresados' => $c->egresados_acreditados_count,
                    'total_ingresantes' => $c->total_ingresantes,
                    'porcentaje' => $c->porcentaje_i1 ?? 0,
                ];
            });
        }

        return [
            'codigo' => 'I1',
            'nombre' => 'Egresados por promoción / Cohorte',
            'unidad' => '%',
            'fuente' => 'Padrón oficial y registro de ingresantes (M1)',
            'data' => $registros->map(function ($r) {
                return [
                    'id_cohorte' => $r->id_cohorte,
                    'codigo_promocion' => $r->codigo_promocion,
                    'anio_ingreso' => $r->anio_ingreso,
                    'numerador' => (int) $r->total_egresados,
                    'denominador' => (int) $r->total_ingresantes,
                    'resultado' => $r->total_ingresantes > 0 ? (float) $r->porcentaje : null,
                    'estado' => $r->total_ingresantes > 0 ? 'CALCULADO' : 'SIN_DATOS',
                ];
            })->toArray(),
        ];
    }

    /**
     * I2 · Empleabilidad en la carrera
     * Numerador: Egresados que trabajan y su trabajo está relacionado con la carrera
     * Denominador: Total de encuestados elegibles
     */
    public function getIndicadorI2(?int $campaniaId = null): array
    {
        if (DB::getDriverName() === 'pgsql') {
            $query = DB::table('seguimiento_egresado.vw_i2_campania');
            if ($campaniaId) {
                $query->where('id_campania', $campaniaId);
            }
            $registros = $query->get();
        } else {
            $query = CampaniaSeguimiento::with('invitaciones.medicion.situacionLaboral');
            if ($campaniaId) {
                $query->where('id', $campaniaId);
            }
            $registros = $query->get()->map(function ($c) {
                $mediciones = $c->invitaciones->pluck('medicion')->filter();
                $encuestados = $mediciones->count();
                $relacionados = $mediciones->filter(fn ($m) => $m->trabajo_relacionado && ($m->id_situacion_laboral == 1))->count();

                return (object) [
                    'id_campania' => $c->id,
                    'encuestados' => $encuestados,
                    'trabajan_en_carrera' => $relacionados,
                    'i2_pct' => $encuestados > 0 ? round(($relacionados / $encuestados) * 100, 2) : 0,
                ];
            });
        }

        return [
            'codigo' => 'I2',
            'nombre' => 'Empleabilidad en carrera',
            'unidad' => '%',
            'fuente' => 'Encuestas de seguimiento profesional y mediciones laborales (Álvaro / Diego)',
            'data' => $registros->map(function ($r) {
                return [
                    'id_campania' => $r->id_campania,
                    'numerador' => (int) $r->trabajan_en_carrera,
                    'denominador' => (int) $r->encuestados,
                    'resultado' => $r->encuestados > 0 ? (float) $r->i2_pct : null,
                    'estado' => $r->encuestados > 0 ? 'CALCULADO' : 'SIN_MEDICION',
                ];
            })->toArray(),
        ];
    }

    /**
     * I3 · Logro de Objetivos Educacionales (OE)
     * Regla oficial del contrato Sprint 3:
     * El denominador oficial es el TOTAL de objetivos educacionales del programa/plan,
     * no únicamente los objetivos evaluados en el periodo.
     */
    public function getIndicadorI3(?int $cohorteId = null, ?string $periodo = null): array
    {
        $totalOePrograma = ObjetivoEducacional::where('vigente', true)->count();

        if (DB::getDriverName() === 'pgsql') {
            $query = DB::table('seguimiento_egresado.vw_i3_oe');
            if ($cohorteId) {
                $query->where('id_cohorte', $cohorteId);
            }
            if ($periodo) {
                $query->where('periodo', $periodo);
            }
            $evaluaciones = $query->get();
        } else {
            $query = EvaluacionOe::where('estado', 'VALIDADA');
            if ($cohorteId) {
                $query->where('id_cohorte', $cohorteId);
            }
            if ($periodo) {
                $query->where('periodo', $periodo);
            }
            $evaluaciones = $query->get()->groupBy(fn ($item) => $item->id_cohorte.'_'.$item->periodo)->map(function ($group) {
                $primero = $group->first();
                $logrados = $group->filter(fn ($e) => in_array($e->id_nivel_logro, [1, 2]))->count();

                return (object) [
                    'id_cohorte' => $primero->id_cohorte,
                    'periodo' => $primero->periodo,
                    'oe_evaluados' => $group->count(),
                    'oe_logrados' => $logrados,
                    'i3_pct' => $group->count() > 0 ? round(($logrados / $group->count()) * 100, 2) : 0,
                ];
            })->values();
        }

        return [
            'codigo' => 'I3',
            'nombre' => 'Cumplimiento de Objetivos Educacionales',
            'unidad' => '%',
            'fuente' => 'Evaluaciones de OE validadas por Dirección de Escuela (Diego)',
            'total_oe_programa' => $totalOePrograma,
            'data' => $evaluaciones->map(function ($r) use ($totalOePrograma) {
                $denominadorOficial = $totalOePrograma > 0 ? $totalOePrograma : (int) $r->oe_evaluados;
                $pctOficial = $denominadorOficial > 0 ? round((((int) $r->oe_logrados) / $denominadorOficial) * 100, 2) : 0;

                return [
                    'id_cohorte' => $r->id_cohorte,
                    'periodo' => $r->periodo,
                    'oe_evaluados' => (int) $r->oe_evaluados,
                    'numerador' => (int) $r->oe_logrados,
                    'denominador' => $denominadorOficial,
                    'resultado' => $pctOficial,
                    'resultado_sobre_evaluados' => (float) $r->i3_pct,
                    'estado' => $denominadorOficial > 0 ? 'CALCULADO' : 'SIN_DATOS',
                ];
            })->toArray(),
        ];
    }

    /**
     * I4 · Satisfacción de egresados con la formación
     * Denominador: Egresados con valoración válida (excluye ausencias)
     */
    public function getIndicadorI4(?int $campaniaId = null): array
    {
        if (DB::getDriverName() === 'pgsql') {
            $query = DB::table('seguimiento_egresado.vw_i4_campania');
            if ($campaniaId) {
                $query->where('id_campania', $campaniaId);
            }
            $registros = $query->get();
        } else {
            $registros = collect();
        }

        return [
            'codigo' => 'I4',
            'nombre' => 'Satisfacción de egresados',
            'unidad' => '%',
            'fuente' => 'Módulo de satisfacción del egresado (Álvaro)',
            'data' => $registros->map(function ($r) {
                return [
                    'id_campania' => $r->id_campania,
                    'mediciones_totales' => (int) $r->mediciones,
                    'numerador' => (int) $r->satisfechos,
                    'denominador' => (int) $r->valoraciones,
                    'resultado' => $r->valoraciones > 0 ? (float) $r->i4_pct : null,
                    'estado' => $r->valoraciones > 0 ? 'CALCULADO' : 'SIN_MEDICION',
                ];
            })->toArray(),
        ];
    }

    /**
     * I5 · Satisfacción de empleadores
     * Excluye encuestas generales de mercado (contexto = 'DESEMPENO')
     */
    public function getIndicadorI5(?int $campaniaId = null): array
    {
        if (DB::getDriverName() === 'pgsql') {
            $query = DB::table('seguimiento_egresado.vw_i5_campania');
            if ($campaniaId) {
                $query->where('id_campania', $campaniaId);
            }
            $registros = $query->get();
        } else {
            $registros = collect();
        }

        return [
            'codigo' => 'I5',
            'nombre' => 'Satisfacción de empleadores',
            'unidad' => '%',
            'fuente' => 'Encuesta empresarial de desempeño por token (Jeamir)',
            'data' => $registros->map(function ($r) {
                return [
                    'id_campania' => $r->id_campania,
                    'numerador' => (int) $r->satisfechos,
                    'denominador' => (int) $r->valoraciones,
                    'resultado' => $r->valoraciones > 0 ? (float) $r->i5_pct : null,
                    'estado' => $r->valoraciones > 0 ? 'CALCULADO' : 'SIN_DATOS',
                ];
            })->toArray(),
        ];
    }

    /**
     * I6 · Mejoras curriculares implementadas
     * Conteo absoluto de mejoras con evidencia y origen del seguimiento
     */
    public function getIndicadorI6(?int $anio = null): array
    {
        if (DB::getDriverName() === 'pgsql') {
            $query = DB::table('seguimiento_egresado.vw_i6_anual');
            if ($anio) {
                $query->where('anio', $anio);
            }
            $registros = $query->orderBy('anio', 'desc')->get();
        } else {
            $query = MejoraCurricular::where('estado', 'IMPLEMENTADA')->where('origen_seguimiento', true);
            $registros = $query->get()->groupBy(fn ($m) => date('Y', strtotime($m->fecha_implementacion)))->map(function ($group, $year) {
                return (object) [
                    'anio' => (int) $year,
                    'mejoras_implementadas' => $group->count(),
                ];
            })->values();
        }

        return [
            'codigo' => 'I6',
            'nombre' => 'Mejoras curriculares implementadas',
            'unidad' => 'cantidad',
            'fuente' => 'Planes de mejora y resoluciones de Consejo de Facultad (Diego)',
            'data' => $registros->map(function ($r) {
                return [
                    'anio' => (int) $r->anio,
                    'resultado' => (int) $r->mejoras_implementadas,
                    'estado' => 'CALCULADO',
                ];
            })->toArray(),
        ];
    }

    /**
     * Resumen de cobertura de campaña
     */
    public function getCoberturaCampania(?int $campaniaId = null): array
    {
        if (DB::getDriverName() === 'pgsql') {
            $query = DB::table('seguimiento_egresado.vw_cobertura_campania');
            if ($campaniaId) {
                $query->where('id_campania', $campaniaId);
            }

            return $query->get()->toArray();
        }

        return [];
    }

    /**
     * Historial de seguimiento por egresado
     */
    public function getHistorialSeguimiento(int $egresadoId): array
    {
        if (DB::getDriverName() === 'pgsql') {
            return DB::table('seguimiento_egresado.vw_historial_seguimiento')
                ->where('id_egresado', $egresadoId)
                ->orderBy('periodo', 'desc')
                ->get()
                ->toArray();
        }

        return [];
    }
}
