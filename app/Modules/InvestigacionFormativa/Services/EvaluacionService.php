<?php

namespace App\Modules\InvestigacionFormativa\Services;

use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Exceptions\EstadoInvalido;
use App\Modules\InvestigacionFormativa\Exceptions\VersionDesactualizada;
use App\Modules\InvestigacionFormativa\Models\Entrega;
use App\Modules\InvestigacionFormativa\Support\ActorInvestigacion;
use App\Modules\InvestigacionFormativa\Support\PuntajeRubrica;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class EvaluacionService
{
    public function __construct(
        private readonly AccesoInvestigacionService $acceso,
        private readonly ConfiguracionService $configuracion,
        private readonly CalificacionService $calificacion,
    ) {}

    /**
     * @param  list<array{id_criterio: int, nivel: int}>  $criterios
     * @param  list<string>  $observaciones
     * @return object{id_evaluacion: int, version: int, nota_centavos: int, puntos_centavos: int, maximo_centavos: int}
     */
    public function guardar(
        ActorInvestigacion $actor,
        Entrega $entrega,
        array $criterios,
        ?string $comentario,
        bool $sinObservaciones,
        array $observaciones,
        int $versionEsperada,
    ): object {
        if ($versionEsperada < 0) {
            throw new EstadoInvalido('La versión de evaluación no es válida.');
        }

        if (! $actor->tieneRol('profesor')
            || ! $actor->user->can('investigacion-formativa.evaluations.manage')
            || $actor->idDocente === null) {
            throw new AccesoDenegado('No tiene permiso para gestionar evaluaciones.');
        }

        $criteriosNormalizados = $this->normalizarCriterios($criterios);
        $comentario = $comentario === null ? null : trim($comentario);
        $observaciones = $this->normalizarObservaciones($observaciones);

        if ($comentario !== null && mb_strlen($comentario) > 4000) {
            throw new EstadoInvalido('El comentario no puede superar los 4000 caracteres.');
        }

        if ($sinObservaciones && $observaciones !== []) {
            throw new EstadoInvalido('No registre observaciones si la entrega no tiene observaciones.');
        }

        if (! $sinObservaciones && $observaciones === []) {
            throw new EstadoInvalido('Registre al menos una observación o indique que no hay observaciones.');
        }

        $contexto = DB::table('investigacion_formativa.entrega as e')
            ->join('investigacion_formativa.proyecto as p', 'p.id_proyecto', '=', 'e.id_proyecto')
            ->join('investigacion_formativa.grupo as g', 'g.id_grupo', '=', 'p.id_grupo')
            ->where('e.id_entrega', (int) $entrega->id_entrega)
            ->first([
                'e.id_entrega',
                'e.id_proyecto',
                'e.id_entregable',
                'p.id_grupo',
                'g.id_seccion',
            ]);

        if ($contexto === null) {
            throw new AccesoDenegado('La entrega solicitada no existe.');
        }

        if (! $this->acceso->puedeVerSeccion($actor->user, (int) $contexto->id_seccion, 'profesor')) {
            throw new AccesoDenegado('No es el docente responsable de la sección de la entrega.');
        }

        return DB::transaction(function () use (
            $actor,
            $contexto,
            $criteriosNormalizados,
            $comentario,
            $sinObservaciones,
            $observaciones,
            $versionEsperada,
        ): object {
            $config = $this->configuracion->bloquearParaEscritura((int) $contexto->id_seccion);

            $proyecto = DB::table('investigacion_formativa.proyecto')
                ->where('id_proyecto', (int) $contexto->id_proyecto)
                ->where('id_grupo', (int) $contexto->id_grupo)
                ->lockForUpdate()
                ->first();

            if ($proyecto === null) {
                throw new AccesoDenegado('El proyecto de la entrega ya no existe.');
            }

            $entregaBloqueada = DB::table('investigacion_formativa.entrega')
                ->where('id_entrega', (int) $contexto->id_entrega)
                ->where('id_proyecto', (int) $contexto->id_proyecto)
                ->lockForUpdate()
                ->first();

            if ($entregaBloqueada === null) {
                throw new AccesoDenegado('La entrega solicitada ya no existe.');
            }

            $evaluacion = DB::table('investigacion_formativa.evaluacion')
                ->where('id_entrega', (int) $entregaBloqueada->id_entrega)
                ->lockForUpdate()
                ->first();

            $this->exigirDocenteResponsableActual($actor, (int) $contexto->id_seccion);

            if (! (bool) $config->habilitada) {
                throw new EstadoInvalido('La sección está deshabilitada. No se aceptan evaluaciones.');
            }

            $entregable = DB::table('investigacion_formativa.entregable')
                ->where('id_entregable', (int) $entregaBloqueada->id_entregable)
                ->where('id_seccion', (int) $contexto->id_seccion)
                ->first();

            if ($entregable === null || $entregable->id_rubrica === null) {
                throw new EstadoInvalido('La entrega no tiene una rúbrica de sección válida.');
            }

            if ($config->id_rubrica === null
                || (int) $config->id_rubrica !== (int) $entregable->id_rubrica) {
                throw new EstadoInvalido('La rúbrica de la entrega no coincide con la configuración vigente.');
            }

            $rubricaId = (int) $entregable->id_rubrica;
            $criteriosRubrica = DB::table('investigacion_formativa.criterio')
                ->where('id_rubrica', $rubricaId)
                ->orderBy('orden')
                ->get();

            if ($criteriosRubrica->isEmpty()) {
                throw new EstadoInvalido('La rúbrica de la entrega no contiene criterios.');
            }

            $idsEsperados = $criteriosRubrica
                ->map(static fn (object $criterio): int => (int) $criterio->id_criterio)
                ->all();
            $idsRecibidos = array_keys($criteriosNormalizados);
            sort($idsEsperados);
            sort($idsRecibidos);

            if ($idsEsperados !== $idsRecibidos) {
                throw new EstadoInvalido(
                    'La evaluación debe incluir exactamente todos los criterios de la rúbrica, una sola vez.',
                );
            }

            $niveles = DB::table('investigacion_formativa.criterio_nivel')
                ->whereIn('id_criterio', $idsEsperados)
                ->get()
                ->groupBy('id_criterio');

            $puntosSeleccionados = 0;
            $puntosMaximos = 0;
            $puntosPorCriterio = [];

            foreach ($criteriosRubrica as $criterio) {
                $idCriterio = (int) $criterio->id_criterio;
                $nivelSolicitado = $criteriosNormalizados[$idCriterio];
                $nivel = $niveles->get($idCriterio, collect())->first(
                    static fn (object $fila): bool => (int) $fila->nivel === $nivelSolicitado,
                );

                if ($nivel === null) {
                    throw new EstadoInvalido(
                        "El nivel seleccionado para el criterio {$idCriterio} no existe en la rúbrica.",
                    );
                }

                $puntajeMaximo = PuntajeRubrica::aCentavos($criterio->puntaje_maximo);
                $puntajeSeleccionado = PuntajeRubrica::aCentavos($nivel->puntaje);

                if ($puntajeMaximo === null || $puntajeSeleccionado === null) {
                    throw new EstadoInvalido('La rúbrica contiene puntajes no válidos.');
                }

                if ($puntajeSeleccionado > $puntajeMaximo) {
                    throw new EstadoInvalido('Un nivel seleccionado supera el máximo de su criterio.');
                }

                $puntosSeleccionados += $puntajeSeleccionado;
                $puntosMaximos += $puntajeMaximo;
                $puntosPorCriterio[$idCriterio] = [
                    'nivel' => $nivelSolicitado,
                    'puntaje' => $puntajeSeleccionado,
                ];
            }

            if ($puntosMaximos <= 0) {
                throw new EstadoInvalido('La rúbrica no tiene un máximo de puntaje válido.');
            }

            $nota = $this->calificacion->normalizar($puntosSeleccionados, $puntosMaximos);
            $version = $this->guardarEvaluacion(
                $actor,
                (int) $entregaBloqueada->id_entrega,
                $evaluacion,
                $comentario,
                $sinObservaciones,
                $versionEsperada,
            );

            DB::table('investigacion_formativa.evaluacion_criterio')
                ->where('id_evaluacion', $version['id_evaluacion'])
                ->delete();

            foreach ($puntosPorCriterio as $idCriterio => $puntos) {
                DB::table('investigacion_formativa.evaluacion_criterio')->insert([
                    'id_evaluacion' => $version['id_evaluacion'],
                    'id_criterio' => $idCriterio,
                    'nivel' => $puntos['nivel'],
                    'puntaje' => PuntajeRubrica::formatear($puntos['puntaje']),
                ]);
            }

            if (! $sinObservaciones) {
                foreach ($observaciones as $observacion) {
                    DB::table('investigacion_formativa.observacion')->insert([
                        'id_entrega' => (int) $entregaBloqueada->id_entrega,
                        'id_evaluacion' => $version['id_evaluacion'],
                        'version_evaluacion' => $version['version'],
                        'descripcion' => $observacion,
                        'estado' => 'Pendiente',
                        'registrado_por' => $actor->idUsuarioSeguridad,
                        'registrado_en' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:sP'),
                    ]);
                }
            }

            return (object) [
                'id_evaluacion' => $version['id_evaluacion'],
                'version' => $version['version'],
                'nota_centavos' => $nota,
                'puntos_centavos' => $puntosSeleccionados,
                'maximo_centavos' => $puntosMaximos,
            ];
        });
    }

    /**
     * @return list<object{
     *     id_entrega: int,
     *     id_entregable: int,
     *     orden: int,
     *     unidad: string,
     *     version: int,
     *     entregado_en: string,
     *     es_ultima: bool,
     *     rubrica: object{id_rubrica: int, criterios: list<object{id_criterio: int, descripcion: string, orden: int, puntaje_maximo: string, niveles: list<object{nivel: int, descripcion: string, puntaje: string, seleccionado: bool}>}>},
     *     evaluacion: object{id_evaluacion: int, version: int, comentario: string|null, sin_observaciones: bool|null, evaluado_en: string, nota: string|null, puntos: string, maximo: string}|null,
     *     observaciones: list<object{descripcion: string, estado: string, autor: string, registrado_en: string, version_evaluacion: int|null}>,
     *     historial_observaciones: list<object{descripcion: string, estado: string, autor: string, registrado_en: string, version_evaluacion: int|null}>
     * }>
     */
    public function detalleEntregasDeGrupo(int $idGrupo): array
    {
        $idProyecto = DB::table('investigacion_formativa.proyecto')
            ->where('id_grupo', $idGrupo)
            ->value('id_proyecto');

        if ($idProyecto === null) {
            return [];
        }

        $entregas = DB::table('investigacion_formativa.entrega as e')
            ->join('investigacion_formativa.entregable as en', 'en.id_entregable', '=', 'e.id_entregable')
            ->where('e.id_proyecto', (int) $idProyecto)
            ->orderBy('en.orden')
            ->orderBy('e.version')
            ->get([
                'e.id_entrega',
                'e.id_entregable',
                'e.version',
                'e.entregado_en',
                'en.orden',
                'en.nombre as unidad',
                'en.id_rubrica',
            ]);

        if ($entregas->isEmpty()) {
            return [];
        }

        $idsEntregas = $entregas->pluck('id_entrega')->map(static fn (mixed $id): int => (int) $id)->all();
        $idsRubricas = $entregas->pluck('id_rubrica')->filter()->map(static fn (mixed $id): int => (int) $id)->unique()->values()->all();

        $criterios = $idsRubricas === []
            ? collect()
            : DB::table('investigacion_formativa.criterio')
                ->whereIn('id_rubrica', $idsRubricas)
                ->orderBy('id_rubrica')
                ->orderBy('orden')
                ->get();

        $niveles = $criterios->isEmpty()
            ? collect()
            : DB::table('investigacion_formativa.criterio_nivel')
                ->whereIn('id_criterio', $criterios->pluck('id_criterio')->all())
                ->orderBy('id_criterio')
                ->orderBy('nivel')
                ->get();

        $criteriosPorRubrica = $criterios->groupBy('id_rubrica');
        $nivelesPorCriterio = $niveles->groupBy('id_criterio');

        $evaluaciones = DB::table('investigacion_formativa.evaluacion')
            ->whereIn('id_entrega', $idsEntregas)
            ->get()
            ->keyBy('id_entrega');

        $idsEvaluaciones = $evaluaciones->pluck('id_evaluacion')->map(static fn (mixed $id): int => (int) $id)->all();
        $criteriosEvaluacion = $idsEvaluaciones === []
            ? collect()
            : DB::table('investigacion_formativa.evaluacion_criterio')
                ->whereIn('id_evaluacion', $idsEvaluaciones)
                ->get()
                ->groupBy('id_evaluacion')
                ->map(static fn ($criterios): mixed => $criterios->keyBy('id_criterio'));

        $observaciones = DB::table('investigacion_formativa.observacion as o')
            ->leftJoin('seguridad.usuario as u', 'u.id_usuario', '=', 'o.registrado_por')
            ->leftJoin('core.persona as p', 'p.id_persona', '=', 'u.id_persona')
            ->whereIn('o.id_entrega', $idsEntregas)
            ->orderBy('o.registrado_en')
            ->orderBy('o.id_observacion')
            ->get([
                'o.id_entrega',
                'o.id_evaluacion',
                'o.version_evaluacion',
                'o.descripcion',
                'o.estado',
                'o.registrado_en',
                'p.nombres',
                'p.apellido_paterno',
            ])
            ->map(static fn (object $fila): object => (object) [
                'id_entrega' => (int) $fila->id_entrega,
                'descripcion' => (string) $fila->descripcion,
                'estado' => (string) $fila->estado,
                'autor' => trim((string) $fila->nombres.' '.(string) $fila->apellido_paterno),
                'registrado_en' => (string) $fila->registrado_en,
                'version_evaluacion' => $fila->version_evaluacion === null
                    ? null
                    : (int) $fila->version_evaluacion,
            ]);

        $observacionesPorEntrega = $observaciones->groupBy('id_entrega');
        $ultimaVersionPorEntregable = $entregas
            ->groupBy('id_entregable')
            ->map(static fn ($versiones): int => (int) $versiones->max('version'));

        return $entregas->map(function (object $entrega) use (
            $criteriosPorRubrica,
            $nivelesPorCriterio,
            $evaluaciones,
            $criteriosEvaluacion,
            $observacionesPorEntrega,
            $ultimaVersionPorEntregable,
        ): object {
            $idEntrega = (int) $entrega->id_entrega;
            $idRubrica = (int) $entrega->id_rubrica;
            $evaluacion = $evaluaciones->get($idEntrega);
            $idEvaluacion = $evaluacion === null ? null : (int) $evaluacion->id_evaluacion;
            $seleccionados = $idEvaluacion === null
                ? collect()
                : $criteriosEvaluacion->get($idEvaluacion, collect());
            $criteriosRubrica = $criteriosPorRubrica->get($idRubrica, collect());

            $criterios = $criteriosRubrica->map(function (object $criterio) use ($nivelesPorCriterio, $seleccionados): object {
                $idCriterio = (int) $criterio->id_criterio;
                $nivelSeleccionado = $seleccionados->get($idCriterio);
                $maximo = PuntajeRubrica::aCentavos($criterio->puntaje_maximo) ?? 0;

                return (object) [
                    'id_criterio' => $idCriterio,
                    'descripcion' => (string) $criterio->descripcion,
                    'orden' => (int) $criterio->orden,
                    'puntaje_maximo' => PuntajeRubrica::formatear($maximo),
                    'niveles' => $nivelesPorCriterio
                        ->get($idCriterio, collect())
                        ->map(static fn (object $nivel): object => (object) [
                            'nivel' => (int) $nivel->nivel,
                            'descripcion' => (string) $nivel->descripcion,
                            'puntaje' => PuntajeRubrica::formatear(PuntajeRubrica::aCentavos($nivel->puntaje) ?? 0),
                            'seleccionado' => $nivelSeleccionado !== null
                                && (int) $nivelSeleccionado->nivel === (int) $nivel->nivel,
                        ])
                        ->values()
                        ->all(),
                ];
            })->values()->all();

            $puntosSeleccionados = 0;
            $puntosMaximos = 0;

            foreach ($criterios as $criterio) {
                $puntosMaximos += (int) (PuntajeRubrica::aCentavos($criterio->puntaje_maximo) ?? 0);
                $punto = $seleccionados->get($criterio->id_criterio);

                if ($punto !== null) {
                    $puntosSeleccionados += PuntajeRubrica::aCentavos($punto->puntaje) ?? 0;
                }
            }

            $nota = null;

            if ($evaluacion !== null && $puntosSeleccionados >= 0 && $puntosMaximos > 0) {
                try {
                    $nota = $this->calificacion->normalizar($puntosSeleccionados, $puntosMaximos);
                } catch (InvalidArgumentException) {
                    $nota = null;
                }
            }

            $historial = $observacionesPorEntrega->get($idEntrega, collect())->values()->all();
            $observacionesActuales = $idEvaluacion === null
                ? []
                : collect($historial)
                    ->filter(static fn (object $observacion): bool => $observacion->version_evaluacion === (int) $evaluacion->version)
                    ->values()
                    ->all();

            return (object) [
                'id_entrega' => $idEntrega,
                'id_entregable' => (int) $entrega->id_entregable,
                'orden' => (int) $entrega->orden,
                'unidad' => (string) $entrega->unidad,
                'version' => (int) $entrega->version,
                'entregado_en' => (string) $entrega->entregado_en,
                'es_ultima' => (int) $entrega->version === $ultimaVersionPorEntregable->get($entrega->id_entregable),
                'rubrica' => (object) [
                    'id_rubrica' => $idRubrica,
                    'criterios' => $criterios,
                ],
                'evaluacion' => $evaluacion === null ? null : (object) [
                    'id_evaluacion' => (int) $evaluacion->id_evaluacion,
                    'version' => (int) $evaluacion->version,
                    'comentario' => $evaluacion->comentario === null ? null : (string) $evaluacion->comentario,
                    'sin_observaciones' => $evaluacion->sin_observaciones === null
                        ? null
                        : (bool) $evaluacion->sin_observaciones,
                    'evaluado_en' => (string) $evaluacion->evaluado_en,
                    'nota' => $nota === null ? null : $this->calificacion->formatear($nota),
                    'puntos' => $this->calificacion->formatear($puntosSeleccionados),
                    'maximo' => $this->calificacion->formatear($puntosMaximos),
                ],
                'observaciones' => $observacionesActuales,
                'historial_observaciones' => $historial,
            ];
        })->values()->all();
    }

    /**
     * @param  list<array{id_criterio: mixed, nivel: mixed}>  $criterios
     * @return array<int, int>
     */
    private function normalizarCriterios(array $criterios): array
    {
        $normalizados = [];

        foreach ($criterios as $criterio) {
            if (! is_array($criterio)) {
                throw new EstadoInvalido('La selección de criterios no es válida.');
            }

            $idCriterio = filter_var($criterio['id_criterio'] ?? null, FILTER_VALIDATE_INT);
            $nivel = filter_var($criterio['nivel'] ?? null, FILTER_VALIDATE_INT);

            if ($idCriterio === false || $idCriterio <= 0 || $nivel === false || $nivel < 1 || $nivel > 4) {
                throw new EstadoInvalido('La selección de criterios o niveles no es válida.');
            }

            $idCriterio = (int) $idCriterio;

            if (array_key_exists($idCriterio, $normalizados)) {
                throw new EstadoInvalido('Cada criterio debe evaluarse una sola vez.');
            }

            $normalizados[$idCriterio] = (int) $nivel;
        }

        return $normalizados;
    }

    /**
     * @param  list<string>  $observaciones
     * @return list<string>
     */
    private function normalizarObservaciones(array $observaciones): array
    {
        $normalizadas = [];

        foreach ($observaciones as $observacion) {
            if (! is_string($observacion)) {
                throw new EstadoInvalido('Las observaciones deben ser textos.');
            }

            $observacion = trim($observacion);

            if ($observacion === '') {
                continue;
            }

            if (mb_strlen($observacion) > 2000) {
                throw new EstadoInvalido('Una observación no puede superar los 2000 caracteres.');
            }

            $normalizadas[] = $observacion;
        }

        if (count($normalizadas) > 20) {
            throw new EstadoInvalido('No se pueden registrar más de 20 observaciones.');
        }

        return $normalizadas;
    }

    /**
     * @return array{id_evaluacion: int, version: int}
     */
    private function guardarEvaluacion(
        ActorInvestigacion $actor,
        int $idEntrega,
        ?object $evaluacion,
        ?string $comentario,
        bool $sinObservaciones,
        int $versionEsperada,
    ): array {
        $evaluadoEn = CarbonImmutable::now('UTC')->format('Y-m-d H:i:sP');

        if ($evaluacion === null) {
            if ($versionEsperada !== 0) {
                throw new VersionDesactualizada(
                    'La evaluación cambió en otra sesión. Recargue la página y vuelva a intentarlo.',
                );
            }

            $idEvaluacion = DB::table('investigacion_formativa.evaluacion')->insertGetId([
                'id_entrega' => $idEntrega,
                'id_docente_evaluador' => $actor->idDocente,
                'comentario' => $comentario,
                'version' => 1,
                'sin_observaciones' => $sinObservaciones,
                'evaluado_en' => $evaluadoEn,
            ], 'id_evaluacion');

            return [
                'id_evaluacion' => (int) $idEvaluacion,
                'version' => 1,
            ];
        }

        if ((int) $evaluacion->version !== $versionEsperada) {
            throw new VersionDesactualizada(
                'La evaluación cambió en otra sesión. Recargue la página y vuelva a intentarlo.',
            );
        }

        $version = $versionEsperada + 1;

        DB::table('investigacion_formativa.evaluacion')
            ->where('id_evaluacion', (int) $evaluacion->id_evaluacion)
            ->update([
                'id_docente_evaluador' => $actor->idDocente,
                'comentario' => $comentario,
                'version' => $version,
                'sin_observaciones' => $sinObservaciones,
                'evaluado_en' => $evaluadoEn,
            ]);

        return [
            'id_evaluacion' => (int) $evaluacion->id_evaluacion,
            'version' => $version,
        ];
    }

    private function exigirDocenteResponsableActual(ActorInvestigacion $actor, int $idSeccion): void
    {
        if (! $this->acceso->puedeVerSeccion($actor->user, $idSeccion, 'profesor')) {
            throw new AccesoDenegado('No es el docente responsable de la sección.');
        }
    }
}
