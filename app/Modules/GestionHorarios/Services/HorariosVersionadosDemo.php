<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Borradores independientes de prueba. No son horarios institucionales ni resultados CP-SAT. */
final class HorariosVersionadosDemo
{
    public const TABLA = 'gestion_horarios.qa_horario_revision';
    public const DIAS = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];

    public function disponible(): bool
    {
        return DB::connection()->getSchemaBuilder()->hasTable(self::TABLA) && HorarioUnicoDemo::id() !== null;
    }

    private function exigir(User $usuario): void
    {
        abort_unless(ConsultaLocalDemostracion::permitida($usuario), 403);
        abort_unless($this->disponible(), 404, 'Prepare el almacenamiento de borradores QA.');
    }

    public static function minutos(string $hora): int
    {
        if (! preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])(?::00)?$/D', $hora, $m)) {
            throw new InvalidArgumentException('Hora inválida.');
        }
        return (int) $m[1] * 60 + (int) $m[2];
    }

    public static function hora(int $minutos): string
    {
        return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }

    private function fuente(): array
    {
        $plan = (int) DB::table('core.plan_estudios')->where('codigo', 'QA-HORARIOS')->value('id_plan');
        $periodo = (int) DB::table('core.periodo_academico')->where('codigo', '2026-II')->value('id_periodo');
        abort_unless($plan > 0 && $periodo > 0, 422);
        $secciones = DB::table('core.seccion as s')->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->where('s.id_periodo', $periodo)->where('cp.id_plan', $plan)->where('s.estado', 'ACTIVA')
            ->orderBy('s.id_seccion')->get(['s.id_seccion', 's.codigo as seccion', 's.capacidad', 'cp.ciclo', 'c.codigo as curso', 'c.nombre'])->map(fn ($s) => (array) $s)->all();
        $ids = array_column($secciones, 'id_seccion');
        $filas = DB::table('gestion_horarios.actividad_academica')->whereIn('id_seccion', $ids)->orderBy('id_actividad')
            ->get(['id_actividad', 'id_seccion', 'tipo_actividad', 'hora_inicio', 'hora_fin']);
        $asignaciones = DB::table('gestion_horarios.asignacion_docente as ad')->join('core.docente as d', 'd.id_docente', '=', 'ad.id_docente')
            ->whereIn('ad.id_actividad', $filas->pluck('id_actividad'))->whereIn('ad.estado', ['PROPUESTA', 'CONFIRMADA'])
            ->orderBy('ad.id_asignacion')->get(['ad.id_actividad', 'ad.id_docente', 'd.codigo_docente', 'ad.estado'])->groupBy('id_actividad');
        $actividades = [];
        foreach ($filas as $fila) {
            $duracion = self::minutos($fila->hora_fin) - self::minutos($fila->hora_inicio);
            if ($duracion <= 0) { throw new InvalidArgumentException('Duración de origen inválida.'); }
            $actividades[] = ['id' => (int) $fila->id_actividad, 'seccion_id' => (int) $fila->id_seccion,
                'tipo' => $fila->tipo_actividad, 'duracion' => $duracion,
                'docentes' => $asignaciones->get($fila->id_actividad, collect())->map(fn ($d) => (array) $d)->all()];
        }
        $bloques = DB::table('gestion_horarios.bloque_horario')->where('id_periodo', $periodo)->orderBy('hora_inicio')->get(['hora_inicio', 'hora_fin'])->map(fn ($b) => [self::minutos($b->hora_inicio), self::minutos($b->hora_fin)])->all();
        return compact('plan', 'periodo', 'secciones', 'actividades', 'bloques');
    }

    public function listar(User $usuario): array
    {
        $this->exigir($usuario);
        return DB::table(self::TABLA)->where('propuesta', HorarioUnicoDemo::id())->select('propuesta', 'nombre', 'version', 'creado_en')->orderByDesc('version')->limit(1)->get()->map(fn ($r) => (array) $r)->all();
    }

    public function leer(User $usuario, string $id, ?int $version = null): array
    {
        $this->exigir($usuario);
        abort_unless(Str::isUuid($id) && $id === HorarioUnicoDemo::id(), 404);
        $fila = DB::table(self::TABLA)->where('propuesta', $id)->when($version !== null, fn ($q) => $q->where('version', $version))->orderByDesc('version')->first();
        abort_unless($fila, 404);
        return ['id' => $id, 'nombre' => $fila->nombre, 'version' => (int) $fila->version, 'datos' => json_decode($fila->datos, true, 512, JSON_THROW_ON_ERROR)];
    }

    private function insertar(User $usuario, string $id, string $nombre, int $version, array $datos): void
    {
        DB::table(self::TABLA)->insert(['propuesta' => $id, 'nombre' => $nombre, 'version' => $version, 'datos' => json_encode($datos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'actor_qa' => $usuario->email]);
    }

    public function crear(User $usuario, string $nombre): string
    {
        $this->exigir($usuario);
        // El horario de trabajo se registra explícitamente por CLI, no se crean copias.
        throw ValidationException::withMessages(['nombre' => 'Ya existe un horario de trabajo para este periodo.']);
    }

    public function renombrar(User $usuario, string $id, int $version, string $nombre): int
    {
        $this->exigir($usuario);
        $nombre = HorarioUnicoDemo::nombre($nombre);
        return DB::transaction(function () use ($usuario, $id, $version, $nombre) {
            DB::statement('LOCK TABLE '.self::TABLA.' IN SHARE ROW EXCLUSIVE MODE');
            $actual = $this->leer($usuario, $id);
            try { $siguiente = HorarioUnicoDemo::siguienteVersion($version, $actual['version']); }
            catch (\RuntimeException $e) { throw ValidationException::withMessages(['nombre' => $e->getMessage()]); }
            if ($nombre === $actual['nombre']) { return $version; }
            $this->insertar($usuario, $id, $nombre, $siguiente, $actual['datos']);
            return $siguiente;
        }, 3);
    }

    /** Comprueba solo calendario, mismo docente y misma sección. No acredita factibilidad HARD/SOFT. */
    public static function colocar(array $datos, int $actividad, ?string $dia, ?string $inicio): array
    {
        $actividades = array_column($datos['actividades'], null, 'id');
        $a = $actividades[$actividad] ?? null;
        if (! $a) { throw new InvalidArgumentException('Actividad no incluida en la propuesta.'); }
        $sesiones = $datos['sesiones'];
        if ($dia === null && $inicio === null) {
            unset($sesiones[$actividad]);
            return array_replace($datos, ['sesiones' => $sesiones]);
        }
        if (! in_array($dia, self::DIAS, true) || $inicio === null) { throw new InvalidArgumentException('Seleccione día y hora.'); }
        $desde = self::minutos($inicio);
        $hasta = $desde + $a['duracion'];
        $cubierto = $desde;
        $inicioValido = false;
        foreach ($datos['bloques'] as [$i, $f]) {
            if ($i === $desde) { $inicioValido = true; }
            if ($i <= $cubierto && $f > $cubierto) { $cubierto = $f; }
        }
        if (! $inicioValido || $hasta > $cubierto || $hasta >= 1440) { throw new InvalidArgumentException('La sesión sale de los bloques continuos del calendario.'); }
        foreach ($sesiones as $id => $s) {
            if ((int) $id === $actividad || $s['dia'] !== $dia || $desde >= $s['fin'] || $s['inicio'] >= $hasta) { continue; }
            $otra = $actividades[$id];
            if ($a['seccion_id'] === $otra['seccion_id'] || array_intersect(array_column($a['docentes'], 'id_docente'), array_column($otra['docentes'], 'id_docente'))) {
                throw new InvalidArgumentException('Coincidencia de sección o docente en la franja seleccionada.');
            }
        }
        $sesiones[$actividad] = ['dia' => $dia, 'inicio' => $desde, 'fin' => $hasta];
        return array_replace($datos, ['sesiones' => $sesiones]);
    }

    public function editar(User $usuario, string $id, int $version, int $actividad, ?string $dia, ?string $inicio): int
    {
        $this->exigir($usuario);
        return DB::transaction(function () use ($usuario, $id, $version, $actividad, $dia, $inicio) {
            DB::statement('LOCK TABLE '.self::TABLA.' IN SHARE ROW EXCLUSIVE MODE');
            $actual = $this->leer($usuario, $id);
            if (isset($actual['datos']['cpsat_qa'])) { throw ValidationException::withMessages(['sesion' => 'El resultado CP-SAT QA es de solo consulta; la edición aún no usa el validador completo.']); }
            if ($actual['version'] !== $version) { throw ValidationException::withMessages(['sesion' => 'Existe una versión posterior. Recargue antes de guardar.']); }
            try { $datos = self::colocar($actual['datos'], $actividad, $dia, $inicio); }
            catch (InvalidArgumentException $e) { throw ValidationException::withMessages(['sesion' => $e->getMessage()]); }
            if ($datos == $actual['datos']) { return $version; }
            $this->insertar($usuario, $id, $actual['nombre'], $version + 1, $datos);
            return $version + 1;
        }, 3);
    }

    public static function comparar(array $a, array $b): array
    {
        $cambios = [];
        foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $id) {
            if (($a[$id] ?? null) != ($b[$id] ?? null)) { $cambios[] = ['id' => $id, 'antes' => $a[$id] ?? null, 'despues' => $b[$id] ?? null]; }
        }
        return $cambios;
    }
}
