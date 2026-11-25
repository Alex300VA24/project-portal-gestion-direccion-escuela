<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Escritura de prueba aislada; no incorporar al PR institucional. */
final class ReasignarDocenteDemo
{
    public function guardar(User $usuario, int $plan, int $periodo, int $asignacion, int $anterior, int $nuevo): void
    {
        abort_unless(ConsultaLocalDemostracion::permitida($usuario), 403);
        DB::transaction(function () use ($usuario, $plan, $periodo, $asignacion, $anterior, $nuevo) {
            $db = DB::connection();
            $db->statement('LOCK TABLE core.seccion, gestion_horarios.actividad_academica, gestion_horarios.detalle_horario, gestion_horarios.horario IN SHARE MODE');
            $db->statement('LOCK TABLE gestion_horarios.asignacion_docente IN SHARE ROW EXCLUSIVE MODE');
            abort_unless(ConsultaLocalDemostracion::permitida($usuario), 403);
            $fila = $db->table('gestion_horarios.asignacion_docente as ad')
                ->join('gestion_horarios.actividad_academica as a', 'a.id_actividad', '=', 'ad.id_actividad')
                ->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')
                ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
                ->where('ad.id_asignacion', $asignacion)->where('s.id_periodo', $periodo)
                ->where('cp.id_plan', $plan)->where('s.estado', 'ACTIVA')
                ->first(['ad.*', 's.id_curso_plan']);
            abort_unless($fila, 404);
            (new AmbitoEscrituraAcademica($db))->autorizarCurso($usuario, (int) $fila->id_curso_plan);
            if ($fila->estado !== 'PROPUESTA' || (int) $fila->id_docente !== $anterior || $fila->id_apertura !== null) {
                $this->rechazar('La propuesta cambió o tiene una apertura institucional. Actualice la consulta.');
            }
            if ($nuevo === $anterior || $nuevo <= 0) {
                $this->rechazar('Seleccione un docente diferente.');
            }
            $docente = $db->table('core.docente as d')->join('core.persona as p', 'p.id_persona', '=', 'd.id_persona')
                ->where('d.id_docente', $nuevo)->where('p.tipo_documento', 'QA')->where('d.codigo_docente', 'like', 'QA-%')
                ->whereRaw('upper(d.estado) = ?', ['ACTIVO'])->first(['d.id_docente']);
            if (! $docente) {
                $this->rechazar('El docente no está activo en el catálogo de prueba.');
            }
            if ($db->table('gestion_horarios.horario')->where('id_periodo', $periodo)->whereIn('estado', ['APROBADO', 'PUBLICADO'])->exists()
                || $db->table('gestion_horarios.detalle_horario')->where('id_actividad', $fila->id_actividad)->exists()) {
                $this->rechazar('No se puede alterar una actividad vinculada a un horario o a un periodo aprobado o publicado.');
            }
            $ofertas = $db->table('gestion_horarios.oferta_curso')->where('id_periodo', $periodo)
                ->where('id_curso_plan', $fila->id_curso_plan)->sharedLock()->pluck('estado');
            if ($ofertas->count() !== 1 || $ofertas->contains('CERRADA')) {
                $this->rechazar('El curso no tiene oferta abierta inequívoca.');
            }
            $activas = $db->table('gestion_horarios.asignacion_docente')->where('id_actividad', $fila->id_actividad)
                ->whereIn('estado', ['PROPUESTA', 'CONFIRMADA'])->get();
            if ($activas->count() !== 1 || (int) $activas->first()->id_asignacion !== $asignacion) {
                $this->rechazar('La actividad tiene varias asignaciones activas; requiere revisión manual.');
            }
            $db->table('gestion_horarios.asignacion_docente')->where('id_asignacion', $asignacion)->update(['estado' => 'REASIGNADA']);
            $db->table('gestion_horarios.asignacion_docente')->insert([
                'id_actividad' => $fila->id_actividad, 'id_docente' => $nuevo, 'estado' => 'PROPUESTA',
                'observacion' => 'Reasignación de prueba desde propuesta '.$asignacion,
            ]);
        }, 3);
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['docenteNuevo' => $mensaje]);
    }
}
