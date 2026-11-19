<?php

namespace App\Modules\InvestigacionFormativa\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class DatosAcademicosService
{
    public function escuelaDeSeccion(int $idSeccion): ?int
    {
        $idEscuela = DB::table('core.seccion as s')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->join('core.plan_estudios as pe', 'pe.id_plan', '=', 'cp.id_plan')
            ->where('s.id_seccion', $idSeccion)
            ->value('pe.id_escuela');

        return $idEscuela === null ? null : (int) $idEscuela;
    }

    /**
     * @return object{id_seccion: int, id_periodo: int, id_curso_plan: int, id_escuela: int|null, id_docente_responsable: int|null, estado: string}|null
     */
    public function seccionConEscuela(int $idSeccion): ?object
    {
        $seccion = DB::table('core.seccion as s')
            ->leftJoin('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->leftJoin('core.plan_estudios as pe', 'pe.id_plan', '=', 'cp.id_plan')
            ->where('s.id_seccion', $idSeccion)
            ->select([
                's.id_seccion',
                's.id_periodo',
                's.id_curso_plan',
                's.id_docente_responsable',
                's.estado',
                'pe.id_escuela',
            ])
            ->first();

        return $seccion === null ? null : (object) [
            'id_seccion' => (int) $seccion->id_seccion,
            'id_periodo' => (int) $seccion->id_periodo,
            'id_curso_plan' => (int) $seccion->id_curso_plan,
            'id_escuela' => $seccion->id_escuela === null ? null : (int) $seccion->id_escuela,
            'id_docente_responsable' => $seccion->id_docente_responsable === null ? null : (int) $seccion->id_docente_responsable,
            'estado' => (string) $seccion->estado,
        ];
    }

    public function nombreEscuelaDeSeccion(int $idSeccion): ?string
    {
        $nombre = DB::table('core.seccion as s')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->join('core.plan_estudios as pe', 'pe.id_plan', '=', 'cp.id_plan')
            ->join('core.escuela_profesional as e', 'e.id_escuela', '=', 'pe.id_escuela')
            ->where('s.id_seccion', $idSeccion)
            ->value('e.nombre');

        return $nombre === null ? null : (string) $nombre;
    }

    public function nombreDeLinea(int $idLinea): ?string
    {
        $nombre = DB::table('core.linea_investigacion')
            ->where('id_linea', $idLinea)
            ->value('nombre');

        return $nombre === null ? null : (string) $nombre;
    }

    public function docenteActivoDePersona(int $idPersona): ?int
    {
        $idDocente = DB::table('core.docente')
            ->where('id_persona', $idPersona)
            ->where('estado', 'Activo')
            ->value('id_docente');

        return $idDocente === null ? null : (int) $idDocente;
    }

    public function estudianteActivoDePersonaEnEscuela(int $idPersona, int $idEscuela): ?int
    {
        $idEstudiante = DB::table('core.estudiante')
            ->where('id_persona', $idPersona)
            ->where('id_escuela', $idEscuela)
            ->where('estado', 'Activo')
            ->value('id_estudiante');

        return $idEstudiante === null ? null : (int) $idEstudiante;
    }

    /**
     * @return list<object{id_estudiante: int, id_escuela: int}>
     */
    public function estudiantesActivosDePersona(int $idPersona): array
    {
        return DB::table('core.estudiante')
            ->where('id_persona', $idPersona)
            ->where('estado', 'Activo')
            ->orderBy('id_estudiante')
            ->get(['id_estudiante', 'id_escuela'])
            ->map(static fn (object $row): object => (object) [
                'id_estudiante' => (int) $row->id_estudiante,
                'id_escuela' => (int) $row->id_escuela,
            ])
            ->all();
    }

    /**
     * @return list<object{id_linea: int, codigo: string, nombre: string}>
     */
    public function lineasActivasDeEscuela(int $idEscuela): array
    {
        return DB::table('core.linea_investigacion')
            ->where('id_escuela', $idEscuela)
            ->where('estado', 'Activo')
            ->orderBy('nombre')
            ->get(['id_linea', 'codigo', 'nombre'])
            ->map(static fn (object $row): object => (object) [
                'id_linea' => (int) $row->id_linea,
                'codigo' => (string) $row->codigo,
                'nombre' => (string) $row->nombre,
            ])
            ->all();
    }

    /**
     * @return list<object{id_seccion: int, seccion: string, curso_codigo: string, curso: string, periodo: string, habilitada: bool, version_configuracion: int|null}>
     */
    public function seccionesDeDocente(int $idDocente): array
    {
        return DB::table('core.seccion as s')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->join('core.periodo_academico as p', 'p.id_periodo', '=', 's.id_periodo')
            ->leftJoin('investigacion_formativa.seccion_configuracion as sc', 'sc.id_seccion', '=', 's.id_seccion')
            ->where('s.id_docente_responsable', $idDocente)
            ->where('s.estado', 'Activa')
            ->orderByDesc('p.fecha_inicio')
            ->orderBy('c.codigo')
            ->orderBy('s.codigo')
            ->get([
                's.id_seccion',
                's.codigo as seccion',
                'c.codigo as curso_codigo',
                'c.nombre as curso',
                'p.codigo as periodo',
                'sc.habilitada',
                'sc.version',
            ])
            ->map(static fn (object $row): object => (object) [
                'id_seccion' => (int) $row->id_seccion,
                'seccion' => (string) $row->seccion,
                'curso_codigo' => (string) $row->curso_codigo,
                'curso' => (string) $row->curso,
                'periodo' => (string) $row->periodo,
                'habilitada' => (bool) $row->habilitada,
                'version_configuracion' => $row->version === null ? null : (int) $row->version,
            ])
            ->all();
    }

    /**
     * @return list<object{id_seccion: int, seccion: string, curso_codigo: string, curso: string, periodo: string, id_grupo: int|null}>
     */
    public function seccionesDeEstudiante(int $idPersona): array
    {
        return DB::table('investigacion_formativa.participante_seccion as ps')
            ->join('core.estudiante as e', 'e.id_estudiante', '=', 'ps.id_estudiante')
            ->join('core.seccion as s', 's.id_seccion', '=', 'ps.id_seccion')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->join('core.periodo_academico as p', 'p.id_periodo', '=', 's.id_periodo')
            ->leftJoin('investigacion_formativa.grupo_estudiante as ge', function ($join): void {
                $join->on('ge.id_estudiante', '=', 'ps.id_estudiante')
                    ->on('ge.id_seccion', '=', 'ps.id_seccion');
            })
            ->leftJoin('investigacion_formativa.grupo as g', 'g.id_grupo', '=', 'ge.id_grupo')
            ->where('e.id_persona', $idPersona)
            ->where('ps.estado', 'activo')
            ->where('s.estado', 'Activa')
            ->orderByDesc('p.fecha_inicio')
            ->orderBy('c.codigo')
            ->orderBy('s.codigo')
            ->get([
                's.id_seccion',
                's.codigo as seccion',
                'c.codigo as curso_codigo',
                'c.nombre as curso',
                'p.codigo as periodo',
                'g.id_grupo',
            ])
            ->map(static fn (object $row): object => (object) [
                'id_seccion' => (int) $row->id_seccion,
                'seccion' => (string) $row->seccion,
                'curso_codigo' => (string) $row->curso_codigo,
                'curso' => (string) $row->curso,
                'periodo' => (string) $row->periodo,
                'id_grupo' => $row->id_grupo === null ? null : (int) $row->id_grupo,
            ])
            ->all();
    }

    /**
     * @return list<object{id_escuela: int, nombre: string}>
     */
    public function escuelasDeDirector(int $userId): array
    {
        return DB::table('investigacion_formativa.director_escuela as d')
            ->join('core.escuela_profesional as e', 'e.id_escuela', '=', 'd.id_escuela')
            ->where('d.user_id', $userId)
            ->where('d.activo', true)
            ->orderBy('e.nombre')
            ->get(['e.id_escuela', 'e.nombre'])
            ->map(static fn (object $row): object => (object) [
                'id_escuela' => (int) $row->id_escuela,
                'nombre' => (string) $row->nombre,
            ])
            ->all();
    }

    /**
     * @return list<object{id_escuela: int, nombre: string}>
     */
    public function escuelasDeSecretaria(int $userId): array
    {
        return DB::table('investigacion_formativa.secretaria_escuela as s')
            ->join('core.escuela_profesional as e', 'e.id_escuela', '=', 's.id_escuela')
            ->where('s.user_id', $userId)
            ->where('s.activo', true)
            ->orderBy('e.nombre')
            ->get(['e.id_escuela', 'e.nombre'])
            ->map(static fn (object $row): object => (object) [
                'id_escuela' => (int) $row->id_escuela,
                'nombre' => (string) $row->nombre,
            ])
            ->all();
    }

    /**
     * Secciones habilitadas del módulo en las escuelas asignadas a la secretaría.
     *
     * @return list<object{
     *     id_seccion: int,
     *     seccion: string,
     *     curso_codigo: string,
     *     curso: string,
     *     periodo: string,
     *     id_escuela: int,
     *     escuela: string
     * }>
     */
    public function seccionesHabilitadasDeSecretaria(int $userId): array
    {
        return DB::table('investigacion_formativa.secretaria_escuela as s')
            ->join('core.escuela_profesional as e', 'e.id_escuela', '=', 's.id_escuela')
            ->join('core.plan_estudios as pe', 'pe.id_escuela', '=', 'e.id_escuela')
            ->join('core.curso_plan as cp', 'cp.id_plan', '=', 'pe.id_plan')
            ->join('core.seccion as sec', 'sec.id_curso_plan', '=', 'cp.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->join('core.periodo_academico as p', 'p.id_periodo', '=', 'sec.id_periodo')
            ->join('investigacion_formativa.seccion_configuracion as sc', 'sc.id_seccion', '=', 'sec.id_seccion')
            ->where('s.user_id', $userId)
            ->where('s.activo', true)
            ->where('sec.estado', 'Activa')
            ->where('sc.habilitada', true)
            ->orderByDesc('p.fecha_inicio')
            ->orderBy('c.codigo')
            ->orderBy('sec.codigo')
            ->get([
                'sec.id_seccion',
                'sec.codigo as seccion',
                'c.codigo as curso_codigo',
                'c.nombre as curso',
                'p.codigo as periodo',
                'e.id_escuela',
                'e.nombre as escuela',
            ])
            ->map(static fn (object $row): object => (object) [
                'id_seccion' => (int) $row->id_seccion,
                'seccion' => (string) $row->seccion,
                'curso_codigo' => (string) $row->curso_codigo,
                'curso' => (string) $row->curso,
                'periodo' => (string) $row->periodo,
                'id_escuela' => (int) $row->id_escuela,
                'escuela' => (string) $row->escuela,
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    public function periodosDeDirector(int $userId): array
    {
        return DB::table('investigacion_formativa.director_escuela as d')
            ->join('core.plan_estudios as pe', 'pe.id_escuela', '=', 'd.id_escuela')
            ->join('core.curso_plan as cp', 'cp.id_plan', '=', 'pe.id_plan')
            ->join('core.seccion as s', 's.id_curso_plan', '=', 'cp.id_curso_plan')
            ->join('core.periodo_academico as p', 'p.id_periodo', '=', 's.id_periodo')
            ->where('d.user_id', $userId)
            ->where('d.activo', true)
            ->select('p.codigo', 'p.fecha_inicio')
            ->orderByDesc('p.fecha_inicio')
            ->get()
            ->unique('codigo')
            ->pluck('codigo')
            ->map(static fn (mixed $codigo): string => (string) $codigo)
            ->values()
            ->all();
    }

    /**
     * @return LengthAwarePaginator<int, object{
     *     id_seccion: int,
     *     seccion: string,
     *     curso_codigo: string,
     *     curso: string,
     *     periodo: string,
     *     id_escuela: int,
     *     escuela: string,
     *     docente: string|null,
     *     habilitada: bool,
     *     version_configuracion: int
     * }>
     */
    public function listarSeccionesDeDirector(
        int $userId,
        ?int $idEscuela = null,
        ?string $busqueda = null,
        ?string $periodo = null,
        int $porPagina = 20,
    ): LengthAwarePaginator {
        $termino = null;

        if ($busqueda !== null && trim($busqueda) !== '') {
            $escapado = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($busqueda));
            $termino = '%'.$escapado.'%';
        }

        return DB::table('investigacion_formativa.director_escuela as d')
            ->join('core.escuela_profesional as e', 'e.id_escuela', '=', 'd.id_escuela')
            ->join('core.plan_estudios as pe', 'pe.id_escuela', '=', 'e.id_escuela')
            ->join('core.curso_plan as cp', 'cp.id_plan', '=', 'pe.id_plan')
            ->join('core.seccion as s', 's.id_curso_plan', '=', 'cp.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->join('core.periodo_academico as p', 'p.id_periodo', '=', 's.id_periodo')
            ->leftJoin('core.docente as doc', 'doc.id_docente', '=', 's.id_docente_responsable')
            ->leftJoin('core.persona as per', 'per.id_persona', '=', 'doc.id_persona')
            ->leftJoin('investigacion_formativa.seccion_configuracion as sc', 'sc.id_seccion', '=', 's.id_seccion')
            ->where('d.user_id', $userId)
            ->where('d.activo', true)
            ->when($idEscuela !== null, fn ($query) => $query->where('d.id_escuela', $idEscuela))
            ->when($periodo !== null && $periodo !== '', fn ($query) => $query->where('p.codigo', $periodo))
            ->when($termino !== null, fn ($query) => $query->whereRaw(
                '(c.nombre ILIKE ? OR c.codigo ILIKE ? OR s.codigo ILIKE ?)',
                [$termino, $termino, $termino],
            ))
            ->orderByDesc('p.fecha_inicio')
            ->orderBy('c.codigo')
            ->orderBy('s.codigo')
            ->paginate($porPagina, [
                's.id_seccion',
                's.codigo as seccion',
                'c.codigo as curso_codigo',
                'c.nombre as curso',
                'p.codigo as periodo',
                'e.id_escuela',
                'e.nombre as escuela',
                'per.nombres as docente_nombres',
                'per.apellido_paterno as docente_apellido',
                'sc.habilitada',
                'sc.version as version_configuracion',
            ])
            ->through(static fn (object $row): object => (object) [
                'id_seccion' => (int) $row->id_seccion,
                'seccion' => (string) $row->seccion,
                'curso_codigo' => (string) $row->curso_codigo,
                'curso' => (string) $row->curso,
                'periodo' => (string) $row->periodo,
                'id_escuela' => (int) $row->id_escuela,
                'escuela' => (string) $row->escuela,
                'docente' => $row->docente_nombres === null
                    ? null
                    : trim((string) $row->docente_nombres.' '.(string) $row->docente_apellido),
                'habilitada' => (bool) $row->habilitada,
                'version_configuracion' => $row->version_configuracion === null
                    ? 1
                    : (int) $row->version_configuracion,
            ]);
    }

    /**
     * @return list<object{id_seccion: int, id_periodo: int, id_curso_plan: int, id_escuela: int|null, id_docente_responsable: int|null, estado: string}>
     */
    public function seccionesDeEscuela(int $idEscuela): array
    {
        return DB::table('core.seccion as s')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->join('core.plan_estudios as pe', 'pe.id_plan', '=', 'cp.id_plan')
            ->where('pe.id_escuela', $idEscuela)
            ->select([
                's.id_seccion',
                's.id_periodo',
                's.id_curso_plan',
                's.id_docente_responsable',
                's.estado',
                'pe.id_escuela',
            ])
            ->orderBy('s.id_seccion')
            ->get()
            ->map(static fn (object $row): object => (object) [
                'id_seccion' => (int) $row->id_seccion,
                'id_periodo' => (int) $row->id_periodo,
                'id_curso_plan' => (int) $row->id_curso_plan,
                'id_escuela' => (int) $row->id_escuela,
                'id_docente_responsable' => $row->id_docente_responsable === null ? null : (int) $row->id_docente_responsable,
                'estado' => (string) $row->estado,
            ])
            ->all();
    }

    /**
     * @return list<object{id_estudiante: int, codigo: string, nombre: string}>
     */
    public function rosterActivoDeSeccion(int $idSeccion): array
    {
        return DB::table('investigacion_formativa.participante_seccion as ps')
            ->join('core.estudiante as e', 'e.id_estudiante', '=', 'ps.id_estudiante')
            ->join('core.persona as p', 'p.id_persona', '=', 'e.id_persona')
            ->where('ps.id_seccion', $idSeccion)
            ->where('ps.estado', 'activo')
            ->orderBy('p.apellido_paterno')
            ->orderBy('p.nombres')
            ->get([
                'e.id_estudiante',
                'e.codigo_universitario',
                'p.nombres',
                'p.apellido_paterno',
            ])
            ->map(static fn (object $row): object => (object) [
                'id_estudiante' => (int) $row->id_estudiante,
                'codigo' => (string) $row->codigo_universitario,
                'nombre' => trim((string) $row->nombres.' '.(string) $row->apellido_paterno),
            ])
            ->all();
    }

    /**
     * @return list<object{id_tipo_producto: int, nombre: string}>
     */
    public function tiposProducto(): array
    {
        return DB::table('investigacion_formativa.tipo_producto')
            ->orderBy('nombre')
            ->get(['id_tipo_producto', 'nombre'])
            ->map(static fn (object $row): object => (object) [
                'id_tipo_producto' => (int) $row->id_tipo_producto,
                'nombre' => (string) $row->nombre,
            ])
            ->all();
    }

    /**
     * @return list<object{id_seccion: int, seccion: string, curso_codigo: string, curso: string, periodo: string, id_grupo: int, grupo: string}>
     */
    public function seccionesRetiradasDeEstudiante(int $idPersona): array
    {
        return DB::table('investigacion_formativa.participante_seccion as ps')
            ->join('core.estudiante as e', 'e.id_estudiante', '=', 'ps.id_estudiante')
            ->join('core.seccion as s', 's.id_seccion', '=', 'ps.id_seccion')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->join('core.periodo_academico as p', 'p.id_periodo', '=', 's.id_periodo')
            ->join('investigacion_formativa.grupo_estudiante as ge', function ($join): void {
                $join->on('ge.id_seccion', '=', 'ps.id_seccion')
                    ->on('ge.id_estudiante', '=', 'ps.id_estudiante');
            })
            ->join('investigacion_formativa.grupo as g', 'g.id_grupo', '=', 'ge.id_grupo')
            ->where('e.id_persona', $idPersona)
            ->where('ps.estado', 'retirado')
            ->where('s.estado', 'Activa')
            ->orderByDesc('p.fecha_inicio')
            ->orderBy('c.codigo')
            ->orderBy('s.codigo')
            ->get([
                's.id_seccion',
                's.codigo as seccion',
                'c.codigo as curso_codigo',
                'c.nombre as curso',
                'p.codigo as periodo',
                'g.id_grupo',
                'g.nombre as grupo',
            ])
            ->map(static fn (object $row): object => (object) [
                'id_seccion' => (int) $row->id_seccion,
                'seccion' => (string) $row->seccion,
                'curso_codigo' => (string) $row->curso_codigo,
                'curso' => (string) $row->curso,
                'periodo' => (string) $row->periodo,
                'id_grupo' => (int) $row->id_grupo,
                'grupo' => (string) $row->grupo,
            ])
            ->all();
    }
}
