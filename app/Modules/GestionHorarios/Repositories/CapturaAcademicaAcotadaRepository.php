<?php

namespace App\Modules\GestionHorarios\Repositories;

use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;
use LogicException;

/** Debe invocarse en la transacción de lectura ya autorizada. */
final class CapturaAcademicaAcotadaRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function leer(int $periodo, int $plan, array $autorizadas): array
    {
        if ($this->db->getDriverName() !== 'pgsql' || $this->db->transactionLevel() !== 1) {
            throw new LogicException('Se requiere la transaccion PostgreSQL de captura.');
        }
        $aislamiento = $this->db->selectOne("select current_setting('transaction_isolation') as nivel, current_setting('transaction_read_only') as solo_lectura");
        if ($aislamiento->nivel !== 'repeatable read' || $aislamiento->solo_lectura !== 'on') {
            throw new LogicException('La captura requiere REPEATABLE READ READ ONLY.');
        }
        $planReal = $this->db->table('core.plan_estudios')->where('id_plan', $plan)->whereIn('id_escuela', $autorizadas)->first(['id_plan', 'id_escuela']);
        if (! $planReal) {
            throw new AuthorizationException;
        }
        $periodoReal = $this->db->table('core.periodo_academico')->where('id_periodo', $periodo)->first(['id_periodo', 'codigo', 'estado']);
        if (! $periodoReal) {
            throw new DomainException('Periodo inexistente.');
        }
        $cursos = $this->db->table('core.curso_plan as cp')->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')->where('cp.id_plan', $plan)->orderBy('cp.id_curso_plan')->get(['cp.id_curso_plan', 'cp.id_plan', 'cp.id_curso', 'cp.ciclo', 'c.codigo', 'c.nombre', 'c.horas_teoria', 'c.horas_practica', 'c.horas_laboratorio'])->map(fn ($r) => (array) $r)->all();
        $ofertas = $this->db->table('gestion_horarios.oferta_curso as o')->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 'o.id_curso_plan')->where('cp.id_plan', $plan)->where('o.id_periodo', $periodo)->orderBy('o.id_oferta')->get(['o.id_oferta', 'o.id_periodo', 'o.id_curso_plan', 'o.tipo_oferta', 'o.estado'])->map(fn ($r) => (array) $r)->all();
        $secciones = $this->db->table('core.seccion as s')->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')->where('cp.id_plan', $plan)->where('s.id_periodo', $periodo)->orderBy('s.id_seccion')->get(['s.id_seccion', 's.id_periodo', 's.id_curso_plan', 's.codigo', 's.capacidad', 's.estado'])->map(fn ($r) => (array) $r)->all();
        $actividades = $this->db->table('gestion_horarios.actividad_academica as a')->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')->where('cp.id_plan', $plan)->where('s.id_periodo', $periodo)->orderBy('a.id_actividad')->get(['a.id_actividad', 'a.id_seccion', 'a.tipo_actividad', 'a.minutos_sesion'])->map(fn ($r) => (array) $r)->all();
        // La autorización sobre un plan no acredita visibilidad de docentes compartidos.
        $asignaciones = $this->db->table('gestion_horarios.asignacion_docente as ad')->join('gestion_horarios.actividad_academica as a', 'a.id_actividad', '=', 'ad.id_actividad')->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')->where('cp.id_plan', $plan)->where('s.id_periodo', $periodo)->orderBy('ad.id_asignacion')->get(['ad.id_asignacion', 'ad.id_actividad', 'ad.estado'])->map(fn ($r) => (array) $r)->all();
        $relaciones = null;
        if ($this->db->getSchemaBuilder()->hasTable('gestion_horarios.oferta_seccion')) {
            $relaciones = $this->db->table('gestion_horarios.oferta_seccion as os')->join('gestion_horarios.oferta_curso as o', 'o.id_oferta', '=', 'os.id_oferta')->join('core.seccion as s', 's.id_seccion', '=', 'os.id_seccion')->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 'o.id_curso_plan')->where('cp.id_plan', $plan)->where('o.id_periodo', $periodo)->where('s.id_periodo', $periodo)->whereColumn('s.id_curso_plan', 'o.id_curso_plan')->orderBy('os.id_oferta_seccion')->get(['os.id_oferta_seccion', 'os.id_oferta', 'os.id_seccion', 'os.estado'])->map(fn ($r) => (array) $r)->all();
        }
        $diagnosticos = [];
        if ($relaciones === null) {
            $diagnosticos[] = ['codigo' => 'ASOCIACION_OFERTA_SECCION_NO_DISPONIBLE'];
        } else {
            $inconsistentes = $this->db->table('gestion_horarios.oferta_seccion as os')
                ->join('gestion_horarios.oferta_curso as o', 'o.id_oferta', '=', 'os.id_oferta')
                ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 'o.id_curso_plan')
                ->leftJoin('core.seccion as s', 's.id_seccion', '=', 'os.id_seccion')
                ->where('cp.id_plan', $plan)->where('o.id_periodo', $periodo)->where('os.estado', 'VIGENTE')
                ->where(fn ($q) => $q->whereNull('s.id_seccion')->orWhere('s.id_periodo', '!=', $periodo)->orWhereColumn('s.id_curso_plan', '!=', 'o.id_curso_plan'))
                ->exists();
            if ($inconsistentes) {
                $diagnosticos[] = ['codigo' => 'ASOCIACION_OFERTA_SECCION_INCONSISTENTE'];
            }
            foreach ($ofertas as $oferta) {
                if (! array_filter($relaciones, fn ($r) => (string) $r['id_oferta'] === (string) $oferta['id_oferta'] && $r['estado'] === 'VIGENTE')) {
                    $diagnosticos[] = ['codigo' => 'OFERTA_SIN_SECCION', 'id_oferta' => $oferta['id_oferta']];
                }
            }
        }
        foreach ($secciones as $seccion) {
            $ids = array_column(array_filter($actividades, fn ($a) => (string) $a['id_seccion'] === (string) $seccion['id_seccion']), 'id_actividad');
            if (! $ids || ! array_filter($asignaciones, fn ($a) => in_array((string) $a['id_actividad'], array_map('strval', $ids), true) && $a['estado'] === 'CONFIRMADA')) {
                $diagnosticos[] = ['codigo' => 'SECCION_SIN_DOCENTE', 'id_seccion' => $seccion['id_seccion']];
            }
        }
        foreach ($actividades as $actividad) {
            if (! array_filter($asignaciones, fn ($a) => (string) $a['id_actividad'] === (string) $actividad['id_actividad'] && $a['estado'] === 'CONFIRMADA')) {
                $diagnosticos[] = ['codigo' => 'ACTIVIDAD_SIN_DOCENTE', 'id_actividad' => $actividad['id_actividad']];
            }
        }
        foreach (['IDENTIDAD_Y_ALCANCE_DOCENTE', 'DISPONIBILIDAD_EFECTIVA', 'POBLACION_COHORTES_Y_SUBGRUPOS', 'SESIONES_FISICAS_COMPARTIDAS', 'CALENDARIO_Y_RESTRICCIONES', 'PRIORIDAD_VERSIONADA', 'CONSISTENCIA_ENTRE_ESCUELAS'] as $codigo) {
            $diagnosticos[] = ['codigo' => 'CONTRATO_PENDIENTE', 'fuente' => $codigo];
        }

        return ['contrato' => 'FUENTES_ACADEMICAS_AUTORIZADAS_V1', 'ejecutable' => false, 'periodo' => (array) $periodoReal, 'escuela_id' => $planReal->id_escuela, 'plan_id' => $planReal->id_plan, 'cursos' => $cursos, 'ofertas' => $ofertas, 'secciones' => $secciones, 'oferta_seccion' => $relaciones, 'actividades' => $actividades, 'asignaciones' => $asignaciones, 'diagnosticos' => $diagnosticos];
    }
}
