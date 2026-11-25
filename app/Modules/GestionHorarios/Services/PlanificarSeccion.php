<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class PlanificarSeccion
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function guardar(User $usuario, int $periodo, int $plan, int $curso, string $codigo, mixed $capacidad, ?int $seccion = null, array $original = []): void
    {
        $permiso = new AutorizacionGestionAcademica;
        $permiso->exigir($usuario);
        if ($periodo <= 0 || $plan <= 0 || $curso <= 0) {
            throw ValidationException::withMessages(['seccion' => 'Seleccione un periodo, un plan y un curso válidos.']);
        }
        $codigo = mb_strtoupper(trim($codigo));
        Validator::make(compact('codigo', 'capacidad'), [
            'codigo' => ['required', 'string', 'max:30', 'regex:/^[\pL\pN]+(?:[ -][\pL\pN]+)*$/u'],
            'capacidad' => ['required', 'integer', 'between:1,2147483647'],
        ], [
            'codigo.regex' => 'Use letras o números, separados por espacios o guiones.',
            'capacidad.between' => 'Ingrese una capacidad positiva dentro del rango permitido.',
        ])->validate();

        try {
            $this->db->transaction(function () use ($usuario, $permiso, $periodo, $plan, $curso, $codigo, $capacidad, $seccion, $original) {
                $ambito = new AmbitoEscrituraAcademica($this->db);
                $ambito->autorizarCurso($usuario, $curso);
                abort_unless($this->db->table('core.curso_plan')->where('id_plan', $plan)->where('id_curso_plan', $curso)->exists(), 403);
                // Respeta el orden de bloqueo de oferta y asociación; impide altas concurrentes
                // antes de consultar duplicados y evita invertir los bloqueos con la edición de oferta.
                $this->db->statement('LOCK TABLE core.seccion IN SHARE ROW EXCLUSIVE MODE');
                $this->db->statement('LOCK TABLE gestion_horarios.actividad_academica, gestion_horarios.detalle_horario, gestion_horarios.horario IN SHARE MODE');
                $ofertas = $this->db->table('gestion_horarios.oferta_curso')->where('id_periodo', $periodo)
                    ->where('id_curso_plan', $curso)->sharedLock()->pluck('estado');
                if ($ofertas->isEmpty() || $ofertas->contains('CERRADA')) {
                    throw ValidationException::withMessages(['seccion' => 'Se requiere oferta abierta del curso. Revise las ofertas del periodo.']);
                }
                if ($this->db->table('gestion_horarios.horario')->where('id_periodo', $periodo)->whereIn('estado', ['APROBADO', 'PUBLICADO'])->exists()) {
                    throw ValidationException::withMessages(['seccion' => 'El periodo tiene horarios aprobados o publicados. Solicite su revisión.']);
                }
                $secciones = $this->db->table('core.seccion')->where('id_periodo', $periodo)->where('id_curso_plan', $curso);
                if ($seccion !== null) {
                    $actual = (clone $secciones)->where('id_seccion', $seccion)->lockForUpdate()->first();
                    abort_unless($actual, 404);
                    if ([$actual->codigo, $actual->capacidad, $actual->estado] !== $original) {
                        throw ValidationException::withMessages(['seccion' => 'La sección cambió. Cancele la edición y vuelva a abrirla.']);
                    }
                    if ($this->db->table('gestion_horarios.detalle_horario as d')
                        ->join('gestion_horarios.actividad_academica as a', 'a.id_actividad', '=', 'd.id_actividad')
                        ->where('a.id_seccion', $seccion)->exists()) {
                        throw ValidationException::withMessages(['seccion' => 'La sección ya forma parte de una versión de horario. Solicite una revisión antes de modificarla.']);
                    }
                }
                $duplicada = (clone $secciones)->when($seccion !== null, fn ($q) => $q->where('id_seccion', '<>', $seccion))
                    ->whereRaw('upper(trim(codigo)) = ?', [$codigo])->exists();
                if ($duplicada) {
                    throw ValidationException::withMessages(['codigo' => 'Ya existe esa sección para el curso y periodo.']);
                }
                $permiso->exigir($usuario);
                $ambito->autorizarCurso($usuario, $curso);
                $valores = ['codigo' => $codigo, 'capacidad' => (int) $capacidad];
                if ($seccion === null) {
                    $secciones->insert($valores + ['id_periodo' => $periodo, 'id_curso_plan' => $curso, 'estado' => 'ACTIVA']);
                } else {
                    $secciones->where('id_seccion', $seccion)->update($valores);
                }
            }, 3);
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) !== '23505') {
                throw $e;
            }
            throw ValidationException::withMessages(['codigo' => 'Ya existe esa sección para el curso y periodo.']);
        }
    }
}
