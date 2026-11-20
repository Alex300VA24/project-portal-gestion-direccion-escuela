<?php

namespace App\Modules\SeguimientoEstudiante\Services\Tutoria;

use App\Modules\SeguimientoEstudiante\Services\SolicitudTutoriaService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AsignacionTutoriaService
{
    private const ASIGNACIONES = 'seguimiento_estudiante.tutor_asignacion';

    private const CAPACIDAD = 20;

    public function __construct(private readonly AvisoTutoriaService $avisos) {}

    public function ambitos(int $persona): Builder
    {
        $hoy = now('America/Lima')->toDateString();

        return DB::table('seguimiento_estudiante.coordinacion_ambito as ambito')
            ->join('core.periodo_academico as periodo', 'periodo.id_periodo', '=', 'ambito.id_periodo')
            ->join('core.escuela_profesional as escuela', 'escuela.id_escuela', '=', 'ambito.id_escuela')
            ->where('ambito.id_persona', $persona)
            ->where('periodo.estado', 'Activo')
            ->where('periodo.fecha_inicio', '<=', $hoy)
            ->where('periodo.fecha_fin', '>=', $hoy);
    }

    private function habilitados(int $escuela, int $periodo): Builder
    {
        return DB::table('seguimiento_estudiante.tutor_habilitado as habilitacion')
            ->join('core.docente as docente', 'docente.id_docente', '=', 'habilitacion.id_docente')
            ->where('habilitacion.id_escuela', $escuela)
            ->where('habilitacion.id_periodo', $periodo)
            ->where('docente.estado', 'Activo')
            ->whereNotExists(DB::table('seguimiento_estudiante.coordinacion_ambito as coordinacion')
                ->whereColumn('coordinacion.id_persona', 'docente.id_persona')
                ->whereColumn('coordinacion.id_escuela', 'habilitacion.id_escuela')
                ->whereColumn('coordinacion.id_periodo', 'habilitacion.id_periodo'));
    }

    private function activas(int $periodo): Builder
    {
        return DB::table(self::ASIGNACIONES)->where('id_periodo', $periodo)
            ->where('tipo', 'Tutoria')->where('estado', 'Activa');
    }

    private function estudiantes(int $escuela): Builder
    {
        return DB::table('core.estudiante as estudiante')
            ->where('estudiante.id_escuela', $escuela)
            ->where('estudiante.estado', 'Activo');
    }

    public function consultar(int $persona, int $escuela, int $periodo): array
    {
        $ambito = $this->ambitos($persona)->where('ambito.id_escuela', $escuela)->where('ambito.id_periodo', $periodo)
            ->first(['escuela.nombre as escuela', 'periodo.codigo as periodo']);
        abort_if($ambito === null, 404);
        $hoy = now('America/Lima')->toDateString();
        $actual = $this->activas($periodo)->whereColumn('id_estudiante', 'estudiante.id_estudiante')
            ->where(fn (Builder $consulta) => $consulta->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $hoy))
            ->orderBy('id_asignacion')->limit(1)->select('id_asignacion', 'id_docente');
        $estudiantes = $this->estudiantes($escuela)
            ->join('core.persona as persona', 'persona.id_persona', '=', 'estudiante.id_persona')
            ->leftJoinLateral($actual, 'actual')
            ->leftJoin('core.docente as tutor', 'tutor.id_docente', '=', 'actual.id_docente')
            ->orderBy('estudiante.codigo_universitario')->orderBy('estudiante.id_estudiante')
            ->paginate(20, [
                'estudiante.id_estudiante', 'estudiante.codigo_universitario', 'persona.nombres', 'persona.apellido_paterno',
                'persona.apellido_materno', 'actual.id_asignacion', 'tutor.codigo_docente',
            ]);
        $tutores = $this->habilitados($escuela, $periodo)
            ->join('core.persona as persona', 'persona.id_persona', '=', 'docente.id_persona')
            ->orderBy('persona.apellido_paterno')->orderBy('persona.nombres')
            ->get(['docente.id_docente', 'docente.codigo_docente', 'persona.nombres', 'persona.apellido_paterno', 'persona.apellido_materno']);

        return compact('ambito', 'estudiantes', 'tutores');
    }

    public function asignar(int $persona, int $cuenta, int $escuela, int $periodo, array $datos): void
    {
        DB::transaction(function () use ($persona, $cuenta, $escuela, $periodo, $datos): void {
            SolicitudTutoriaService::bloquearAgenda();
            $ambito = $this->ambitos($persona)->where('ambito.id_escuela', $escuela)->where('ambito.id_periodo', $periodo)
                ->sharedLock()->first(['ambito.id_persona']);
            abort_if($ambito === null, 404);
            $estudiante = $this->estudiantes($escuela)->where('estudiante.id_estudiante', $datos['id_estudiante'])
                ->lockForUpdate()->first(['estudiante.id_estudiante', 'estudiante.id_persona']);
            abort_if($estudiante === null, 404);

            $docente = $this->habilitados($escuela, $periodo)->where('docente.id_docente', $datos['id_docente'])
                ->sharedLock()->first(['docente.id_persona', 'docente.id_docente']);
            if ($docente === null || (int) $docente->id_persona === (int) $estudiante->id_persona) {
                throw ValidationException::withMessages(['id_docente' => 'Selecciona un docente habilitado para esta escuela y período.']);
            }

            $hoy = now('America/Lima')->toDateString();
            $activos = $this->activas($periodo)->where('id_estudiante', $estudiante->id_estudiante)->lockForUpdate()->get();
            $vigentes = $activos->filter(fn ($fila) => $fila->fecha_fin === null || $fila->fecha_fin >= $hoy);
            $anterior = $vigentes->first();
            if ($vigentes->count() > 1 || ($anterior && ($anterior->fecha_inicio ?? $anterior->fecha_asignacion) > $hoy)) {
                throw ValidationException::withMessages(['id_asignacion' => 'Revisa las asignaciones vigentes o futuras antes de reasignar.']);
            }
            if ((int) ($anterior?->id_asignacion ?? 0) !== (int) $datos['id_asignacion']) {
                throw ValidationException::withMessages(['id_asignacion' => 'La asignación cambió. Vuelve a consultar al estudiante.']);
            }
            if ($anterior && (int) $anterior->id_docente === (int) $docente->id_docente) {
                throw ValidationException::withMessages(['id_docente' => 'Selecciona un tutor distinto del actual.']);
            }

            $carga = $this->activas($periodo)->where('id_docente', $docente->id_docente)
                ->where(fn (Builder $consulta) => $consulta->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $hoy))
                ->distinct()->count('id_estudiante');
            if ($carga >= self::CAPACIDAD) {
                throw ValidationException::withMessages(['id_docente' => 'El tutor alcanzó el límite de 20 estudiantes en el período.']);
            }

            if ($anterior) {
                DB::table(self::ASIGNACIONES)->where('id_asignacion', $anterior->id_asignacion)->update([
                    'estado' => 'Finalizada', 'fecha_fin' => $hoy, 'causa_fin' => 'REASIGNACION', 'observacion' => $datos['motivo'],
                ]);
                $this->cancelarCitas($estudiante->id_estudiante, $periodo, $anterior->id_asignacion, $cuenta, 'Tutor reasignado. Debe acordarse una nueva cita.');
                $personaAnterior = DB::table('core.docente')->where('id_docente', $anterior->id_docente)->value('id_persona');
                $this->avisos->avisar($personaAnterior, 'Una de tus asignaciones de tutoría se reasignó.');
            }

            // Las filas heredadas con vencimiento conservan su fecha y pasan al historial.
            $vencidos = $activos->where('fecha_fin', '<', $hoy)->whereNotNull('fecha_fin')->pluck('id_asignacion');
            DB::table(self::ASIGNACIONES)->whereIn('id_asignacion', $vencidos)->update(['estado' => 'Finalizada']);

            DB::table(self::ASIGNACIONES)->insert([
                'id_estudiante' => $estudiante->id_estudiante, 'id_docente' => $docente->id_docente, 'id_periodo' => $periodo,
                'fecha_asignacion' => $hoy, 'fecha_inicio' => $hoy, 'id_usuario_asignador' => $cuenta, 'motivo' => $datos['motivo'],
            ]);
            $this->avisos->avisar($docente->id_persona, 'Tienes una nueva asignación de tutoría.');
            $this->avisos->avisar($estudiante->id_persona, $anterior ? 'Se actualizó tu tutor asignado.' : 'Ya tienes un tutor asignado.');
        });
    }

    public function finalizar(int $persona, int $cuenta, int $escuela, int $periodo, array $datos, CausasAsignacionService $causas): void
    {
        DB::transaction(function () use ($persona, $cuenta, $escuela, $periodo, $datos, $causas): void {
            SolicitudTutoriaService::bloquearAgenda();
            $ambito = $this->ambitos($persona)->where('ambito.id_escuela', $escuela)->where('ambito.id_periodo', $periodo)
                ->sharedLock()->first(['ambito.id_persona']);
            abort_if($ambito === null, 404);
            $estudiante = $this->estudiantes($escuela)->where('id_estudiante', $datos['id_estudiante'])
                ->lockForUpdate()->first(['id_estudiante', 'id_persona']);
            abort_if($estudiante === null, 404);
            $historial = DB::table(self::ASIGNACIONES)->where('id_estudiante', $estudiante->id_estudiante)
                ->where('id_periodo', $periodo)->where('tipo', 'Tutoria');
            $asignacion = (clone $historial)->where('id_asignacion', $datos['id_asignacion'])->lockForUpdate()->first();
            abort_if($asignacion === null, 404);

            $hoy = now('America/Lima')->toDateString();
            $activas = (clone $historial)->where('estado', 'Activa')
                ->where(fn (Builder $q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $hoy))
                ->lockForUpdate()->get(['id_asignacion', 'id_docente']);
            $reemplazo = (int) ($datos['reemplazo'] ?? 0);
            $mismoResultado = $reemplazo === 0 ? $activas->isEmpty()
                : $activas->count() === 1 && (int) $activas->first()->id_docente === $reemplazo;
            if ($mismoResultado && $asignacion->estado === 'Finalizada'
                && (int) $asignacion->id_usuario_finalizador === $cuenta
                && $asignacion->version_causa_fin === $datos['version_causa']
                && $asignacion->causa_fin === $datos['causa'] && $asignacion->observacion === $datos['motivo']) {
                return;
            }
            if ($activas->count() !== 1 || (int) $activas->first()->id_asignacion !== (int) $asignacion->id_asignacion
                || ($asignacion->fecha_inicio ?? $asignacion->fecha_asignacion) > $hoy) {
                throw ValidationException::withMessages(['id_asignacion' => 'La asignación vigente cambió. Vuelve a consultarla.']);
            }

            $causa = $causas->resolver($escuela, $periodo, $datos['version_causa'], $datos['causa']);
            if (($causa['efecto'] === 'REASIGNAR') !== ($reemplazo > 0)) {
                throw ValidationException::withMessages(['reemplazo' => 'Selecciona un reemplazo únicamente cuando lo exija la causa.']);
            }

            if ($reemplazo > 0) {
                $this->asignar($persona, $cuenta, $escuela, $periodo, $datos + ['id_docente' => $reemplazo]);
            }
            $historial->where('id_asignacion', $asignacion->id_asignacion)->update([
                'estado' => 'Finalizada',
                'fecha_fin' => $hoy,
                'causa_fin' => $datos['causa'],
                'observacion' => $datos['motivo'],
                'version_causa_fin' => $datos['version_causa'],
                'id_usuario_finalizador' => $cuenta,
            ]);
            if ($reemplazo > 0) {
                return;
            }
            $this->cancelarCitas($estudiante->id_estudiante, $periodo, $asignacion->id_asignacion, $cuenta, 'La asignación de tutoría finalizó. La solicitud queda pendiente.');
            $tutor = DB::table('core.docente')->where('id_docente', $asignacion->id_docente)->value('id_persona');
            foreach (array_unique([$estudiante->id_persona, $tutor, $persona]) as $destinatario) {
                $this->avisos->avisar($destinatario, 'Una asignación de tutoría finalizó y requiere revisión manual.');
            }
        });
    }

    private function cancelarCitas(int $estudiante, int $periodo, int $asignacion, int $cuenta, string $motivo): void
    {
        $solicitudes = DB::table('seguimiento_estudiante.solicitud_estudiante')
            ->where('id_estudiante', $estudiante)->where('id_periodo', $periodo)->where('tipo', 'Tutoria')
            ->whereIn('estado', ['Pendiente', 'Programada']);
        $programadas = (clone $solicitudes)->where('estado', 'Programada')->select('id_solicitud');
        $citas = DB::table('seguimiento_estudiante.atencion')
            ->where('id_asignacion', $asignacion)->whereIn('id_solicitud', $programadas)->get([
                'id_atencion', 'fecha_hora', 'modalidad', 'duracion_minutos', 'lugar',
            ]);

        foreach ($citas as $cita) {
            DB::table('seguimiento_estudiante.atencion_evento')->insert([
                'id_atencion' => $cita->id_atencion, 'id_usuario' => $cuenta, 'tipo' => 'cancelacion',
                'fecha_programada' => $cita->fecha_hora, 'modalidad' => $cita->modalidad,
                'duracion_minutos' => $cita->duracion_minutos, 'lugar' => $cita->lugar,
                'motivo' => $motivo,
            ]);
        }

        $solicitudes->increment('version', 1, ['estado' => 'Pendiente']);
    }
}
