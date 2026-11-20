<?php

namespace App\Modules\InvestigacionFormativa\Services;

use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Exceptions\EstadoInvalido;
use App\Modules\InvestigacionFormativa\Exceptions\VersionDesactualizada;
use App\Modules\InvestigacionFormativa\Support\ActorInvestigacion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Decisiones docentes sobre el trabajo final.
 *
 * Mantiene dos flujos append-only y separados: la aprobación final del trabajo
 * y la selección manual del trabajo destacado. Ninguno se deduce de la nota ni
 * del estado del título. Cada decisión guarda una instantánea de las versiones
 * de entrega y evaluación usadas; si esa evidencia cambia, la decisión queda
 * desactualizada hasta que el docente la confirme.
 */
final class DecisionTrabajoService
{
    public function __construct(
        private readonly AccesoInvestigacionService $acceso,
        private readonly ConfiguracionService $configuracion,
        private readonly DatosAcademicosService $datos,
    ) {}

    public function aprobar(ActorInvestigacion $actor, int $idProyecto, int $versionEsperada): void
    {
        $contexto = $this->contextoProyecto($idProyecto);
        $this->exigirDocente($actor, $contexto->id_seccion);

        DB::transaction(function () use ($actor, $contexto, $versionEsperada): void {
            $this->bloquearSeccionOperable($contexto->id_seccion);
            $this->exigirDocenteActual($actor, $contexto->id_seccion);
            $this->exigirVersionAprobacion($contexto->id_seccion, $contexto->id_proyecto, $versionEsperada);

            $evidencia = $this->evidenciaActual($contexto->id_proyecto);

            if ($evidencia === null) {
                throw new EstadoInvalido(
                    'El trabajo debe tener las tres unidades con su versión más reciente evaluada antes de aprobarlo.',
                );
            }

            $this->registrar(
                $actor,
                $contexto->id_seccion,
                'aprobacion',
                'registrar',
                $contexto->id_proyecto,
                $versionEsperada + 1,
                $evidencia,
                null,
            );
        });
    }

    public function seleccionar(ActorInvestigacion $actor, int $idProyecto, int $versionEsperada): void
    {
        $contexto = $this->contextoProyecto($idProyecto);
        $this->exigirDocente($actor, $contexto->id_seccion);

        DB::transaction(function () use ($actor, $contexto, $versionEsperada): void {
            $this->bloquearSeccionOperable($contexto->id_seccion);
            $this->exigirDocenteActual($actor, $contexto->id_seccion);
            $this->exigirVersionSeleccion($contexto->id_seccion, $versionEsperada);

            if (! $this->aprobacionVigente($contexto->id_seccion, $contexto->id_proyecto)) {
                throw new EstadoInvalido(
                    'El trabajo no tiene una aprobación final vigente. Apruebe o confirme la aprobación antes de destacarlo.',
                );
            }

            $evidencia = $this->evidenciaActual($contexto->id_proyecto);

            if ($evidencia === null) {
                throw new EstadoInvalido(
                    'La evidencia del trabajo cambió. Confirme la aprobación antes de destacarlo.',
                );
            }

            $this->registrar(
                $actor,
                $contexto->id_seccion,
                'seleccion',
                'registrar',
                $contexto->id_proyecto,
                $versionEsperada + 1,
                $evidencia,
                null,
            );
        });
    }

    public function retirarSeleccion(
        ActorInvestigacion $actor,
        int $idProyecto,
        int $versionEsperada,
        ?string $motivo = null,
    ): void {
        $contexto = $this->contextoProyecto($idProyecto);
        $this->exigirDocente($actor, $contexto->id_seccion);
        $motivo = $motivo === null ? null : trim($motivo);

        if ($motivo !== null && mb_strlen($motivo) > 2000) {
            throw new EstadoInvalido('El motivo no puede superar los 2000 caracteres.');
        }

        DB::transaction(function () use ($actor, $contexto, $versionEsperada, $motivo): void {
            $this->bloquearSeccionOperable($contexto->id_seccion);
            $this->exigirDocenteActual($actor, $contexto->id_seccion);
            $this->exigirVersionSeleccion($contexto->id_seccion, $versionEsperada);

            $actual = $this->ultimaSeleccion($contexto->id_seccion);

            if ($actual === null
                || $actual->accion !== 'registrar'
                || (int) $actual->id_proyecto !== $contexto->id_proyecto) {
                throw new EstadoInvalido('El grupo indicado no es el trabajo destacado vigente.');
            }

            $this->registrar(
                $actor,
                $contexto->id_seccion,
                'seleccion',
                'retirar',
                $contexto->id_proyecto,
                $versionEsperada + 1,
                [],
                $motivo,
            );
        });
    }

    public function versionAprobacion(int $idSeccion, int $idProyecto): int
    {
        return (int) DB::table('investigacion_formativa.decision_trabajo')
            ->where('id_seccion', $idSeccion)
            ->where('tipo', 'aprobacion')
            ->where('id_proyecto', $idProyecto)
            ->max('version');
    }

    public function versionSeleccion(int $idSeccion): int
    {
        return (int) DB::table('investigacion_formativa.decision_trabajo')
            ->where('id_seccion', $idSeccion)
            ->where('tipo', 'seleccion')
            ->max('version');
    }

    /**
     * Estado de las decisiones de una sección listo para vistas y reportes.
     *
     * @return object{
     *     id_seccion: int,
     *     aprobados: int,
     *     destacados: int,
     *     seleccion: object{id_proyecto: int, id_grupo: int, nombre: string, titulo: string, desactualizada: bool, decidido_en: string|null}|null,
     *     por_grupo: array<int, object{aprobado: bool, aprobacion_desactualizada: bool, destacado: bool, seleccion_desactualizada: bool}>
     * }
     */
    public function estadoDeSeccion(int $idSeccion): object
    {
        $estado = $this->cargarEstados([$idSeccion])[(int) $idSeccion];

        $grupos = DB::table('investigacion_formativa.grupo as g')
            ->leftJoin('investigacion_formativa.proyecto as p', 'p.id_grupo', '=', 'g.id_grupo')
            ->where('g.id_seccion', $idSeccion)
            ->get(['g.id_grupo', 'p.id_proyecto']);

        $porGrupo = [];

        foreach ($grupos as $grupo) {
            $idProyecto = $grupo->id_proyecto === null ? null : (int) $grupo->id_proyecto;
            $esDestacado = $idProyecto !== null && $estado->destacado === $idProyecto;
            $seleccionDesactualizada = $idProyecto !== null
                && $estado->seleccion !== null
                && $estado->seleccion->id_proyecto === $idProyecto
                && $estado->seleccion->desactualizada;

            $porGrupo[(int) $grupo->id_grupo] = (object) [
                'aprobado' => $idProyecto !== null && ($estado->aprobaciones[$idProyecto] ?? false),
                'aprobacion_desactualizada' => $idProyecto !== null
                    && ($estado->aprobaciones_desactualizadas[$idProyecto] ?? false),
                'destacado' => $esDestacado,
                'seleccion_desactualizada' => $seleccionDesactualizada,
            ];
        }

        return (object) [
            'id_seccion' => (int) $idSeccion,
            'aprobados' => $estado->aprobados,
            'destacados' => $estado->destacado === null ? 0 : 1,
            'seleccion' => $estado->seleccion_info,
            'por_grupo' => $porGrupo,
        ];
    }

    /**
     * Conteos de aprobaciones y trabajos destacados por sección, sin consultas
     * proporcionales al número de secciones.
     *
     * Cuando se pasan proyectos permitidos, solo cuenta las aprobaciones de
     * esos proyectos y conserva la selección únicamente si pertenece al
     * conjunto. Se usa para reportes filtrados por línea de investigación.
     *
     * @param  list<int>  $idsSecciones
     * @param  array<int, list<int>>|null  $proyectosPermitidos
     * @return array<int, object{aprobados: int, destacados: int, seleccion: object|null}>
     */
    public function resumenesPorSecciones(array $idsSecciones, ?array $proyectosPermitidos = null): array
    {
        $estados = $this->cargarEstados($idsSecciones);
        $resumenes = [];

        foreach ($idsSecciones as $idSeccion) {
            $estado = $estados[(int) $idSeccion];
            $aprobados = $estado->aprobados;
            $destacados = $estado->destacado === null ? 0 : 1;
            $seleccion = $estado->seleccion_info;

            if ($proyectosPermitidos !== null) {
                $permitidos = $proyectosPermitidos[(int) $idSeccion] ?? [];
                $aprobados = 0;

                foreach ($estado->aprobaciones as $idProyecto => $vigente) {
                    if ($vigente && in_array((int) $idProyecto, $permitidos, true)) {
                        $aprobados++;
                    }
                }

                if ($seleccion !== null && ! in_array((int) $seleccion->id_proyecto, $permitidos, true)) {
                    $seleccion = null;
                    $destacados = 0;
                }
            }

            $resumenes[(int) $idSeccion] = (object) [
                'aprobados' => $aprobados,
                'destacados' => $destacados,
                'seleccion' => $seleccion,
            ];
        }

        return $resumenes;
    }

    /**
     * @param  list<int>  $idsSecciones
     * @return array<int, object{
     *     aprobaciones: array<int, bool>,
     *     aprobaciones_desactualizadas: array<int, bool>,
     *     aprobados: int,
     *     destacado: int|null,
     *     seleccion: object{id_proyecto: int, desactualizada: bool, motivo: string|null, decidido_en: string}|null,
     *     seleccion_info: object{id_proyecto: int, id_grupo: int, nombre: string, titulo: string, desactualizada: bool, decidido_en: string|null}|null
     * }>
     */
    private function cargarEstados(array $idsSecciones): array
    {
        if ($idsSecciones === []) {
            return [];
        }

        $filasAprobacion = DB::table('investigacion_formativa.decision_trabajo')
            ->whereIn('id_seccion', $idsSecciones)
            ->where('tipo', 'aprobacion')
            ->orderBy('id_seccion')
            ->orderBy('id_proyecto')
            ->orderByDesc('version')
            ->get([
                'id_seccion',
                'accion',
                'id_proyecto',
                'version',
                'decidido_en',
                'evidencia',
                'motivo',
            ]);

        $filasSeleccion = DB::table('investigacion_formativa.decision_trabajo')
            ->whereIn('id_seccion', $idsSecciones)
            ->where('tipo', 'seleccion')
            ->orderBy('id_seccion')
            ->orderByDesc('version')
            ->get([
                'id_seccion',
                'accion',
                'id_proyecto',
                'version',
                'decidido_en',
                'evidencia',
                'motivo',
            ]);

        $aprobaciones = [];
        $selecciones = [];

        foreach ($filasAprobacion as $fila) {
            $idSeccion = (int) $fila->id_seccion;
            $idProyecto = (int) $fila->id_proyecto;

            if (! isset($aprobaciones[$idSeccion][$idProyecto])) {
                $aprobaciones[$idSeccion][$idProyecto] = $fila;
            }
        }

        foreach ($filasSeleccion as $fila) {
            $idSeccion = (int) $fila->id_seccion;

            if (! isset($selecciones[$idSeccion])) {
                $selecciones[$idSeccion] = $fila;
            }
        }

        $idsProyectos = [];

        foreach ($aprobaciones as $porProyecto) {
            foreach ($porProyecto as $idProyecto => $fila) {
                if ($fila->accion === 'registrar') {
                    $idsProyectos[(int) $idProyecto] = true;
                }
            }
        }

        foreach ($selecciones as $fila) {
            if ($fila->accion === 'registrar') {
                $idsProyectos[(int) $fila->id_proyecto] = true;
            }
        }

        $evidencias = $this->evidenciasActuales(array_keys($idsProyectos));

        // Carga por lote la información de proyecto de todas las selecciones,
        // una sola consulta en lugar de una por sección con selección.
        $seleccionInfoPorProyecto = [];
        foreach ($selecciones as $filaSeleccion) {
            if ($filaSeleccion->accion !== 'registrar') {
                continue;
            }
            $idProyecto = (int) $filaSeleccion->id_proyecto;
            if (! isset($seleccionInfoPorProyecto[$idProyecto])) {
                $seleccionInfoPorProyecto[$idProyecto] = null;
            }
        }
        if ($seleccionInfoPorProyecto !== []) {
            $filasProyecto = DB::table('investigacion_formativa.proyecto as p')
                ->join('investigacion_formativa.grupo as g', 'g.id_grupo', '=', 'p.id_grupo')
                ->whereIn('p.id_proyecto', array_keys($seleccionInfoPorProyecto))
                ->get(['p.id_proyecto', 'p.titulo', 'g.id_grupo', 'g.nombre']);
            foreach ($filasProyecto as $fila) {
                $seleccionInfoPorProyecto[(int) $fila->id_proyecto] = (object) [
                    'id_proyecto' => (int) $fila->id_proyecto,
                    'id_grupo' => (int) $fila->id_grupo,
                    'nombre' => (string) $fila->nombre,
                    'titulo' => (string) $fila->titulo,
                ];
            }
        }

        $estados = [];

        foreach ($idsSecciones as $idSeccion) {
            $idSeccion = (int) $idSeccion;
            $vigentes = [];
            $desactualizadas = [];

            foreach ($aprobaciones[$idSeccion] ?? [] as $idProyecto => $fila) {
                $idProyecto = (int) $idProyecto;

                if ($fila->accion !== 'registrar') {
                    $vigentes[$idProyecto] = false;

                    continue;
                }

                $vigente = $this->vigente($fila->evidencia, $evidencias[$idProyecto] ?? null);
                $vigentes[$idProyecto] = $vigente;

                if (! $vigente) {
                    $desactualizadas[$idProyecto] = true;
                }
            }

            $destacado = null;
            $seleccion = null;
            $seleccionInfo = null;
            $filaSeleccion = $selecciones[$idSeccion] ?? null;

            if ($filaSeleccion !== null && $filaSeleccion->accion === 'registrar') {
                $idProyecto = (int) $filaSeleccion->id_proyecto;
                $vigente = ($vigentes[$idProyecto] ?? false)
                    && $this->vigente($filaSeleccion->evidencia, $evidencias[$idProyecto] ?? null);

                if ($vigente) {
                    $destacado = $idProyecto;
                }

                $seleccion = (object) [
                    'id_proyecto' => $idProyecto,
                    'desactualizada' => ! $vigente,
                    'motivo' => $filaSeleccion->motivo === null ? null : (string) $filaSeleccion->motivo,
                    'decidido_en' => (string) $filaSeleccion->decidido_en,
                ];
                $baseInfo = $seleccionInfoPorProyecto[$idProyecto] ?? null;
                $seleccionInfo = $baseInfo === null
                    ? null
                    : (object) array_merge((array) $baseInfo, [
                        'desactualizada' => ! $vigente,
                        'decidido_en' => (string) $filaSeleccion->decidido_en,
                    ]);
            }

            $estados[$idSeccion] = (object) [
                'aprobaciones' => $vigentes,
                'aprobaciones_desactualizadas' => $desactualizadas,
                'aprobados' => count(array_filter($vigentes)),
                'destacado' => $destacado,
                'seleccion' => $seleccion,
                'seleccion_info' => $seleccionInfo,
            ];
        }

        return $estados;
    }

    /**
     * Instantánea de las versiones de entrega y evaluación de las tres unidades.
     *
     * @return array{unidades: array<string, array{id_entrega: int, version_entrega: int, id_evaluacion: int, version_evaluacion: int}>}|null
     */
    public function evidenciaActual(int $idProyecto): ?array
    {
        return $this->evidenciasActuales([$idProyecto])[$idProyecto] ?? null;
    }

    /**
     * @param  list<int>  $idsProyectos
     * @return array<int, array{unidades: array<string, array{id_entrega: int, version_entrega: int, id_evaluacion: int, version_evaluacion: int}>}|null>
     */
    private function evidenciasActuales(array $idsProyectos): array
    {
        $idsProyectos = array_values(array_unique(array_map('intval', $idsProyectos)));

        if ($idsProyectos === []) {
            return [];
        }

        $proyectos = DB::table('investigacion_formativa.proyecto as p')
            ->join('investigacion_formativa.grupo as g', 'g.id_grupo', '=', 'p.id_grupo')
            ->whereIn('p.id_proyecto', $idsProyectos)
            ->get(['p.id_proyecto', 'g.id_seccion']);

        $seccionPorProyecto = [];
        $idsSecciones = [];

        foreach ($proyectos as $proyecto) {
            $seccionPorProyecto[(int) $proyecto->id_proyecto] = (int) $proyecto->id_seccion;
            $idsSecciones[(int) $proyecto->id_seccion] = true;
        }

        if ($seccionPorProyecto === []) {
            return array_fill_keys($idsProyectos, null);
        }

        $entregables = DB::table('investigacion_formativa.entregable')
            ->whereIn('id_seccion', array_keys($idsSecciones))
            ->whereIn('orden', [1, 2, 3])
            ->get(['id_entregable', 'id_seccion', 'orden']);

        $entregablePorOrden = [];

        foreach ($entregables as $entregable) {
            $entregablePorOrden[(int) $entregable->id_seccion][(int) $entregable->orden] = (int) $entregable->id_entregable;
        }

        $entregas = DB::table('investigacion_formativa.entrega')
            ->whereIn('id_proyecto', $idsProyectos)
            ->whereIn('id_entregable', $entregables->pluck('id_entregable')->all())
            ->get(['id_entrega', 'id_proyecto', 'id_entregable', 'version']);

        $ultima = [];

        foreach ($entregas as $entrega) {
            $clave = (int) $entrega->id_proyecto.'-'.(int) $entrega->id_entregable;

            if (! isset($ultima[$clave]) || (int) $entrega->version > (int) $ultima[$clave]->version) {
                $ultima[$clave] = $entrega;
            }
        }

        $evaluaciones = $ultima === []
            ? collect()
            : DB::table('investigacion_formativa.evaluacion')
                ->whereIn('id_entrega', array_map(static fn (object $entrega): int => (int) $entrega->id_entrega, array_values($ultima)))
                ->get(['id_entrega', 'id_evaluacion', 'version'])
                ->keyBy('id_entrega');

        $resultado = [];

        foreach ($idsProyectos as $idProyecto) {
            $idSeccion = $seccionPorProyecto[$idProyecto] ?? null;

            if ($idSeccion === null) {
                $resultado[$idProyecto] = null;

                continue;
            }

            $unidades = [];
            $completo = true;

            foreach ([1, 2, 3] as $orden) {
                $idEntregable = $entregablePorOrden[$idSeccion][$orden] ?? null;

                if ($idEntregable === null) {
                    $completo = false;
                    break;
                }

                $entrega = $ultima[$idProyecto.'-'.$idEntregable] ?? null;

                if ($entrega === null) {
                    $completo = false;
                    break;
                }

                $evaluacion = $evaluaciones->get((int) $entrega->id_entrega);

                if ($evaluacion === null) {
                    $completo = false;
                    break;
                }

                $unidades[(string) $orden] = [
                    'id_entrega' => (int) $entrega->id_entrega,
                    'version_entrega' => (int) $entrega->version,
                    'id_evaluacion' => (int) $evaluacion->id_evaluacion,
                    'version_evaluacion' => (int) $evaluacion->version,
                ];
            }

            $resultado[$idProyecto] = $completo ? ['unidades' => $unidades] : null;
        }

        return $resultado;
    }

    /**
     * @return list<object{accion: string, version: int, actor: string, decidido_en: string, motivo: string|null, desactualizada: bool}>
     */
    public function historialAprobacion(int $idProyecto): array
    {
        $evidencia = $this->evidenciaActual($idProyecto);

        return DB::table('investigacion_formativa.decision_trabajo as d')
            ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->where('d.tipo', 'aprobacion')
            ->where('d.id_proyecto', $idProyecto)
            ->orderByDesc('d.version')
            ->get(['d.accion', 'd.version', 'd.decidido_en', 'd.motivo', 'd.evidencia', 'u.name as actor'])
            ->map(fn (object $fila): object => (object) [
                'accion' => (string) $fila->accion,
                'version' => (int) $fila->version,
                'actor' => $fila->actor === null ? 'Docente' : (string) $fila->actor,
                'decidido_en' => (string) $fila->decidido_en,
                'motivo' => $fila->motivo === null ? null : (string) $fila->motivo,
                'desactualizada' => $fila->accion === 'registrar'
                    && ! $this->vigente($fila->evidencia, $evidencia),
            ])
            ->all();
    }

    /**
     * @return list<object{accion: string, version: int, actor: string, decidido_en: string, motivo: string|null, id_proyecto: int, grupo: string, titulo: string, desactualizada: bool}>
     */
    public function historialSeleccion(int $idSeccion): array
    {
        $filas = DB::table('investigacion_formativa.decision_trabajo as d')
            ->join('investigacion_formativa.proyecto as p', 'p.id_proyecto', '=', 'd.id_proyecto')
            ->join('investigacion_formativa.grupo as g', 'g.id_grupo', '=', 'p.id_grupo')
            ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->where('d.tipo', 'seleccion')
            ->where('d.id_seccion', $idSeccion)
            ->orderByDesc('d.version')
            ->get([
                'd.accion',
                'd.version',
                'd.decidido_en',
                'd.motivo',
                'd.evidencia',
                'd.id_proyecto',
                'p.titulo',
                'g.nombre as grupo',
                'u.name as actor',
            ]);

        $evidencias = $this->evidenciasActuales(
            $filas->pluck('id_proyecto')->map(static fn (mixed $id): int => (int) $id)->unique()->values()->all(),
        );

        return $filas->map(fn (object $fila): object => (object) [
            'accion' => (string) $fila->accion,
            'version' => (int) $fila->version,
            'actor' => $fila->actor === null ? 'Docente' : (string) $fila->actor,
            'decidido_en' => (string) $fila->decidido_en,
            'motivo' => $fila->motivo === null ? null : (string) $fila->motivo,
            'id_proyecto' => (int) $fila->id_proyecto,
            'grupo' => (string) $fila->grupo,
            'titulo' => (string) $fila->titulo,
            'desactualizada' => $fila->accion === 'registrar'
                && ! $this->vigente($fila->evidencia, $evidencias[(int) $fila->id_proyecto] ?? null),
        ])->all();
    }

    private function aprobacionVigente(int $idSeccion, int $idProyecto): bool
    {
        $fila = DB::table('investigacion_formativa.decision_trabajo')
            ->where('id_seccion', $idSeccion)
            ->where('tipo', 'aprobacion')
            ->where('id_proyecto', $idProyecto)
            ->orderByDesc('version')
            ->first(['accion', 'evidencia']);

        if ($fila === null || $fila->accion !== 'registrar') {
            return false;
        }

        return $this->vigente($fila->evidencia, $this->evidenciaActual($idProyecto));
    }

    private function ultimaSeleccion(int $idSeccion): ?object
    {
        return DB::table('investigacion_formativa.decision_trabajo')
            ->where('id_seccion', $idSeccion)
            ->where('tipo', 'seleccion')
            ->orderByDesc('version')
            ->first(['accion', 'id_proyecto', 'motivo']);
    }

    private function exigirVersionAprobacion(int $idSeccion, int $idProyecto, int $versionEsperada): void
    {
        if ($versionEsperada !== $this->versionAprobacion($idSeccion, $idProyecto)) {
            throw new VersionDesactualizada(
                'La aprobación cambió en otra sesión. Recargue la página y vuelva a intentarlo.',
            );
        }
    }

    private function exigirVersionSeleccion(int $idSeccion, int $versionEsperada): void
    {
        if ($versionEsperada !== $this->versionSeleccion($idSeccion)) {
            throw new VersionDesactualizada(
                'La selección del trabajo destacado cambió en otra sesión. Recargue la página y vuelva a intentarlo.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $evidencia
     */
    private function registrar(
        ActorInvestigacion $actor,
        int $idSeccion,
        string $tipo,
        string $accion,
        int $idProyecto,
        int $version,
        array $evidencia,
        ?string $motivo,
    ): void {
        if ($actor->idDocente === null) {
            throw new AccesoDenegado('La decisión requiere una identidad docente.');
        }

        DB::table('investigacion_formativa.decision_trabajo')->insert([
            'id_seccion' => $idSeccion,
            'tipo' => $tipo,
            'accion' => $accion,
            'id_proyecto' => $idProyecto,
            'version' => $version,
            'user_id' => $actor->user->id,
            'id_docente' => $actor->idDocente,
            'decidido_en' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:sP'),
            'evidencia' => json_encode($evidencia, JSON_THROW_ON_ERROR),
            'motivo' => $motivo,
        ]);
    }

    private function bloquearSeccionOperable(int $idSeccion): void
    {
        $config = $this->configuracion->bloquearParaEscritura($idSeccion);

        if (! (bool) $config->habilitada) {
            throw new EstadoInvalido('La sección está deshabilitada. No se aceptan decisiones.');
        }

        if (! $this->configuracion->estaConfigurada($idSeccion)) {
            throw new EstadoInvalido('La configuración de la sección está incompleta.');
        }

        $seccion = $this->datos->seccionConEscuela($idSeccion);

        if ($seccion === null || $seccion->estado !== 'Activa') {
            throw new EstadoInvalido('La sección no está activa. Las decisiones quedan en solo lectura.');
        }
    }

    private function exigirDocente(ActorInvestigacion $actor, int $idSeccion): void
    {
        if (! $actor->tieneRol('profesor')
            || ! $actor->user->can('investigacion-formativa.groups.review')
            || $actor->idDocente === null) {
            throw new AccesoDenegado('No tiene permiso para decidir sobre el trabajo final.');
        }

        if (! $this->acceso->puedeVerSeccion($actor->user, $idSeccion, 'profesor')) {
            throw new AccesoDenegado('No es el docente responsable de la sección.');
        }
    }

    private function exigirDocenteActual(ActorInvestigacion $actor, int $idSeccion): void
    {
        $actual = $this->acceso->resolver($actor->user);

        if (! $actual->tieneRol('profesor')
            || ! $actual->user->can('investigacion-formativa.groups.review')
            || $actual->idDocente === null
            || ! $this->acceso->puedeVerSeccion($actual->user, $idSeccion, 'profesor')) {
            throw new AccesoDenegado('No es el docente responsable de la sección.');
        }
    }

    private function contextoProyecto(int $idProyecto): object
    {
        $fila = DB::table('investigacion_formativa.proyecto as p')
            ->join('investigacion_formativa.grupo as g', 'g.id_grupo', '=', 'p.id_grupo')
            ->where('p.id_proyecto', $idProyecto)
            ->first(['p.id_proyecto', 'p.id_grupo', 'g.id_seccion']);

        if ($fila === null) {
            throw new AccesoDenegado('El proyecto solicitado no existe.');
        }

        return (object) [
            'id_proyecto' => (int) $fila->id_proyecto,
            'id_grupo' => (int) $fila->id_grupo,
            'id_seccion' => (int) $fila->id_seccion,
        ];
    }

    /**
     * @param  array<string, mixed>|string|null  $almacenada
     * @param  array<string, mixed>|null  $actual
     */
    private function vigente(mixed $almacenada, ?array $actual): bool
    {
        if ($actual === null) {
            return false;
        }

        return $this->huella($almacenada) === $this->huella($actual);
    }

    /**
     * @param  array<string, mixed>|string|null  $evidencia
     */
    private function huella(mixed $evidencia): string
    {
        $snapshot = $this->snapshot($evidencia);
        $unidades = $snapshot['unidades'] ?? [];
        ksort($unidades);

        $canonico = [];

        foreach ($unidades as $orden => $unidad) {
            if (! is_array($unidad)) {
                continue;
            }

            $canonico[(string) $orden] = [
                'id_entrega' => (int) ($unidad['id_entrega'] ?? 0),
                'version_entrega' => (int) ($unidad['version_entrega'] ?? 0),
                'id_evaluacion' => (int) ($unidad['id_evaluacion'] ?? 0),
                'version_evaluacion' => (int) ($unidad['version_evaluacion'] ?? 0),
            ];
        }

        return json_encode(['unidades' => $canonico], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(mixed $evidencia): array
    {
        if (is_array($evidencia)) {
            return $evidencia;
        }

        if (! is_string($evidencia) || trim($evidencia) === '') {
            return [];
        }

        $decodificada = json_decode($evidencia, true);

        return is_array($decodificada) ? $decodificada : [];
    }
}
