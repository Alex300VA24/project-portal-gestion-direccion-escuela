<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Models\User;
use App\Modules\TesisProyectoTesis\Support\Identidad;
use App\Shared\Models\Core\Estudiante;
use App\Shared\Models\Core\Persona;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de lectura de personas, estudiantes, docentes y usuarios académicos
 * (core / seguridad). Si gestión define otro contrato, se cambia solo aquí.
 */
final class IdentidadService
{
    /** @var array<int, Identidad|null> */
    private array $cache = [];

    /**
     * Resuelve: public.users.email → seguridad.usuario → core.persona → estudiante / docente.
     */
    public function actual(?User $user): ?Identidad
    {
        if ($user === null) {
            return null;
        }

        if (array_key_exists($user->id, $this->cache)) {
            return $this->cache[$user->id];
        }

        $usuario = Usuario::query()
            ->select(['id_usuario', 'id_persona', 'estado'])
            ->whereRaw('LOWER(correo_institucional) = ?', [mb_strtolower((string) $user->email)])
            ->first();

        if ($usuario === null || $usuario->estado === 'Inactivo') {
            return $this->cache[$user->id] = null;
        }

        $persona = Persona::query()
            ->select(['id_persona', 'nombres', 'apellido_paterno', 'apellido_materno'])
            ->find($usuario->id_persona);

        $idEstudiante = Estudiante::query()->where('id_persona', $usuario->id_persona)->value('id_estudiante');
        $idDocente = DB::table('core.docente')
            ->where('id_persona', $usuario->id_persona)
            ->where('estado', 'Activo')
            ->value('id_docente');

        return $this->cache[$user->id] = new Identidad(
            (int) $usuario->id_usuario,
            (int) $usuario->id_persona,
            $persona ? self::nombreCompleto($persona->nombres, $persona->apellido_paterno, $persona->apellido_materno) : (string) $user->name,
            $idEstudiante !== null ? (int) $idEstudiante : null,
            $idDocente !== null ? (int) $idDocente : null,
        );
    }

    public function olvidar(): void
    {
        $this->cache = [];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{nombre: string, codigo: string, correo: ?string}>
     */
    public function estudiantes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('core.estudiante as e')
            ->join('core.persona as p', 'p.id_persona', '=', 'e.id_persona')
            ->leftJoin('seguridad.usuario as u', 'u.id_persona', '=', 'e.id_persona')
            ->whereIn('e.id_estudiante', array_values(array_unique($ids)))
            ->get(['e.id_estudiante', 'e.codigo_universitario', 'p.nombres', 'p.apellido_paterno', 'p.apellido_materno', 'u.correo_institucional'])
            ->mapWithKeys(fn ($r) => [(int) $r->id_estudiante => [
                'nombre' => self::nombreCompleto($r->nombres, $r->apellido_paterno, $r->apellido_materno),
                'codigo' => (string) $r->codigo_universitario,
                'correo' => $r->correo_institucional,
            ]])
            ->all();
    }

    /**
     * Busca un estudiante activo por código universitario (para agregar coautor).
     *
     * @return array{id: int, nombre: string, codigo: string, correo: ?string}|null
     */
    public function estudiantePorCodigo(string $codigo): ?array
    {
        $id = Estudiante::query()
            ->where('codigo_universitario', trim($codigo))
            ->where('estado', 'Activo')
            ->value('id_estudiante');

        if ($id === null) {
            return null;
        }

        return ['id' => (int) $id, ...$this->estudiantes([(int) $id])[(int) $id]];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{nombre: string, codigo: string, grado: ?string, categoria: ?string, orcid: ?string, departamento: ?string}>
     */
    public function docentes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('core.docente as d')
            ->join('core.persona as p', 'p.id_persona', '=', 'd.id_persona')
            ->leftJoin('core.departamento_academico as da', 'da.id_departamento', '=', 'd.id_departamento')
            ->whereIn('d.id_docente', array_values(array_unique($ids)))
            ->get(['d.id_docente', 'd.codigo_docente', 'd.grado_academico', 'd.categoria', 'd.orcid', 'p.nombres', 'p.apellido_paterno', 'p.apellido_materno', 'da.nombre as departamento'])
            ->mapWithKeys(fn ($r) => [(int) $r->id_docente => [
                'nombre' => trim(($r->grado_academico ? $r->grado_academico.' ' : '').self::nombreCompleto($r->nombres, $r->apellido_paterno, $r->apellido_materno)),
                'codigo' => (string) $r->codigo_docente,
                'grado' => $r->grado_academico,
                'categoria' => $r->categoria,
                'orcid' => $r->orcid,
                'departamento' => $r->departamento,
            ]])
            ->all();
    }

    /** Nombre completo de un usuario del portal (por su id en seguridad.usuario). */
    public function nombreUsuario(?int $idUsuario): string
    {
        if ($idUsuario === null) {
            return '—';
        }
        $r = DB::table('seguridad.usuario as u')->join('core.persona as p', 'p.id_persona', '=', 'u.id_persona')
            ->where('u.id_usuario', $idUsuario)->first(['p.nombres', 'p.apellido_paterno', 'p.apellido_materno']);

        return $r ? self::nombreCompleto($r->nombres, $r->apellido_paterno, $r->apellido_materno) : '—';
    }

    public function nombreDocente(?int $id): string
    {
        return $id === null ? '—' : ($this->docentes([$id])[$id]['nombre'] ?? '—');
    }

    /**
     * Docentes activos que pueden ser asesores o jurados.
     *
     * @return list<array{id: int, codigo: string, nombre: string, categoria: ?string, departamento: ?string, lineas: list<int>}>
     */
    public function docentesActivos(): array
    {
        $lineas = DB::table('core.docente_linea')->get(['id_docente', 'id_linea'])
            ->groupBy('id_docente')
            ->map(fn ($g) => $g->pluck('id_linea')->map(fn ($v) => (int) $v)->all());

        $ids = DB::table('core.docente')->where('estado', 'Activo')->pluck('id_docente')->map(fn ($v) => (int) $v)->all();
        $datos = $this->docentes($ids);

        $lista = [];
        foreach ($datos as $id => $d) {
            $lista[] = [
                'id' => $id,
                'codigo' => $d['codigo'],
                'nombre' => $d['nombre'],
                'categoria' => $d['categoria'],
                'departamento' => $d['departamento'],
                'lineas' => $lineas[$id] ?? [],
            ];
        }

        usort($lista, fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));

        return $lista;
    }

    /** Id de un docente activo a partir de su código (los formularios nunca envían ids). */
    public function idDocentePorCodigo(?string $codigo): ?int
    {
        if ($codigo === null || trim($codigo) === '') {
            return null;
        }

        $id = DB::table('core.docente')->where('codigo_docente', trim($codigo))->where('estado', 'Activo')->value('id_docente');

        return $id !== null ? (int) $id : null;
    }

    public function docenteActivoExiste(int $id): bool
    {
        return DB::table('core.docente')->where('id_docente', $id)->where('estado', 'Activo')->exists();
    }

    /**
     * Usuarios académicos (seguridad.usuario) de los estudiantes indicados.
     *
     * @param  list<int>  $idsEstudiante
     * @return list<int>
     */
    public function usuariosDeEstudiantes(array $idsEstudiante): array
    {
        if ($idsEstudiante === []) {
            return [];
        }

        return DB::table('core.estudiante as e')
            ->join('seguridad.usuario as u', 'u.id_persona', '=', 'e.id_persona')
            ->whereIn('e.id_estudiante', $idsEstudiante)
            ->pluck('u.id_usuario')->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    /**
     * @param  list<int>  $idsDocente
     * @return list<int>
     */
    public function usuariosDeDocentes(array $idsDocente): array
    {
        if ($idsDocente === []) {
            return [];
        }

        return DB::table('core.docente as d')
            ->join('seguridad.usuario as u', 'u.id_persona', '=', 'd.id_persona')
            ->whereIn('d.id_docente', $idsDocente)
            ->pluck('u.id_usuario')->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    /**
     * Usuarios académicos de quienes tienen un rol del portal (director_escuela, secretaria).
     *
     * @return list<int>
     */
    public function usuariosConRol(string $rol): array
    {
        $correos = User::role($rol)->pluck('email')->map(fn ($c) => mb_strtolower((string) $c))->all();

        if ($correos === []) {
            return [];
        }

        return Usuario::query()
            ->whereIn(DB::raw('LOWER(correo_institucional)'), $correos)
            ->pluck('id_usuario')->map(fn ($v) => (int) $v)->all();
    }

    public static function nombreCompleto(?string $nombres, ?string $paterno, ?string $materno): string
    {
        return trim(preg_replace('/\s+/', ' ', ($nombres ?? '').' '.($paterno ?? '').' '.($materno ?? '')) ?? '');
    }
}
