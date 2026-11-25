<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;

final class GestionOfertaSeccion
{
    public function __construct(private readonly ConnectionInterface $db, private readonly ?ProveedorEscuelasAutorizadas $escuelas = null, private readonly ?DistribucionAutomaticaLocal $automatico = null) {}

    public function asociar(User $usuario, int $periodo, int $seccion, int $oferta, ?int $asociacionEsperada): void
    {
        (new AutorizacionGestionAcademica)->exigir($usuario);
        if ($periodo <= 0 || $seccion <= 0 || $oferta <= 0 || ($asociacionEsperada !== null && $asociacionEsperada <= 0)) {
            throw ValidationException::withMessages(['asociacion' => 'Seleccione una sección y una oferta válidas.']);
        }
        if (! $this->db->getSchemaBuilder()->hasTable('gestion_horarios.oferta_seccion')) {
            throw ValidationException::withMessages(['asociacion' => 'La asociación por tipo de oferta no está disponible: falta la actualización institucional del módulo.']);
        }
        $this->db->transaction(function () use ($usuario, $periodo, $seccion, $oferta, $asociacionEsperada) {
            $cursoPlan = $this->db->table('core.seccion')->where('id_seccion', $seccion)->value('id_curso_plan');
            (new AmbitoEscrituraAcademica($this->db, $this->escuelas))->autorizarCurso($usuario, (int) $cursoPlan);
            if (DistribucionAutomaticaLocal::flujoHabilitado()) {
                $plan = (int) $this->db->table('core.curso_plan')->where('id_curso_plan', $cursoPlan)->value('id_plan');
                if (! $this->automatico?->autorizaEscritura($periodo, $plan)) {
                    throw ValidationException::withMessages(['asociacion' => 'Utilice la planificación automática con vacantes consolidadas.']);
                }
            }
            // Bloquea solo la fila compartida y después las tablas propias, en el orden usado por gestión de oferta.
            $s = $this->db->table('core.seccion')->where('id_seccion', $seccion)->sharedLock()->first();
            abort_unless($s && (int) $s->id_curso_plan === (int) $cursoPlan, 403);
            $this->db->statement('LOCK TABLE gestion_horarios.actividad_academica, gestion_horarios.detalle_horario, gestion_horarios.horario IN SHARE MODE');
            $this->db->select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['gestion_horarios.oferta_seccion:'.$seccion]);
            $o = $this->db->table('gestion_horarios.oferta_curso')->where('id_oferta', $oferta)->lockForUpdate()->first();
            if (! $s || ! $o || (int) $s->id_periodo !== $periodo || (int) $o->id_periodo !== $periodo || (int) $s->id_curso_plan !== (int) $o->id_curso_plan || $o->estado === 'CERRADA') {
                throw ValidationException::withMessages(['asociacion' => 'La oferta y la sección deben corresponder al mismo curso y periodo, y la oferta debe estar abierta.']);
            }
            $actual = $this->db->table('gestion_horarios.oferta_seccion')->where('id_seccion', $seccion)->where('estado', 'VIGENTE')->lockForUpdate()->first();
            if (($actual ? (int) $actual->id_oferta_seccion : null) !== $asociacionEsperada) {
                throw ValidationException::withMessages(['asociacion' => 'La asociación cambió. Actualice la pantalla antes de continuar.']);
            }
            if ($actual && (int) $actual->id_oferta === $oferta) {
                return;
            }
            if ($this->db->table('gestion_horarios.horario')->where('id_periodo', $periodo)->whereIn('estado', ['APROBADO', 'PUBLICADO'])->exists()) {
                throw ValidationException::withMessages(['asociacion' => 'El periodo tiene horarios aprobados o publicados. Solicite una revisión antes de cambiar asociaciones.']);
            }
            (new AutorizacionGestionAcademica)->exigir($usuario);
            (new AmbitoEscrituraAcademica($this->db, $this->escuelas))->autorizarCurso($usuario, (int) $cursoPlan);
            if ($actual) {
                $this->db->table('gestion_horarios.oferta_seccion')->where('id_oferta_seccion', $actual->id_oferta_seccion)->update(['estado' => 'CERRADA', 'cerrada_por' => $usuario->getKey(), 'cerrada_en' => $this->db->raw('clock_timestamp()'), 'motivo_cierre' => 'Cambio de oferta asociado a la sección.']);
            }
            $this->db->table('gestion_horarios.oferta_seccion')->insert(['id_oferta' => $oferta, 'id_seccion' => $seccion, 'registrada_por' => $usuario->getKey()]);
        }, 3);
    }
}
