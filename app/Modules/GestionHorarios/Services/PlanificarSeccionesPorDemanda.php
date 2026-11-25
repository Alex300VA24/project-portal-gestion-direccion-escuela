<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use App\Modules\GestionHorarios\Demand\RepositorioDemandaPostgres;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;

/** Alta aditiva de secciones regulares, sin matrícula ni creación de actividades ficticias. */
final class PlanificarSeccionesPorDemanda
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public static function propuesta(?int $total, int $capacidad, iterable $secciones, string $nombre): array
    {
        $secciones = is_array($secciones) ? $secciones : iterator_to_array($secciones);
        $calculo = PropuestaSecciones::calcular($total, $capacidad, $secciones);
        if (preg_match('/\b(TESIS|ASESOR[IÍ]A|TRABAJO DE INVESTIGACI[OÓ]N|PROYECTO INTEGRADOR)\b/iu', $nombre)) {
            throw new \InvalidArgumentException('Los grupos de tesis o asesoría requieren planificación específica.');
        }
        if ($calculo['adicionales'] === null) {
            throw new \InvalidArgumentException('Falta demanda o existen capacidades activas inválidas.');
        }
        $ocupados = [];
        foreach ($secciones as $s) {
            $codigo = strtoupper(trim($s->codigo));
            if (! preg_match('/^[A-Z]$/D', $codigo) || isset($ocupados[$codigo])) {
                throw new \InvalidArgumentException('Existen códigos especiales o ambiguos; utilice planificación manual.');
            }
            $ocupados[$codigo] = true;
        }
        $libres = array_values(array_diff(range('A', 'Z'), array_keys($ocupados)));
        if ($calculo['adicionales'] > count($libres)) {
            throw new \InvalidArgumentException('No hay suficientes códigos de sección disponibles (A–Z).');
        }

        return $calculo + ['codigos' => array_slice($libres, 0, $calculo['adicionales'])];
    }

    public function previsualizar(User $usuario, int $periodo, int $plan, int $curso, int $capacidad): array
    {
        return $this->db->transaction(fn () => $this->capturar($usuario, $periodo, $plan, $curso, $capacidad));
    }

    public function aplicar(User $usuario, int $periodo, int $plan, int $curso, int $capacidad, string $huella): int
    {
        (new AutorizacionGestionAcademica)->exigir($usuario);
        abort_unless($this->db->getDriverName() === 'pgsql', 422);
        if (DistribucionAutomaticaLocal::flujoHabilitado()) self::rechazar('Utilice la planificación automática con vacantes consolidadas.');
        $repo = new RepositorioDemandaPostgres($this->db);

        return $repo->conBloqueo($periodo, function () use ($usuario, $periodo, $plan, $curso, $capacidad, $huella) {
            (new AmbitoEscrituraAcademica($this->db))->autorizarCurso($usuario, $curso);
            $this->db->statement('LOCK TABLE core.seccion IN SHARE ROW EXCLUSIVE MODE');
            $this->db->statement('LOCK TABLE gestion_horarios.actividad_academica, gestion_horarios.detalle_horario, gestion_horarios.horario IN SHARE MODE');
            $actual = $this->capturar($usuario, $periodo, $plan, $curso, $capacidad, true);
            if (! hash_equals($actual['huella'], $huella)) {
                self::rechazar('La demanda, la oferta o las secciones cambiaron. Revise una nueva propuesta.');
            }
            $planificador = new PlanificarSeccion($this->db);
            foreach ($actual['codigos'] as $codigo) {
                $planificador->guardar($usuario, $periodo, $plan, $curso, $codigo, $capacidad);
                if ($actual['asociacion']) {
                    $id = $this->db->table('core.seccion')->where('id_periodo', $periodo)->where('id_curso_plan', $curso)->where('codigo', $codigo)->value('id_seccion');
                    (new GestionOfertaSeccion($this->db))->asociar($usuario, $periodo, (int) $id, $actual['oferta'], null);
                }
            }

            return count($actual['codigos']);
        });
    }

    private function capturar(User $usuario, int $periodo, int $plan, int $curso, int $capacidad, bool $bloqueada = false): array
    {
        (new AutorizacionGestionAcademica)->exigir($usuario);
        if (min($periodo, $plan, $curso, $capacidad) <= 0 || $capacidad > 2147483647) {
            self::rechazar('Seleccione curso, periodo y capacidad válidos.');
        }
        (new AmbitoEscrituraAcademica($this->db))->autorizarCurso($usuario, $curso);
        $nombre = $this->db->table('core.curso_plan as cp')->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->where('cp.id_plan', $plan)->where('cp.id_curso_plan', $curso)->value('c.nombre');
        abort_unless(is_string($nombre), 403);
        $ofertas = $this->db->table('gestion_horarios.oferta_curso')->where('id_periodo', $periodo)->where('id_curso_plan', $curso)->orderBy('id_oferta');
        if ($bloqueada) {
            $ofertas->lockForUpdate();
        }
        $ofertas = $ofertas->get(['id_oferta', 'tipo_oferta', 'estado']);
        if ($ofertas->count() !== 1 || $ofertas->first()->tipo_oferta !== 'REGULAR' || ! in_array($ofertas->first()->estado, ['PLANIFICADA', 'OFERTADA'], true)) {
            self::rechazar('Solo se admite una oferta regular abierta, sin repetición ni ambigüedad.');
        }
        if ($this->db->table('gestion_horarios.horario')->where('id_periodo', $periodo)->exists()) {
            self::rechazar('Ya hay versiones de horario en este periodo. Revise su impacto antes de agregar secciones.');
        }
        $demanda = (new RepositorioDemandaPostgres($this->db))->vigente($periodo);
        if (! $demanda) {
            self::rechazar('No existe una instantánea de demanda vigente.');
        }
        if ($demanda->requiere_revision) {
            self::rechazar('La demanda está marcada para revisión.');
        }
        $fila = $this->db->table('gestion_horarios.demanda_curso')->where('id_demanda', $demanda->id_demanda)->where('id_curso_plan', $curso)->first();
        if ($fila) (new \App\Modules\GestionHorarios\Demand\AjustesDemanda($this->db))->filas($periodo, (int) $demanda->id_demanda, [$fila]);
        $total = $fila?->total;
        $secciones = $this->db->table('core.seccion')->where('id_periodo', $periodo)->where('id_curso_plan', $curso)->orderBy('id_seccion')->get(['id_seccion', 'codigo', 'estado', 'capacidad']);
        $oferta = (int) $ofertas->first()->id_oferta;
        $asociacion = $this->db->getSchemaBuilder()->hasTable('gestion_horarios.oferta_seccion');
        if ($asociacion) {
            $vinculos = $this->db->table('gestion_horarios.oferta_seccion')->whereIn('id_seccion', $secciones->where('estado', 'ACTIVA')->pluck('id_seccion'))->where('estado', 'VIGENTE')->get(['id_seccion', 'id_oferta']);
            if ($vinculos->count() !== $secciones->where('estado', 'ACTIVA')->count() || $vinculos->contains(fn ($v) => (int) $v->id_oferta !== $oferta) || $vinculos->pluck('id_seccion')->unique()->count() !== $vinculos->count()) {
                self::rechazar('Las secciones activas deben tener una asociación regular inequívoca.');
            }
        }
        try {
            $resultado = self::propuesta($total === null ? null : (int) $total, $capacidad, $secciones, $nombre);
        } catch (\InvalidArgumentException $e) {
            self::rechazar($e->getMessage());
        }
        $huella = hash('sha256', json_encode([$periodo, $plan, $curso, $capacidad, $demanda, $total, $ofertas, $secciones, $asociacion], JSON_THROW_ON_ERROR));

        return $resultado + compact('huella', 'oferta', 'asociacion', 'nombre', 'capacidad', 'curso', 'periodo', 'plan') + ['version' => (int) $demanda->version, 'total' => (int) $total];
    }

    private static function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['propuesta' => $mensaje]);
    }
}
