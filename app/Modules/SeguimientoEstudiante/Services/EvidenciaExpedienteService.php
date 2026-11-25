<?php

namespace App\Modules\SeguimientoEstudiante\Services;

use App\Models\User;
use App\Modules\SeguimientoEstudiante\Policies\ExpedientePolicy;
use App\Shared\Models\Core\Estudiante;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EvidenciaExpedienteService
{
    public function __construct(private readonly ExpedientePolicy $policy) {}

    public function consultar(User $cuenta, int $persona, Estudiante $estudiante): array
    {
        $alertas = null;
        $situaciones = null;
        if ((int) $estudiante->id_persona === $persona || ! $cuenta->can('seguimiento-estudiante.tutoria.view')) {
            return compact('alertas', 'situaciones');
        }

        if ($cuenta->can('seguimiento-estudiante.alerta.view')) {
            $alertas = $this->enAmbito('seguimiento_estudiante.alerta_sat', $persona, $estudiante)
                ->join('seguimiento_estudiante.parametro_sat as criterio', 'criterio.id_parametro', '=', 'registro.id_parametro')
                ->orderByDesc('registro.generada_en')
                ->orderByDesc('registro.id_alerta')
                ->paginate(15, [
                    'criterio.codigo', 'registro.valor_detectado', 'registro.estado', 'registro.generada_en',
                    'periodo.codigo as periodo',
                ], 'alertas')->withQueryString();
        }

        if ($cuenta->can('seguimiento-estudiante.continuidad.view')) {
            $situaciones = $this->enAmbito('seguimiento_estudiante.situacion_academica', $persona, $estudiante)
                ->orderByDesc('registro.calculado_en')
                ->orderByDesc('registro.id_situacion')
                ->paginate(15, [
                    'registro.promedio_ponderado', 'registro.condicion', 'registro.calculado_en',
                    'periodo.codigo as periodo',
                ], 'situaciones')->withQueryString();
        }

        return compact('alertas', 'situaciones');
    }

    private function enAmbito(string $tabla, int $persona, Estudiante $estudiante): Builder
    {
        return DB::table($tabla.' as registro')
            ->join('core.periodo_academico as periodo', 'periodo.id_periodo', '=', 'registro.id_periodo')
            ->where('registro.id_estudiante', $estudiante->getKey())
            ->whereExists($this->policy->asignacionesVigentes($persona)
                ->whereColumn('asignacion.id_estudiante', 'registro.id_estudiante')
                ->whereColumn('asignacion.id_periodo', 'registro.id_periodo'));
    }
}
