<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use App\Modules\GestionHorarios\Demand\AjustesDemanda;
use App\Modules\GestionHorarios\Demand\InstantaneaDemanda;
use App\Modules\GestionHorarios\Demand\RepositorioDemandaPostgres;
use App\Shared\Contracts\CierreDemandaPrematricula;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Ensayo local: propuesta completa por plan, sin asignar estudiantes ni horarios. */
final class DistribucionAutomaticaLocal
{
    private ?array $escritura = null;

    public static function esBaseIntegracion(ConnectionInterface $db): bool
    {
        return $db->getDriverName() === 'pgsql' && $db->getDatabaseName() === 'portal_integracion_pm_gh';
    }

    public function autorizaEscritura(int $periodo, int $plan): bool
    {
        return $this->escritura === [$periodo, $plan] && $this->db->transactionLevel() > 0;
    }

    private const AMBIENTES = ['R01', 'R02', 'R03', 'R04', 'R05', 'R06', 'R07', 'R08'];

    public function __construct(private readonly ConnectionInterface $db) {}

    public function disponible(): bool
    {
        return app()->environment('local', 'testing') && $this->db->getDriverName() === 'pgsql'
            && in_array($this->db->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)
            && (string) $this->db->getConfig('port') === '5433'
            && $this->db->getDatabaseName() === 'portal_integracion_pm_gh'
            && $this->db->selectOne('select current_database() as nombre')->nombre === 'portal_integracion_pm_gh';
    }

    /** Solo cálculos. Conserva códigos e identidades existentes, no elimina filas. */
    public static function calcular(int $total, bool $aula, bool $lab, array $ambientes, array $actuales, string $nombre): array
    {
        if (preg_match('/\b(TESIS|ASESOR[IÍ]A|TRABAJO DE INVESTIGACI[OÓ]N|PROYECTO INTEGRADOR)\b/iu', $nombre)) {
            throw new InvalidArgumentException('Grupo especial: requiere revisión académica.');
        }
        $codigos = array_column($actuales, 'codigo');
        if (count($codigos) !== count(array_unique($codigos)) || array_filter($actuales, fn ($s) => $s['estado'] !== 'ACTIVA' || ! preg_match('/^[A-Z]$/D', $s['codigo']) || (int) $s['capacidad'] <= 0)) {
            throw new InvalidArgumentException('Secciones especiales, inactivas o con capacidad inválida.');
        }
        $plan = PlanificacionPorAforo::calcular($total, $aula, $lab, $ambientes, count($actuales));
        $libres = array_values(array_diff(range('A', 'Z'), $codigos));
        $filas = [];
        foreach ($plan['secciones'] as $i => $s) {
            $anterior = $actuales[$i] ?? null;
            $accion = $anterior === null ? 'crear' : ((int) $anterior['capacidad'] === $s['capacidad'] ? 'conservar' : 'ajustar');
            $filas[] = $s + ['codigo' => $anterior['codigo'] ?? array_shift($libres), 'id_seccion' => $anterior['id_seccion'] ?? null, 'accion' => $accion];
        }
        $plan['secciones'] = $filas;
        return $plan;
    }

    public function previsualizar(User $usuario, int $periodo, int $plan): array
    {
        return $this->db->transaction(fn () => $this->capturar($usuario, $periodo, $plan));
    }

    public function consolidar(User $usuario, int $periodo, int $plan, string $huella): void
    {
        $this->bajoBloqueos($usuario, $periodo, $plan, $huella, function (array $actual) use ($usuario, $periodo, $plan) {
            if ($actual['estadoConsolidacion'] !== 'sin_consolidar') self::rechazar('La demanda no está lista para consolidarse o ya fue consolidada.');
            $demanda = (new RepositorioDemandaPostgres($this->db))->vigente($periodo);
            $this->db->table('gestion_horarios.consolidacion_vacantes')->insert([
                'id_periodo' => $periodo, 'id_plan' => $plan, 'id_demanda' => $demanda->id_demanda,
                'huella' => $actual['huellaVacantes'], 'detalle' => json_encode($actual, JSON_THROW_ON_ERROR),
                'id_usuario' => $usuario->getKey(),
            ]);
        });
    }

    public function aplicar(User $usuario, int $periodo, int $plan, string $huella): array
    {
        return $this->bajoBloqueos($usuario, $periodo, $plan, $huella, function (array $actual) use ($usuario, $periodo, $plan) {
            if (! in_array($actual['estadoConsolidacion'], ['consolidada', 'secciones_verificadas'], true)) self::rechazar('Consolide las vacantes antes de crear secciones.');
            $this->escritura = [$periodo, $plan];
            try { return $this->crearSecciones($usuario, $periodo, $plan, $actual); }
            finally { $this->escritura = null; }
        });
    }

    private function bajoBloqueos(User $usuario, int $periodo, int $plan, string $huella, callable $operacion): mixed
    {
        abort_unless($this->disponible(), 403);
        (new AutorizacionGestionAcademica)->exigir($usuario);
        abort_unless(app(AmbitoPlanes::class)->contiene($usuario, $plan), 403);
        if (! app()->bound(CierreDemandaPrematricula::class)) self::rechazar('Falta el proveedor local de Prematrícula.');
        $fuente = app(CierreDemandaPrematricula::class);
        $repo = new RepositorioDemandaPostgres($this->db);
        return $repo->conBloqueo($periodo, function () use ($usuario, $periodo, $plan, $huella, $fuente, $repo, $operacion) {
            return $fuente->conBloqueo(function () use ($usuario, $periodo, $plan, $huella, $fuente, $repo, $operacion) {
                $this->db->statement('LOCK TABLE core.seccion IN SHARE ROW EXCLUSIVE MODE');
                $this->db->statement('LOCK TABLE gestion_horarios.actividad_academica, gestion_horarios.detalle_horario, gestion_horarios.horario, core.ambiente, gestion_horarios.ambiente_bloqueo IN SHARE MODE');
                $this->db->statement('LOCK TABLE gestion_horarios.oferta_curso, gestion_horarios.oferta_seccion IN SHARE ROW EXCLUSIVE MODE');
                if (! $fuente->confirmado($periodo)) self::rechazar('Prematrícula todavía no ha culminado oficialmente.');
                $actual = $this->capturar($usuario, $periodo, $plan);
                if (! hash_equals($actual['huella'], $huella)) self::rechazar('La demanda o la preparación cambió. Revise otra propuesta.');
                if ($actual['bloqueo'] !== null) self::rechazar($actual['bloqueo']);
                $ultima = $repo->ultima($periodo);
                if (! $ultima) self::rechazar('No hay demanda vigente.');
                $origen = InstantaneaDemanda::desdeArray(['periodo' => $periodo, 'version' => $ultima['version'], 'cursos' => $fuente->cursos($periodo)]);
                if (! hash_equals($ultima['hash'], $origen->hash())) self::rechazar('Hay nuevas solicitudes sin sincronizar. Actualice la demanda antes de confirmar.');
                return $operacion($actual);
            });
        });
    }

    private function crearSecciones(User $usuario, int $periodo, int $plan, array $actual): array
    {
        $resultado = ['ofertas' => 0, 'creadas' => 0, 'ajustadas' => 0];
        foreach ($actual['cursos'] as $fila) {
            if ($fila['estado'] !== 'pendiente') continue;
            $curso = $fila['id_curso_plan'];
            (new AmbitoEscrituraAcademica($this->db))->autorizarCurso($usuario, $curso);
            $oferta = $fila['id_oferta'];
            if ($oferta === null) {
                (new GestionOfertaAcademica($this->db))->crear($usuario, ['periodo' => $periodo, 'curso' => $curso, 'tipo' => 'REGULAR', 'docentes' => 1]);
                $oferta = (int) $this->db->table('gestion_horarios.oferta_curso')->where('id_periodo', $periodo)->where('id_curso_plan', $curso)->where('tipo_oferta', 'REGULAR')->value('id_oferta');
                $resultado['ofertas']++;
            }
            foreach ($fila['propuesta'] as $s) {
                if ($s['accion'] === 'conservar') continue;
                $planificador = new PlanificarSeccion($this->db, $this);
                if ($s['accion'] === 'crear') {
                    $planificador->guardar($usuario, $periodo, $plan, $curso, $s['codigo'], $s['capacidad']);
                    $id = (int) $this->db->table('core.seccion')->where('id_periodo', $periodo)->where('id_curso_plan', $curso)->where('codigo', $s['codigo'])->value('id_seccion');
                    (new GestionOfertaSeccion($this->db, null, $this))->asociar($usuario, $periodo, $id, $oferta, null);
                    $resultado['creadas']++;
                } else {
                    $original = collect($fila['actuales'])->firstWhere('id_seccion', $s['id_seccion']);
                    $planificador->guardar($usuario, $periodo, $plan, $curso, $s['codigo'], $s['capacidad'], (int) $s['id_seccion'], [$original['codigo'], $original['capacidad'], $original['estado']]);
                    $resultado['ajustadas']++;
                }
            }
        }
        return $resultado;
    }

    private function capturar(User $usuario, int $periodo, int $plan): array
    {
        abort_unless($this->disponible() && $periodo > 0 && app(AmbitoPlanes::class)->contiene($usuario, $plan), 403);
        abort_unless($this->db->table('core.escuela_profesional')->count() === 1
            && $this->db->table('core.plan_estudios')->count() === 1
            && $this->db->table('core.plan_estudios')->where('id_plan', $plan)->where('codigo', 'PLAN-2018-INF')->exists()
            && $this->db->table('core.periodo_academico')->where('id_periodo', $periodo)->where('codigo', '2026-II')->exists(), 403);
        $demanda = (new RepositorioDemandaPostgres($this->db))->vigente($periodo);
        $bloqueo = null;
        if (! $demanda) $bloqueo = 'Todavía no hay demanda sincronizada.';
        elseif ($demanda->requiere_revision) $bloqueo = 'La demanda requiere revisión.';
        elseif ($this->db->table('gestion_horarios.horario')->where('id_periodo', $periodo)->exists()) $bloqueo = 'Ya existe un horario del periodo. Revise su impacto antes de cambiar secciones.';
        $asociacion = $this->db->getSchemaBuilder()->hasTable('gestion_horarios.oferta_seccion');
        if (! $asociacion) $bloqueo = 'Falta la vinculación entre ofertas y secciones.';
        $bloqueados = $this->db->table('gestion_horarios.ambiente_bloqueo')->where('id_periodo', $periodo)->pluck('id_ambiente');
        $ambientes = $this->db->table('core.ambiente')->whereIn('codigo', self::AMBIENTES)->whereNotIn('id_ambiente', $bloqueados)->orderBy('codigo')->get(['id_ambiente', 'codigo', 'tipo', 'capacidad', 'estado'])->map(fn ($r) => (array) $r)->all();
        $catalogo = $this->db->table('core.curso_plan as cp')->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')->where('cp.id_plan', $plan)->orderBy('cp.ciclo')->orderBy('c.nombre')->orderBy('cp.id_curso_plan')->get(['cp.id_curso_plan', 'cp.ciclo', 'c.codigo', 'c.nombre', 'c.horas_teoria', 'c.horas_practica', 'c.horas_laboratorio']);
        $totales = $demanda ? $this->db->table('gestion_horarios.demanda_curso')->where('id_demanda', $demanda->id_demanda)->get(['id_curso_plan', 'total', 'manana', 'tarde']) : collect();
        if ($demanda && ($totales->count() !== $catalogo->count() || $totales->pluck('id_curso_plan')->diff($catalogo->pluck('id_curso_plan'))->isNotEmpty())) $bloqueo = 'La instantánea no cubre exactamente el plan de prueba.';
        (new AjustesDemanda($this->db))->filas($periodo, $demanda ? (int) $demanda->id_demanda : null, $totales);
        $totales = $totales->keyBy('id_curso_plan');
        $cursos = [];
        foreach ($catalogo as $c) {
            $id = (int) $c->id_curso_plan;
            $d = $totales->get($id);
            $total = $d ? (int) $d->total : null;
            $actuales = $this->db->table('core.seccion')->where('id_periodo', $periodo)->where('id_curso_plan', $id)->orderBy('codigo')->orderBy('id_seccion')->get(['id_seccion', 'codigo', 'capacidad', 'estado', 'id_docente_responsable'])->map(fn ($r) => (array) $r)->all();
            $ofertas = $this->db->table('gestion_horarios.oferta_curso')->where('id_periodo', $periodo)->where('id_curso_plan', $id)->orderBy('id_oferta')->get(['id_oferta', 'tipo_oferta', 'estado']);
            $ids = array_column($actuales, 'id_seccion');
            $vinculos = $asociacion ? $this->db->table('gestion_horarios.oferta_seccion')->whereIn('id_seccion', $ids)->where('estado', 'VIGENTE')->orderBy('id_oferta_seccion')->get(['id_oferta_seccion', 'id_seccion', 'id_oferta']) : collect();
            $actividades = $this->db->table('gestion_horarios.actividad_academica')->whereIn('id_seccion', $ids)->exists();
            $fila = ['id_curso_plan' => $id, 'codigo' => $c->codigo, 'nombre' => $c->nombre, 'ciclo' => (int) $c->ciclo, 'total' => $total, 'original' => $d ? (int) ($d->total_original ?? $d->total) : null, 'actuales' => $actuales, 'ofertas' => $ofertas->all(), 'vinculos' => $vinculos->all(), 'actividades' => $actividades, 'id_oferta' => null, 'minimo' => null, 'aforo' => null, 'propuesta' => [], 'estado' => 'revision', 'motivo' => null];
            try {
                if ($total === null) throw new InvalidArgumentException('Demanda no recibida.');
                if ($total === 0 && ! $actuales && $ofertas->isEmpty()) { $fila['estado'] = 'sin_demanda'; $fila['minimo'] = 0; $cursos[] = $fila; continue; }
                if ($ofertas->count() > 1 || ($ofertas->isNotEmpty() && ($ofertas->first()->tipo_oferta !== 'REGULAR' || ! in_array($ofertas->first()->estado, ['PLANIFICADA','OFERTADA'], true))) || ($ofertas->isEmpty() && $actuales)) throw new InvalidArgumentException('Oferta cerrada, especial o ambigua.');
                if ($total === 0) throw new InvalidArgumentException('Demanda cero con oferta o secciones: revisar sin eliminar.');
                $fila['id_oferta'] = $ofertas->isEmpty() ? null : (int) $ofertas->first()->id_oferta;
                if (count($actuales) !== $vinculos->count() || $vinculos->pluck('id_seccion')->unique()->count() !== count($actuales) || $vinculos->contains(fn ($v) => (int) $v->id_oferta !== $fila['id_oferta'])) throw new InvalidArgumentException('Las secciones no tienen una asociación regular inequívoca.');
                $p = self::calcular($total, (int)$c->horas_teoria + (int)$c->horas_practica > 0, (int)$c->horas_laboratorio > 0, $ambientes, $actuales, $c->nombre);
                $fila['minimo'] = $p['minimo']; $fila['aforo'] = $p['aforo_seccion']; $fila['propuesta'] = $p['secciones'];
                $cambios = collect($p['secciones'])->contains(fn ($s) => $s['accion'] !== 'conservar');
                if ($cambios && ($actividades || collect($actuales)->contains(fn ($s) => $s['id_docente_responsable'] !== null))) throw new InvalidArgumentException('Ya hay actividades o responsables. Revise los cambios antes de ampliar o ajustar.');
                $fila['estado'] = $cambios ? 'pendiente' : 'cubierta';
            } catch (InvalidArgumentException $e) { $fila['motivo'] = $e->getMessage(); }
            $cursos[] = $fila;
        }
        $datos = ['periodo' => $periodo, 'plan' => $plan, 'version' => $demanda ? (int)$demanda->version : null, 'recibida_en' => $demanda?->recibida_en, 'bloqueo' => $bloqueo, 'cursos' => $cursos, 'ambientes' => $ambientes];
        $fuente = app()->bound(CierreDemandaPrematricula::class) ? app(CierreDemandaPrematricula::class) : null;
        $cierre = $fuente?->marcaCierre($periodo);
        if ($cierre !== null && $demanda) {
            $ultima = (new RepositorioDemandaPostgres($this->db))->ultima($periodo);
            $origen = InstantaneaDemanda::desdeArray(['periodo' => $periodo, 'version' => (int) $demanda->version, 'cursos' => $fuente->cursos($periodo)]);
            if (! $ultima || $ultima['version'] !== (int) $demanda->version || ! hash_equals($ultima['hash'], $origen->hash())) {
                $bloqueo = 'Falta sincronizar la demanda final de Prematrícula.';
                $datos['bloqueo'] = $bloqueo;
            }
        }
        $tabla = $this->db->getSchemaBuilder()->hasTable('gestion_horarios.consolidacion_vacantes');
        $ajustes = $demanda && $this->db->getSchemaBuilder()->hasTable('gestion_horarios.ajuste_demanda')
            ? $this->db->table('gestion_horarios.ajuste_demanda')->where('id_demanda', $demanda->id_demanda)->orderByDesc('id_ajuste')->get(['id_ajuste', 'id_curso_plan'])->unique('id_curso_plan')->sortBy('id_curso_plan')->values()->toArray() : [];
        $guardada = $tabla ? $this->db->table('gestion_horarios.consolidacion_vacantes')->where('id_periodo', $periodo)->where('id_plan', $plan)->orderByDesc('id_consolidacion')->value('huella') : null;
        $revision = $bloqueo !== null || ! $tabla || collect($cursos)->contains(fn ($c) => $c['estado'] === 'revision');
        $pendientes = collect($cursos)->contains(fn ($c) => $c['estado'] === 'pendiente');
        $datos['huellaVacantes'] = $cierre !== null && ! $revision ? ConsolidacionVacantesLocal::huella($datos, $cierre, $ajustes) : null;
        $datos['estadoConsolidacion'] = ConsolidacionVacantesLocal::estado($cierre, $datos['huellaVacantes'], $guardada, $revision, $pendientes);
        if (! $tabla) $datos['bloqueo'] = 'Falta migrar la tabla de consolidación de vacantes.';
        return $datos + ['huella' => hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR))];
    }

    private static function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['planAutomatico' => $mensaje]);
    }
}
