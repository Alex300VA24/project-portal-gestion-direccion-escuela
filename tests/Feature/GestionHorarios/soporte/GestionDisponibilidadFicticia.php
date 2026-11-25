<?php

namespace Tests\Feature\GestionHorarios\soporte;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorIdentidadDocente;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Protocolo y tablas ficticias; no es un proveedor institucional. */
final class GestionDisponibilidadFicticia
{
    public function __construct(private readonly ConnectionInterface $db, private readonly ProveedorIdentidadDocente $identidad) {}

    private function verificar(): void
    {
        $cfg = $this->db->getConfig();
        if (! app()->environment('testing') || $this->db->getDriverName() !== 'pgsql' || ($cfg['host'] ?? null) !== '127.0.0.1' || (int) ($cfg['port'] ?? 0) !== 55471 || ($cfg['database'] ?? null) !== 'horarios_27b2_test' || ($cfg['username'] ?? null) !== 'horarios_test') {
            throw new RuntimeException('Escritura ficticia fuera del entorno aislado.');
        }
        $real = $this->db->selectOne("select current_database() as base, current_user as usuario, current_setting('server_version_num') as version");
        if ($real->base !== 'horarios_27b2_test' || $real->usuario !== 'horarios_test' || (int) $real->version < 170000 || (int) $real->version >= 180000) {
            throw new RuntimeException('Identidad PostgreSQL no válida.');
        }
    }

    public function docentesPendientes(User $actor, int $periodo): array
    {
        $this->verificar();
        $admin = $this->db->table('prueba.disp_actor')->where('usuario_id', $actor->id)->whereIn('rol', ['DIRECTOR', 'SECRETARIA'])->first();
        if (! $admin) {
            throw new AuthorizationException;
        }

        return $this->db->table('gestion_horarios.asignacion_docente as ad')->join('gestion_horarios.actividad_academica as a', 'a.id_actividad', '=', 'ad.id_actividad')->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')->join('core.plan_estudios as p', 'p.id_plan', '=', 'cp.id_plan')->join('gestion_horarios.oferta_seccion as os', 'os.id_seccion', '=', 's.id_seccion')->join('gestion_horarios.oferta_curso as o', function ($join) {
            $join->on('o.id_oferta', '=', 'os.id_oferta')->on('o.id_periodo', '=', 's.id_periodo')->on('o.id_curso_plan', '=', 's.id_curso_plan');
        })->leftJoin('gestion_horarios.declaracion_disponibilidad as d', function ($join) {
            $join->on('d.id_docente', '=', 'ad.id_docente')->on('d.id_periodo', '=', 's.id_periodo');
        })->where('s.id_periodo', $periodo)->where('p.id_escuela', $admin->escuela_id)->where('ad.estado', 'CONFIRMADA')->where('s.estado', 'ACTIVA')->where('os.estado', 'VIGENTE')->where('o.estado', 'OFERTADA')->where(fn ($q) => $q->whereNull('d.id_declaracion')->orWhere('d.estado', '!=', 'PRESENTADA'))->distinct()->orderBy('ad.id_docente')->pluck('ad.id_docente')->map(fn ($id) => (int) $id)->all();
    }

    public function historial(User $actor, int $periodo, int $docente): array
    {
        $this->verificar();
        $admin = $this->db->table('prueba.disp_actor')->where('usuario_id', $actor->id)->whereIn('rol', ['DIRECTOR', 'SECRETARIA'])->first();
        if (! $admin) {
            throw new AuthorizationException;
        }
        $autorizado = $this->db->table('gestion_horarios.asignacion_docente as ad')->join('gestion_horarios.actividad_academica as a', 'a.id_actividad', '=', 'ad.id_actividad')->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')->join('core.plan_estudios as p', 'p.id_plan', '=', 'cp.id_plan')->where('ad.id_docente', $docente)->where('ad.estado', 'CONFIRMADA')->where('s.estado', 'ACTIVA')->where('s.id_periodo', $periodo)->where('p.id_escuela', $admin->escuela_id)->exists();
        if (! $autorizado) {
            throw new AuthorizationException;
        }

        return $this->db->table('prueba.disp_historial')->where('periodo', $periodo)->where('docente', $docente)->orderBy('version')->get()->all();
    }

    public function guardar(User $actor, int $periodo, ?int $objetivo, int $version, string $preferencia, array $seleccion): array
    {
        $this->verificar();
        if ($periodo <= 0 || $periodo > 2147483647 || $version < 0 || ! in_array($preferencia, ['MANANA', 'TARDE', 'SIN_PREFERENCIA'], true) || ! array_is_list($seleccion) || count($seleccion) > 200) {
            throw ValidationException::withMessages(['datos' => 'Datos de disponibilidad inválidos.']);
        }
        $docentePropio = $this->identidad->docenteId($actor);
        $admin = $this->db->table('prueba.disp_actor')->where('usuario_id', $actor->id)->whereIn('rol', ['DIRECTOR', 'SECRETARIA'])->first();
        $docente = $admin ? $objetivo : $docentePropio;
        if (! $docente || (! $admin && ($objetivo !== null && $objetivo !== $docentePropio))) {
            throw new AuthorizationException;
        }

        return $this->db->transaction(function () use ($actor, $periodo, $version, $preferencia, $seleccion, $admin, $docente): array {
            $this->db->selectOne('select pg_advisory_xact_lock(?, ?)', [27127, $periodo]);
            $config = $this->db->table('prueba.disp_periodo')->where('periodo', $periodo)->lockForUpdate()->first();
            if (! $config || ! $config->abierto) {
                throw ValidationException::withMessages(['periodo' => 'La recepción del periodo está cerrada.']);
            }
            $asignaciones = $this->db->table('gestion_horarios.asignacion_docente as ad')->join('gestion_horarios.actividad_academica as a', 'a.id_actividad', '=', 'ad.id_actividad')->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')->join('core.plan_estudios as p', 'p.id_plan', '=', 'cp.id_plan')->join('gestion_horarios.oferta_seccion as os', 'os.id_seccion', '=', 's.id_seccion')->join('gestion_horarios.oferta_curso as o', function ($join) {
                $join->on('o.id_oferta', '=', 'os.id_oferta')->on('o.id_periodo', '=', 's.id_periodo')->on('o.id_curso_plan', '=', 's.id_curso_plan');
            })->where('s.id_periodo', $periodo)->where('ad.id_docente', $docente)->where('ad.estado', 'CONFIRMADA')->where('s.estado', 'ACTIVA')->where('os.estado', 'VIGENTE')->where('o.estado', 'OFERTADA')->when($admin, fn ($q) => $q->where('p.id_escuela', $admin->escuela_id))->get(['o.tipo_oferta']);
            if ($asignaciones->isEmpty()) {
                throw new AuthorizationException;
            }
            $dias = json_decode($config->dias, true, 512, JSON_THROW_ON_ERROR);
            $normalizadas = [];
            foreach ($seleccion as $item) {
                if (! is_array($item) || ! is_string($item['dia'] ?? null) || ! is_int($item['bloque'] ?? null) || ! in_array($item['dia'], $dias, true)) {
                    throw ValidationException::withMessages(['franjas' => 'Día o bloque no autorizado.']);
                }
                if ($item['dia'] === 'SABADO' && (($asignaciones->contains('tipo_oferta', 'REGULAR') && ! $config->sabado_regular) || ($asignaciones->contains('tipo_oferta', 'REPETICION') && ! $config->sabado_repeticion))) {
                    throw ValidationException::withMessages(['franjas' => 'El sábado no está autorizado para esta carga.']);
                }
                $bloque = $this->db->table('gestion_horarios.bloque_horario')->where('id_periodo', $periodo)->where('id_bloque', $item['bloque'])->first();
                if (! $bloque || $bloque->hora_inicio >= $bloque->hora_fin || $bloque->hora_inicio < $config->inicio || $bloque->hora_fin > $config->fin || ($bloque->hora_inicio < $config->receso_fin && $bloque->hora_fin > $config->receso_inicio)) {
                    throw ValidationException::withMessages(['franjas' => 'El bloque no pertenece al calendario autorizado.']);
                }
                $clave = $item['dia'].':'.$item['bloque'];
                if (isset($normalizadas[$clave])) {
                    throw ValidationException::withMessages(['franjas' => 'La selección contiene bloques duplicados.']);
                }
                foreach ($normalizadas as $previa) {
                    if ($previa['dia'] === $item['dia'] && $previa['inicio'] < $bloque->hora_fin && $bloque->hora_inicio < $previa['fin']) {
                        throw ValidationException::withMessages(['franjas' => 'La selección contiene bloques superpuestos.']);
                    }
                }
                $normalizadas[$clave] = ['dia' => $item['dia'], 'bloque' => $item['bloque'], 'inicio' => $bloque->hora_inicio, 'fin' => $bloque->hora_fin];
            }
            ksort($normalizadas);
            $anterior = $this->db->table('gestion_horarios.declaracion_disponibilidad')->where('id_periodo', $periodo)->where('id_docente', $docente)->lockForUpdate()->first();
            if ((int) ($anterior->version ?? 0) !== $version) {
                throw ValidationException::withMessages(['version' => 'La declaración cambió; recargue antes de guardar.']);
            }
            $id = $anterior?->id_declaracion ?? $this->db->selectOne("select nextval('prueba.declaracion_seq') as id")->id;
            $datos = ['estado' => 'PRESENTADA', 'tratamiento_regular' => 'ESTRICTA', 'version' => $version + 1, 'preferencia' => $preferencia];
            if ($anterior) {
                $this->db->table('gestion_horarios.declaracion_disponibilidad')->where('id_declaracion', $id)->update($datos);
            } else {
                $this->db->table('gestion_horarios.declaracion_disponibilidad')->insert(['id_declaracion' => $id, 'id_periodo' => $periodo, 'id_docente' => $docente] + $datos);
            }
            $this->db->table('gestion_horarios.disponibilidad_docente')->where('id_periodo', $periodo)->where('id_docente', $docente)->delete();
            foreach ($normalizadas as $f) {
                $this->db->table('gestion_horarios.disponibilidad_docente')->insert(['id_disponibilidad' => $this->db->selectOne("select nextval('prueba.disponibilidad_seq') as id")->id, 'id_periodo' => $periodo, 'id_docente' => $docente, 'id_declaracion' => $id, 'dia_semana' => $f['dia'], 'id_bloque' => $f['bloque']]);
            }
            $this->db->table('prueba.disp_historial')->insert(['periodo' => $periodo, 'docente' => $docente, 'version' => $version + 1, 'actor' => $actor->id, 'rol_actor' => $admin?->rol ?? 'DOCENTE', 'preferencia' => $preferencia, 'franjas' => json_encode(array_values($normalizadas), JSON_THROW_ON_ERROR)]);

            return ['version' => $version + 1, 'estado' => 'DECLARADA'];
        });
    }
}
