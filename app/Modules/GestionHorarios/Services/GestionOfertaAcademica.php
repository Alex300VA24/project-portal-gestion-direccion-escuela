<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class GestionOfertaAcademica
{
    public function __construct(private readonly ConnectionInterface $db, private readonly ?ProveedorEscuelasAutorizadas $escuelas = null) {}

    public function crear(User $usuario, array $entrada): void
    {
        $this->autorizar($usuario);
        $datos = Validator::make($entrada, [
            'periodo' => ['required', 'integer', 'min:1'], 'curso' => ['required', 'integer', 'min:1'],
            'tipo' => ['required', 'in:REGULAR,REPETICION'], 'docentes' => ['required', 'integer', 'min:1', 'max:32767'],
        ])->validate();
        try {
            $this->db->transaction(function () use ($datos, $usuario) {
                (new AmbitoEscrituraAcademica($this->db, $this->escuelas))->autorizarCurso($usuario, (int) $datos['curso']);
                $this->db->statement('LOCK TABLE core.seccion IN SHARE MODE');
                if (! $this->db->table('core.periodo_academico')->where('id_periodo', $datos['periodo'])->exists()) {
                    throw ValidationException::withMessages(['periodo' => 'Seleccione un periodo válido.']);
                }
                if (! $this->db->table('core.curso_plan')->where('id_curso_plan', $datos['curso'])->exists()) {
                    throw ValidationException::withMessages(['curso' => 'Seleccione un curso del plan institucional.']);
                }
                if ($this->db->table('core.seccion')->where('id_periodo', $datos['periodo'])->where('id_curso_plan', $datos['curso'])->exists()) {
                    throw ValidationException::withMessages(['curso' => 'Este curso ya tiene secciones en el periodo; solicite una revisión antes de agregar otra oferta.']);
                }
                $this->autorizar($usuario);
                (new AmbitoEscrituraAcademica($this->db, $this->escuelas))->autorizarCurso($usuario, (int) $datos['curso']);
                $this->db->table('gestion_horarios.oferta_curso')->insert([
                    'id_periodo' => $datos['periodo'], 'id_curso_plan' => $datos['curso'], 'tipo_oferta' => $datos['tipo'],
                    'docentes_minimos' => $datos['docentes'], 'estado' => 'PLANIFICADA',
                ]);
            });
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '23505') {
                throw ValidationException::withMessages(['curso' => 'Ya existe una oferta de ese tipo para el curso y periodo.']);
            }
            throw $e;
        }
    }

    public function actualizar(User $usuario, int $id, array $entrada, string $estadoAnterior, int $docentesAnteriores): void
    {
        $this->autorizar($usuario);
        $datos = Validator::make($entrada, ['docentes' => ['required', 'integer', 'min:1', 'max:32767'], 'estado' => ['required', 'in:PLANIFICADA,OFERTADA,CERRADA']])->validate();
        $rechazada = $this->db->transaction(function () use ($usuario, $id, $datos, $estadoAnterior, $docentesAnteriores) {
            $cursoPlan = $this->db->table('gestion_horarios.oferta_curso')->where('id_oferta', $id)->value('id_curso_plan');
            (new AmbitoEscrituraAcademica($this->db, $this->escuelas))->autorizarCurso($usuario, (int) $cursoPlan);
            // Bloquea escrituras concurrentes incluso de módulos que no conocen nuestro bloqueo asesor.
            $this->db->statement('LOCK TABLE core.seccion, gestion_horarios.actividad_academica, gestion_horarios.detalle_horario, gestion_horarios.horario IN SHARE MODE');
            $oferta = $this->db->table('gestion_horarios.oferta_curso')->where('id_oferta', $id)->lockForUpdate()->first();
            if (! $oferta) {
                throw ValidationException::withMessages(['formulario' => 'La oferta ya no está disponible.']);
            }
            abort_unless((int) $oferta->id_curso_plan === (int) $cursoPlan, 403);
            if ($oferta->estado !== $estadoAnterior || (int) $oferta->docentes_minimos !== $docentesAnteriores) {
                throw ValidationException::withMessages(['formulario' => 'La oferta cambió mientras la consultaba. Cierre el formulario y ábralo de nuevo.']);
            }
            $transiciones = ['PLANIFICADA' => ['PLANIFICADA', 'OFERTADA'], 'OFERTADA' => ['OFERTADA', 'CERRADA'], 'CERRADA' => ['CERRADA']];
            if (! in_array($datos['estado'], $transiciones[$oferta->estado] ?? [], true)) {
                throw ValidationException::withMessages(['formulario' => 'La transición de estado solicitada no está permitida.']);
            }
            if ($oferta->estado === 'CERRADA' && ((int) $oferta->docentes_minimos !== (int) $datos['docentes'] || $datos['estado'] !== 'CERRADA')) {
                throw ValidationException::withMessages(['formulario' => 'Una oferta cerrada no se puede modificar.']);
            }
            if ((int) $oferta->docentes_minimos === (int) $datos['docentes'] && $oferta->estado === $datos['estado']) {
                return false;
            }
            $this->autorizar($usuario);
            (new AmbitoEscrituraAcademica($this->db, $this->escuelas))->autorizarCurso($usuario, (int) $cursoPlan);
            $tieneSecciones = $this->db->table('core.seccion')->where('id_periodo', $oferta->id_periodo)->where('id_curso_plan', $oferta->id_curso_plan)->exists();
            if ($tieneSecciones) {
                $this->db->table('gestion_horarios.revision_oferta_pendiente')->insert(['id_oferta' => $id, 'id_usuario' => $usuario->getKey(), 'motivo' => 'La oferta tiene secciones relacionadas; revisar sus actividades y horarios antes de modificarla.']);

                return true;
            }
            $this->db->table('gestion_horarios.oferta_curso')->where('id_oferta', $id)->update(['docentes_minimos' => $datos['docentes'], 'estado' => $datos['estado']]);

            return false;
        }, 3);
        if ($rechazada) {
            throw ValidationException::withMessages(['formulario' => 'No se pudo modificar la oferta porque tiene secciones relacionadas. Se registró una revisión pendiente.']);
        }
    }

    private function autorizar(User $usuario): void
    {
        (new AutorizacionGestionAcademica)->exigir($usuario);
    }
}
