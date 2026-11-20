<?php

namespace App\Modules\SeguimientoEstudiante\Policies;

use App\Models\User;
use App\Shared\Models\Core\Estudiante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final class ExpedientePolicy
{
    /** @return Builder<Estudiante> */
    public function visibles(User $user, int $persona): Builder
    {
        return Estudiante::query()->where(function (Builder $scope) use ($user, $persona): void {
            $scope->where('core.estudiante.id_persona', $persona);

            if ($user->can('seguimiento-estudiante.tutoria.view')) {
                $scope->orWhereExists($this->asignacionesVigentes($persona)
                    ->whereColumn('asignacion.id_estudiante', 'core.estudiante.id_estudiante'));
            }
        });
    }

    public function asignacionesVigentes(int $persona): QueryBuilder
    {
        return $this->tutoriasVigentes()->where('docente.id_persona', $persona);
    }

    public function tutoriasVigentes(): QueryBuilder
    {
        $hoy = now('America/Lima')->toDateString();

        return DB::table('seguimiento_estudiante.tutor_asignacion as asignacion')
            ->selectRaw('1')
            ->join('core.docente as docente', 'docente.id_docente', '=', 'asignacion.id_docente')
            ->join('core.periodo_academico as periodo', 'periodo.id_periodo', '=', 'asignacion.id_periodo')
            ->where('docente.estado', 'Activo')
            ->where('asignacion.tipo', 'Tutoria')
            ->where('asignacion.estado', 'Activa')
            ->whereRaw('COALESCE(asignacion.fecha_inicio, asignacion.fecha_asignacion) <= ?', [$hoy])
            ->where(function (QueryBuilder $vigencia) use ($hoy): void {
                $vigencia->whereNull('asignacion.fecha_fin')->orWhere('asignacion.fecha_fin', '>=', $hoy);
            })
            ->where('periodo.estado', 'Activo')
            ->where('periodo.fecha_inicio', '<=', $hoy)
            ->where('periodo.fecha_fin', '>=', $hoy);
    }

    public function puedeSolicitar(User $user, int $persona, Estudiante $estudiante): bool
    {
        return $user->can('seguimiento-estudiante.solicitud.create')
            && (int) $estudiante->id_persona === $persona
            && $estudiante->estado === 'Activo';
    }
}
