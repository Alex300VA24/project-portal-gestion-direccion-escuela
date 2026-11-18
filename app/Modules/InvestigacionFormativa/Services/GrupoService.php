<?php

namespace App\Modules\InvestigacionFormativa\Services;

use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Exceptions\EstadoInvalido;
use App\Modules\InvestigacionFormativa\Exceptions\RecursoBloqueado;
use App\Modules\InvestigacionFormativa\Exceptions\VersionDesactualizada;
use App\Modules\InvestigacionFormativa\Support\ActorInvestigacion;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class GrupoService
{
    private const ESTADOS_TITULO = ['Registrado', 'Aceptado', 'Cambio solicitado'];

    public function __construct(
        private readonly AccesoInvestigacionService $acceso,
        private readonly DatosAcademicosService $datos,
        private readonly ConfiguracionService $configuracion,
    ) {}

    /**
     * @param  array{nombre: string, titulo: string, integrante_ids: list<int>, id_linea: int, id_tipo_producto: int}  $datos
     * @return object{id_grupo: int, id_proyecto: int}
     */
    public function crear(ActorInvestigacion $actor, int $idSeccion, array $datos): object
    {
        if (! $actor->tieneRol('alumno') || ! $actor->user->can('investigacion-formativa.groups.create')) {
            throw new AccesoDenegado('No tiene permiso para crear grupos de investigación.');
        }

        $seccion = $this->datos->seccionConEscuela($idSeccion);

        if ($seccion === null || $seccion->id_escuela === null) {
            throw new AccesoDenegado('La sección solicitada no existe o no tiene escuela configurada.');
        }

        $idEstudiante = $this->acceso->idEstudianteEnEscuela($actor, $seccion->id_escuela);

        if ($idEstudiante === null || ! $this->acceso->participanteActivo($idSeccion, $idEstudiante)) {
            throw new AccesoDenegado('No figura en el padrón activo de la sección.');
        }

        $integrantes = array_values(array_unique(array_map('intval', $datos['integrante_ids'])));
        sort($integrantes);

        if (count($integrantes) < 1 || count($integrantes) > 20) {
            throw new EstadoInvalido('El grupo debe tener entre 1 y 20 integrantes.');
        }

        if (! in_array($idEstudiante, $integrantes, true)) {
            throw new EstadoInvalido('El creador debe figurar como integrante del grupo.');
        }

        return DB::transaction(function () use ($actor, $seccion, $idSeccion, $datos, $integrantes, $idEstudiante): object {
            $config = $this->configuracion->bloquearParaEscritura($idSeccion);
            $seccionActual = $this->datos->seccionConEscuela($idSeccion);

            if ($seccionActual === null
                || $seccionActual->id_escuela === null
                || (int) $seccionActual->id_escuela !== (int) $seccion->id_escuela) {
                throw new AccesoDenegado('La sección cambió de escuela durante la creación del grupo.');
            }

            $this->exigirCreadorActivo(
                $actor,
                $idSeccion,
                (int) $seccionActual->id_escuela,
                $idEstudiante,
            );

            if (! (bool) $config->habilitada) {
                throw new EstadoInvalido('La sección no está habilitada para Investigación Formativa.');
            }

            if (! $this->configuracion->estaConfigurada($idSeccion)) {
                throw new EstadoInvalido('La configuración de la sección está incompleta.');
            }

            $enPadron = DB::table('investigacion_formativa.participante_seccion')
                ->where('id_seccion', $idSeccion)
                ->whereIn('id_estudiante', $integrantes)
                ->where('estado', 'activo')
                ->pluck('id_estudiante')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            if (count($enPadron) !== count($integrantes)) {
                throw new EstadoInvalido('Todos los integrantes deben figurar en el padrón activo de la sección.');
            }

            $yaAgrupados = DB::table('investigacion_formativa.grupo_estudiante')
                ->where('id_seccion', $idSeccion)
                ->whereIn('id_estudiante', $integrantes)
                ->pluck('id_estudiante')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            if ($yaAgrupados !== []) {
                throw new EstadoInvalido('Uno o más integrantes ya pertenecen a un grupo de la sección.');
            }

            $lineas = array_map(
                static fn (object $linea): int => (int) $linea->id_linea,
                $this->datos->lineasActivasDeEscuela((int) $seccion->id_escuela),
            );

            if (! in_array((int) $datos['id_linea'], $lineas, true)) {
                throw new EstadoInvalido('La línea de investigación no está activa en la escuela de la sección.');
            }

            if (! DB::table('investigacion_formativa.tipo_producto')
                ->where('id_tipo_producto', (int) $datos['id_tipo_producto'])
                ->exists()) {
                throw new EstadoInvalido('El tipo de producto seleccionado no existe.');
            }

            $nombre = trim((string) $datos['nombre']);
            $titulo = trim((string) $datos['titulo']);

            if ($nombre === '' || $titulo === '') {
                throw new EstadoInvalido('El nombre del grupo y el título propuesto son obligatorios.');
            }

            try {
                $idGrupo = DB::table('investigacion_formativa.grupo')->insertGetId([
                    'id_seccion' => $idSeccion,
                    'nombre' => $nombre,
                ], 'id_grupo');

                foreach ($integrantes as $idIntegrante) {
                    DB::table('investigacion_formativa.grupo_estudiante')->insert([
                        'id_grupo' => $idGrupo,
                        'id_seccion' => $idSeccion,
                        'id_estudiante' => $idIntegrante,
                        'es_coordinador' => $idIntegrante === $idEstudiante,
                    ]);
                }

                $idProyecto = DB::table('investigacion_formativa.proyecto')->insertGetId([
                    'id_grupo' => $idGrupo,
                    'id_linea' => (int) $datos['id_linea'],
                    'id_tipo_producto' => (int) $datos['id_tipo_producto'],
                    'titulo' => $titulo,
                    'estado' => 'Registrado',
                    'version' => 1,
                ], 'id_proyecto');

                DB::table('investigacion_formativa.revision_titulo')->insert([
                    'id_proyecto' => $idProyecto,
                    'titulo' => $titulo,
                    'estado' => 'Registrado',
                    'motivo' => null,
                    'user_id' => $actor->user->id,
                ]);
            } catch (QueryException $exception) {
                throw $this->traducirConflicto($exception);
            }

            return (object) [
                'id_grupo' => (int) $idGrupo,
                'id_proyecto' => (int) $idProyecto,
            ];
        });
    }

    public function actualizarTitulo(
        ActorInvestigacion $actor,
        int $idProyecto,
        string $titulo,
        int $version,
    ): void {
        $proyecto = $this->proyectoConSeccion($idProyecto);

        if (! $actor->user->can('investigacion-formativa.projects.update')) {
            throw new AccesoDenegado('No tiene permiso para editar títulos.');
        }

        $this->acceso->exigirCoordinador($actor, $proyecto->id_grupo);

        $titulo = trim($titulo);

        if ($titulo === '') {
            throw new EstadoInvalido('El título propuesto es obligatorio.');
        }

        DB::transaction(function () use ($actor, $proyecto, $titulo, $version, $idProyecto): void {
            $config = $this->configuracion->bloquearParaEscritura($proyecto->id_seccion);

            if (! (bool) $config->habilitada) {
                throw new EstadoInvalido('La sección está deshabilitada. No se aceptan cambios de título.');
            }

            if (! $this->configuracion->estaConfigurada($proyecto->id_seccion)) {
                throw new EstadoInvalido('La configuración de la sección está incompleta.');
            }

            $bloqueado = DB::table('investigacion_formativa.proyecto')
                ->where('id_proyecto', $idProyecto)
                ->lockForUpdate()
                ->first();

            if ((int) $bloqueado->version !== $version) {
                throw new VersionDesactualizada(
                    'El título cambió en otra sesión. Recargue la página y vuelva a intentarlo.',
                );
            }

            $this->exigirTituloEditable($idProyecto, $proyecto->id_seccion);
            $this->exigirCoordinadorActual($actor, $proyecto->id_grupo);

            DB::table('investigacion_formativa.proyecto')
                ->where('id_proyecto', $idProyecto)
                ->update([
                    'titulo' => $titulo,
                    'estado' => 'Registrado',
                    'version' => $version + 1,
                ]);

            DB::table('investigacion_formativa.revision_titulo')->insert([
                'id_proyecto' => $idProyecto,
                'titulo' => $titulo,
                'estado' => 'Registrado',
                'motivo' => null,
                'user_id' => $actor->user->id,
            ]);
        });
    }

    public function revisarTitulo(
        ActorInvestigacion $actor,
        int $idProyecto,
        string $estado,
        ?string $motivo,
        int $version,
    ): void {
        $proyecto = $this->proyectoConSeccion($idProyecto);

        if (! $actor->user->can('investigacion-formativa.groups.review')
            || ! $this->acceso->puedeVerSeccion($actor->user, $proyecto->id_seccion, 'profesor')) {
            throw new AccesoDenegado('No es el docente responsable de la sección.');
        }

        if (! in_array($estado, self::ESTADOS_TITULO, true) || $estado === 'Registrado') {
            throw new EstadoInvalido('El estado de revisión debe ser Aceptado o Cambio solicitado.');
        }

        $motivo = $motivo === null ? null : trim($motivo);

        if ($estado === 'Cambio solicitado' && ($motivo === null || $motivo === '')) {
            throw new EstadoInvalido('Debe indicar el motivo del cambio solicitado.');
        }

        if ($motivo !== null && mb_strlen($motivo) > 2000) {
            throw new EstadoInvalido('El motivo no puede superar los 2000 caracteres.');
        }

        DB::transaction(function () use ($actor, $idProyecto, $proyecto, $estado, $motivo, $version): void {
            $config = $this->configuracion->bloquearParaEscritura($proyecto->id_seccion);

            if (! (bool) $config->habilitada) {
                throw new EstadoInvalido('La sección está deshabilitada. No se aceptan revisiones de título.');
            }

            $bloqueado = DB::table('investigacion_formativa.proyecto')
                ->where('id_proyecto', $idProyecto)
                ->lockForUpdate()
                ->first();

            if ((int) $bloqueado->version !== $version) {
                throw new VersionDesactualizada(
                    'El título cambió en otra sesión. Recargue la página y vuelva a intentarlo.',
                );
            }

            if ($this->proyectoTieneEntregas($idProyecto)) {
                throw new RecursoBloqueado(
                    'El título ya se usó en una entrega registrada. No admite revisión.',
                );
            }

            $this->exigirDocenteResponsableActual($actor, $proyecto->id_seccion);

            DB::table('investigacion_formativa.proyecto')
                ->where('id_proyecto', $idProyecto)
                ->update([
                    'estado' => $estado,
                    'version' => $version + 1,
                ]);

            DB::table('investigacion_formativa.revision_titulo')->insert([
                'id_proyecto' => $idProyecto,
                'titulo' => (string) $bloqueado->titulo,
                'estado' => $estado,
                'motivo' => $estado === 'Cambio solicitado' ? $motivo : null,
                'user_id' => $actor->user->id,
            ]);
        });
    }

    /**
     * Motivo visible por el que el coordinador ya no puede editar el título.
     */
    public function motivoBloqueoTitulo(int $idProyecto, int $idSeccion): ?string
    {
        $estado = $this->configuracion->estado($idSeccion);

        if (! $estado->habilitada) {
            return 'La sección está deshabilitada para Investigación Formativa.';
        }

        if (! $estado->configurada) {
            return 'La configuración de la sección está incompleta.';
        }

        if ($this->proyectoTieneEntregas($idProyecto)) {
            return 'El título ya se usó en una entrega registrada.';
        }

        $limite = DB::table('investigacion_formativa.entregable')
            ->where('id_seccion', $idSeccion)
            ->where('orden', 1)
            ->value('fecha_limite');

        if ($limite !== null && CarbonImmutable::parse($limite)->lte(CarbonImmutable::now())) {
            return 'El plazo de la unidad 1 ya cerró.';
        }

        return null;
    }

    /**
     * @return object{id_grupo: int, nombre: string, es_coordinador: bool}|null
     */
    public function grupoDeEstudianteEnSeccion(int $idPersona, int $idSeccion): ?object
    {
        $fila = DB::table('investigacion_formativa.grupo_estudiante as ge')
            ->join('investigacion_formativa.grupo as g', 'g.id_grupo', '=', 'ge.id_grupo')
            ->join('core.estudiante as e', 'e.id_estudiante', '=', 'ge.id_estudiante')
            ->where('g.id_seccion', $idSeccion)
            ->where('e.id_persona', $idPersona)
            ->select(['g.id_grupo', 'g.nombre', 'ge.es_coordinador'])
            ->first();

        return $fila === null ? null : (object) [
            'id_grupo' => (int) $fila->id_grupo,
            'nombre' => (string) $fila->nombre,
            'es_coordinador' => (bool) $fila->es_coordinador,
        ];
    }

    /**
     * @return object{
     *     grupo: object{id_grupo: int, id_seccion: int, nombre: string},
     *     proyecto: object{id_proyecto: int, titulo: string, estado: string, version: int, linea: string, tipo_producto: string, resumen: string|null}|null,
     *     miembros: list<object{id_estudiante: int, nombre: string, codigo: string, es_coordinador: bool}>,
     *     historial: list<object{titulo: string, estado: string, motivo: string|null, autor: string, registrado_en: string}>,
     *     entregados: int
     * }|null
     */
    public function detalleGrupo(int $idGrupo): ?object
    {
        $grupo = DB::table('investigacion_formativa.grupo')
            ->where('id_grupo', $idGrupo)
            ->first(['id_grupo', 'id_seccion', 'nombre']);

        if ($grupo === null) {
            return null;
        }

        $proyecto = DB::table('investigacion_formativa.proyecto as p')
            ->leftJoin('core.linea_investigacion as l', 'l.id_linea', '=', 'p.id_linea')
            ->leftJoin('investigacion_formativa.tipo_producto as t', 't.id_tipo_producto', '=', 'p.id_tipo_producto')
            ->where('p.id_grupo', $idGrupo)
            ->select([
                'p.id_proyecto',
                'p.titulo',
                'p.estado',
                'p.version',
                'p.resumen',
                'l.nombre as linea',
                't.nombre as tipo_producto',
            ])
            ->first();

        $miembros = DB::table('investigacion_formativa.grupo_estudiante as ge')
            ->join('core.estudiante as e', 'e.id_estudiante', '=', 'ge.id_estudiante')
            ->join('core.persona as per', 'per.id_persona', '=', 'e.id_persona')
            ->where('ge.id_grupo', $idGrupo)
            ->orderByDesc('ge.es_coordinador')
            ->orderBy('per.apellido_paterno')
            ->orderBy('per.nombres')
            ->get([
                'e.id_estudiante',
                'e.codigo_universitario',
                'per.nombres',
                'per.apellido_paterno',
                'ge.es_coordinador',
            ])
            ->map(static fn (object $fila): object => (object) [
                'id_estudiante' => (int) $fila->id_estudiante,
                'nombre' => trim((string) $fila->nombres.' '.(string) $fila->apellido_paterno),
                'codigo' => (string) $fila->codigo_universitario,
                'es_coordinador' => (bool) $fila->es_coordinador,
            ])
            ->all();

        $historial = $proyecto === null
            ? []
            : DB::table('investigacion_formativa.revision_titulo as r')
                ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
                ->where('r.id_proyecto', $proyecto->id_proyecto)
                ->orderByDesc('r.registrado_en')
                ->orderByDesc('r.id_revision')
                ->get(['r.titulo', 'r.estado', 'r.motivo', 'r.registrado_en', 'u.name as autor'])
                ->map(static fn (object $fila): object => (object) [
                    'titulo' => (string) $fila->titulo,
                    'estado' => (string) $fila->estado,
                    'motivo' => $fila->motivo === null ? null : (string) $fila->motivo,
                    'autor' => $fila->autor === null ? 'Sistema' : (string) $fila->autor,
                    'registrado_en' => (string) $fila->registrado_en,
                ])
                ->all();

        $entregados = $proyecto === null
            ? 0
            : DB::table('investigacion_formativa.entrega')
                ->where('id_proyecto', $proyecto->id_proyecto)
                ->distinct()
                ->count('id_entregable');

        return (object) [
            'grupo' => (object) [
                'id_grupo' => (int) $grupo->id_grupo,
                'id_seccion' => (int) $grupo->id_seccion,
                'nombre' => (string) $grupo->nombre,
            ],
            'proyecto' => $proyecto === null ? null : (object) [
                'id_proyecto' => (int) $proyecto->id_proyecto,
                'titulo' => (string) $proyecto->titulo,
                'estado' => (string) $proyecto->estado,
                'version' => (int) $proyecto->version,
                'linea' => (string) ($proyecto->linea ?? ''),
                'tipo_producto' => (string) ($proyecto->tipo_producto ?? ''),
                'resumen' => $proyecto->resumen === null ? null : (string) $proyecto->resumen,
            ],
            'miembros' => $miembros,
            'historial' => $historial,
            'entregados' => (int) $entregados,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, object{
     *     id_grupo: int,
     *     nombre: string,
     *     coordinador: string|null,
     *     titulo: string|null,
     *     estado: string|null,
     *     version: int|null,
     *     entregados: int
     * }>
     */
    public function listarGruposDeSeccion(
        int $idSeccion,
        ?int $idLinea = null,
        ?string $busqueda = null,
        int $porPagina = 20,
    ): LengthAwarePaginator {
        $termino = null;

        if ($busqueda !== null && trim($busqueda) !== '') {
            $escapado = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($busqueda));
            $termino = '%'.$escapado.'%';
        }

        $avances = DB::table('investigacion_formativa.entrega as en')
            ->join('investigacion_formativa.proyecto as pr', 'pr.id_proyecto', '=', 'en.id_proyecto')
            ->selectRaw('pr.id_grupo, count(distinct en.id_entregable) as entregados')
            ->groupBy('pr.id_grupo');

        return DB::table('investigacion_formativa.grupo as g')
            ->leftJoin('investigacion_formativa.proyecto as p', 'p.id_grupo', '=', 'g.id_grupo')
            ->leftJoin('investigacion_formativa.grupo_estudiante as ge', function ($join): void {
                $join->on('ge.id_grupo', '=', 'g.id_grupo')
                    ->where('ge.es_coordinador', true);
            })
            ->leftJoin('core.estudiante as e', 'e.id_estudiante', '=', 'ge.id_estudiante')
            ->leftJoin('core.persona as per', 'per.id_persona', '=', 'e.id_persona')
            ->leftJoinSub($avances, 'av', 'av.id_grupo', '=', 'g.id_grupo')
            ->where('g.id_seccion', $idSeccion)
            ->when($idLinea !== null, fn ($query) => $query->where('p.id_linea', $idLinea))
            ->when($termino !== null, fn ($query) => $query->whereRaw(
                '(g.nombre ILIKE ? OR p.titulo ILIKE ?)',
                [$termino, $termino],
            ))
            ->orderBy('g.nombre')
            ->paginate($porPagina, [
                'g.id_grupo',
                'g.nombre',
                'p.titulo',
                'p.estado',
                'p.version',
                'per.nombres as coordinador_nombres',
                'per.apellido_paterno as coordinador_apellido',
                'av.entregados',
            ])
            ->through(static fn (object $fila): object => (object) [
                'id_grupo' => (int) $fila->id_grupo,
                'nombre' => (string) $fila->nombre,
                'coordinador' => $fila->coordinador_nombres === null
                    ? null
                    : trim((string) $fila->coordinador_nombres.' '.(string) $fila->coordinador_apellido),
                'titulo' => $fila->titulo === null ? null : (string) $fila->titulo,
                'estado' => $fila->estado === null ? null : (string) $fila->estado,
                'version' => $fila->version === null ? null : (int) $fila->version,
                'entregados' => (int) ($fila->entregados ?? 0),
            ]);
    }

    /**
     * @return object{id_proyecto: int, id_grupo: int, id_seccion: int, titulo: string, estado: string, version: int}
     */
    private function exigirCreadorActivo(
        ActorInvestigacion $actor,
        int $idSeccion,
        int $idEscuela,
        int $idEstudianteEsperado,
    ): void {
        $actual = $this->acceso->resolver($actor->user);

        if (! $actual->tieneRol('alumno')
            || ! $actual->user->can('investigacion-formativa.groups.create')) {
            throw new AccesoDenegado('No tiene permiso para crear grupos de investigación.');
        }

        $idEstudiante = $this->acceso->idEstudianteEnEscuela($actual, $idEscuela);

        if ($idEstudiante === null
            || $idEstudiante !== $idEstudianteEsperado
            || ! $this->acceso->participanteActivo($idSeccion, $idEstudiante)) {
            throw new AccesoDenegado('El creador ya no figura como participante activo de la sección.');
        }
    }

    private function exigirCoordinadorActual(ActorInvestigacion $actor, int $idGrupo): void
    {
        $actual = $this->acceso->resolver($actor->user);
        $this->acceso->exigirCoordinador($actual, $idGrupo);
    }

    private function exigirDocenteResponsableActual(ActorInvestigacion $actor, int $idSeccion): void
    {
        $actual = $this->acceso->resolver($actor->user);

        if (! $actual->user->can('investigacion-formativa.groups.review')
            || ! $this->acceso->puedeVerSeccion($actual->user, $idSeccion, 'profesor')) {
            throw new AccesoDenegado('No es el docente responsable de la sección.');
        }
    }

    private function proyectoConSeccion(int $idProyecto): object
    {
        $fila = DB::table('investigacion_formativa.proyecto as p')
            ->join('investigacion_formativa.grupo as g', 'g.id_grupo', '=', 'p.id_grupo')
            ->where('p.id_proyecto', $idProyecto)
            ->select([
                'p.id_proyecto',
                'p.id_grupo',
                'p.titulo',
                'p.estado',
                'p.version',
                'g.id_seccion',
            ])
            ->first();

        if ($fila === null) {
            throw new AccesoDenegado('El proyecto solicitado no existe.');
        }

        return (object) [
            'id_proyecto' => (int) $fila->id_proyecto,
            'id_grupo' => (int) $fila->id_grupo,
            'id_seccion' => (int) $fila->id_seccion,
            'titulo' => (string) $fila->titulo,
            'estado' => (string) $fila->estado,
            'version' => (int) $fila->version,
        ];
    }

    private function exigirTituloEditable(int $idProyecto, int $idSeccion): void
    {
        if ($this->proyectoTieneEntregas($idProyecto)) {
            throw new RecursoBloqueado(
                'El título ya se usó en una entrega registrada. Ya no se puede editar.',
            );
        }

        $limite = DB::table('investigacion_formativa.entregable')
            ->where('id_seccion', $idSeccion)
            ->where('orden', 1)
            ->value('fecha_limite');

        if ($limite !== null && CarbonImmutable::parse($limite)->lte(CarbonImmutable::now())) {
            throw new RecursoBloqueado(
                'El plazo de la unidad 1 ya cerró. El título ya no se puede editar.',
            );
        }
    }

    private function proyectoTieneEntregas(int $idProyecto): bool
    {
        return DB::table('investigacion_formativa.entrega')
            ->where('id_proyecto', $idProyecto)
            ->exists();
    }

    private function traducirConflicto(QueryException $exception): EstadoInvalido
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $detalle = (string) ($exception->errorInfo[2] ?? $exception->getMessage());

        if ($sqlState !== '23505') {
            throw $exception;
        }

        $mensajes = [
            'if_grupo_seccion_nombre_uq' => 'Ya existe un grupo con ese nombre en la sección.',
            'if_grupo_estudiante_pk' => 'Uno o más integrantes ya pertenecen a un grupo de la sección.',
            'if_grupo_estudiante_seccion_estudiante_uq' => 'Uno o más integrantes ya pertenecen a un grupo de la sección.',
            'if_grupo_estudiante_coordinador_uq' => 'El grupo ya tiene un coordinador asignado.',
            'if_proyecto_grupo_uq' => 'El grupo ya tiene un proyecto registrado.',
        ];

        foreach ($mensajes as $constraint => $mensaje) {
            if (str_contains($detalle, $constraint)) {
                return new EstadoInvalido($mensaje);
            }
        }

        throw $exception;
    }
}
