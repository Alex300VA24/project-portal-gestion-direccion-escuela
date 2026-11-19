<?php

namespace App\Modules\InvestigacionFormativa\Services;

use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Support\ActorInvestigacion;
use App\Modules\InvestigacionFormativa\Support\PuntajeRubrica;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginador;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ReporteService
{
    public function __construct(
        private readonly AccesoInvestigacionService $acceso,
        private readonly DatosAcademicosService $datos,
        private readonly CalificacionService $calificacion,
        private readonly DecisionTrabajoService $decisiones,
    ) {}

    /**
     * @return object{
     *     id_seccion: int,
     *     estado: string|null,
     *     respuestas_count: int,
     *     roster_activo: int,
     *     participacion_porcentaje: float|null,
     *     preguntas: list<object{id_pregunta: int, texto: string, orden: int, valor_promedio: string|null, distribucion: list<object{valor: int, conteo: int}>}>,
     *     comentarios: LengthAwarePaginator<int, object{comentario: string, respondida_en: string}>,
     *     grupos: list<object{id_grupo: int, nombre: string, entregas_evaluadas: int, nota_final: string|null, aprobado: bool, destacado: bool}>,
     *     mejor_nota: string|null,
     *     peor_nota: string|null,
     *     grupos_mejor: list<object{id_grupo: int, nombre: string, nota_final: string}>,
     *     grupos_peor: list<object{id_grupo: int, nombre: string, nota_final: string}>,
     *     promedio_curso: string|null,
     *     promedio_denominador: int,
     *     aprobados_count: int,
     *     destacados_count: int,
     *     seleccion_destacada: object{id_proyecto: int, id_grupo: int, nombre: string, titulo: string, desactualizada: bool, decidido_en: string|null}|null,
     *     entregas_evaluadas: int,
     * }
     */
    public function porSeccion(
        ActorInvestigacion $actor,
        int $idSeccion,
        int $paginaComentarios = 1,
        int $porPaginaComentarios = 10,
        ?int $idLinea = null,
        bool $incluirComentarios = true,
    ): object {
        $this->exigirSeccionVisible($actor, $idSeccion);

        if ($idLinea !== null && ! $this->datos->lineaPerteneceASeccion($idLinea, $idSeccion)) {
            throw new AccesoDenegado('La línea solicitada no pertenece a la escuela de la sección.');
        }

        $encuesta = DB::table('investigacion_formativa.encuesta')
            ->where('id_seccion', $idSeccion)
            ->first();

        $respuestasCount = 0;
        $preguntas = [];
        $comentarios = $this->paginadosComentarios(null, $paginaComentarios, $porPaginaComentarios);

        if ($encuesta !== null) {
            $idEncuesta = (int) $encuesta->id_encuesta;

            $respuestasCount = (int) DB::table('investigacion_formativa.encuesta_respuesta')
                ->where('id_encuesta', $idEncuesta)
                ->count();

            $items = DB::table('investigacion_formativa.encuesta_respuesta_item')
                ->where('id_encuesta', $idEncuesta)
                ->get(['id_pregunta', 'valor'])
                ->groupBy('id_pregunta');

            $preguntas = DB::table('investigacion_formativa.encuesta_pregunta')
                ->where('id_encuesta', $idEncuesta)
                ->orderBy('orden')
                ->get(['id_pregunta', 'texto', 'orden'])
                ->map(function (object $pregunta) use ($items): object {
                    $valores = $items->get((int) $pregunta->id_pregunta, collect());

                    return (object) [
                        'id_pregunta' => (int) $pregunta->id_pregunta,
                        'texto' => (string) $pregunta->texto,
                        'orden' => (int) $pregunta->orden,
                        'valor_promedio' => $this->promedioEncuesta($valores),
                        'distribucion' => $this->distribucion($valores),
                    ];
                })
                ->all();

            if ($incluirComentarios) {
                $comentarios = $this->paginadosComentarios($idEncuesta, $paginaComentarios, $porPaginaComentarios);
            }
        }

        $rosterActivo = (int) DB::table('investigacion_formativa.participante_seccion')
            ->where('id_seccion', $idSeccion)
            ->where('estado', 'activo')
            ->count();

        $participacion = $rosterActivo > 0
            ? round($respuestasCount / $rosterActivo * 100, 2)
            : null;

        $grupos = $this->notasPorSecciones([$idSeccion], $idLinea);
        $decisiones = $this->decisiones->estadoDeSeccion($idSeccion);

        foreach ($grupos as $grupo) {
            $estadoGrupo = $decisiones->por_grupo[(int) $grupo->id_grupo] ?? null;
            $grupo->aprobado = $estadoGrupo?->aprobado ?? false;
            $grupo->destacado = $estadoGrupo?->destacado ?? false;
        }

        $seleccion = $decisiones->seleccion;

        if ($idLinea !== null && $seleccion !== null) {
            $idsProyectosVisibles = array_map(
                static fn (object $grupo): int => (int) $grupo->id_proyecto,
                $grupos,
            );

            if (! in_array((int) $seleccion->id_proyecto, $idsProyectosVisibles, true)) {
                $seleccion = null;
            }
        }

        $notas = array_values(array_filter(
            array_map(static fn (object $grupo): ?int => $grupo->nota_final_centesimas, $grupos),
            static fn (?int $nota): bool => $nota !== null,
        ));

        $mejor = $notas === [] ? null : $this->calificacion->formatear(max($notas));
        $peor = $notas === [] ? null : $this->calificacion->formatear(min($notas));

        $promedio = null;
        if ($notas !== []) {
            $suma = array_sum($notas);
            $promedio = $this->calificacion->formatear($this->promedioEntero($suma, count($notas)));
        }

        $aprobadosCount = $idLinea === null
            ? $decisiones->aprobados
            : count(array_filter($grupos, static fn (object $grupo): bool => $grupo->aprobado));
        $destacadosCount = $idLinea === null
            ? $decisiones->destacados
            : count(array_filter($grupos, static fn (object $grupo): bool => $grupo->destacado));

        return (object) [
            'id_seccion' => $idSeccion,
            'estado' => $encuesta === null ? null : (string) $encuesta->estado,
            'respuestas_count' => $respuestasCount,
            'roster_activo' => $rosterActivo,
            'participacion_porcentaje' => $participacion,
            'preguntas' => $preguntas,
            'comentarios' => $comentarios,
            'grupos' => $grupos,
            'mejor_nota' => $mejor,
            'peor_nota' => $peor,
            'grupos_mejor' => $mejor === null
                ? []
                : array_values(array_filter($grupos, static fn (object $grupo): bool => $grupo->nota_final === $mejor)),
            'grupos_peor' => $peor === null
                ? []
                : array_values(array_filter($grupos, static fn (object $grupo): bool => $grupo->nota_final === $peor)),
            'promedio_curso' => $promedio,
            'promedio_denominador' => count($notas),
            'aprobados_count' => $aprobadosCount,
            'destacados_count' => $destacadosCount,
            'seleccion_destacada' => $seleccion,
            'entregas_evaluadas' => array_reduce(
                $grupos,
                static fn (int $acumulado, object $grupo): int => $acumulado + (int) $grupo->entregas_evaluadas,
                0,
            ),
        ];
    }

    /**
     * @return object{
     *     escuelas: list<object{id_escuela: int, nombre: string}>,
     *     periodos: list<string>,
     *     cursos: list<object{id_curso: int, codigo: string, nombre: string}>,
     *     lineas: list<object{id_linea: int, codigo: string, nombre: string}>,
     *     filtros: object{escuela: int|null, periodo: string|null, curso: int|null, linea: int|null},
     *     secciones: list<object{
     *         id_seccion: int,
     *         id_curso: int,
     *         seccion: string,
     *         curso: string,
     *         periodo: string,
     *         escuela: string,
     *         docente: string|null,
     *         habilitada: bool,
     *         encuesta: string|null,
     *         respuestas_count: int,
     *         roster_activo: int,
     *         participacion_porcentaje: float|null,
     *         grupos: int,
     *         entregas_evaluadas: int,
     *         grupos_con_nota: int,
     *         mejor_nota: string|null,
     *         peor_nota: string|null,
     *         aprobados: int,
     *         destacados: int
     *     }>,
     *     lineas_resumen: list<object{
     *         id_linea: int,
     *         codigo: string,
     *         nombre: string,
     *         total: int,
     *         completos: int,
     *         pendientes: int,
     *         mayores_16: int,
     *         mayores_18: int
     *     }>,
     *     umbrales: object{base: int, mayores_16: int, mayores_18: int},
     *     encuesta: object{
     *         respuestas: int,
     *         roster_activo: int,
     *         participacion_porcentaje: float|null,
     *         secciones_con_encuesta: int,
     *         secciones_con_respuestas: int,
     *         escala_min: int,
     *         escala_max: int,
     *         preguntas: list<object{
     *             texto: string,
     *             orden: int,
     *             respuestas: int,
     *             valor_promedio: string|null,
     *             distribucion: list<object{valor: int, conteo: int}>
     *         }>
     *     },
     *     decisiones: object{aprobados: int, destacados: int},
     *     totales: object{
     *         secciones: int,
     *         habilitadas: int,
     *         grupos: int,
     *         evaluaciones: int,
     *         respuestas: int,
     *         aprobados: int,
     *         destacados: int,
     *         proyectos: int,
     *         proyectos_completos: int,
     *         proyectos_pendientes: int,
     *         mayores_16: int,
     *         mayores_18: int,
     *         roster_activo: int,
     *         participacion_porcentaje: float|null
     *     }
     * }
     */
    public function resumenDirector(
        ActorInvestigacion $actor,
        ?int $idEscuela = null,
        ?string $periodo = null,
        ?int $idCurso = null,
        ?int $idLinea = null,
    ): object {
        if (! $actor->tieneRol('director_escuela')
            || ! $actor->user->can('investigacion-formativa.reports.view')) {
            throw new AccesoDenegado('No tiene permiso para ver reportes de dirección.');
        }

        $escuelas = $this->datos->escuelasDeDirector($actor->user->id);
        $periodos = $this->datos->periodosDeDirector($actor->user->id);
        $periodo = $periodo === null || trim($periodo) === '' ? null : trim($periodo);

        if ($idEscuela !== null && ! in_array($idEscuela, array_column($escuelas, 'id_escuela'), true)) {
            throw new AccesoDenegado('La escuela solicitada no pertenece a su alcance de dirección.');
        }

        if ($periodo !== null && ! in_array($periodo, $periodos, true)) {
            throw new AccesoDenegado('El periodo solicitado no pertenece a su alcance de dirección.');
        }

        $seccionesFiltradas = $this->seccionesDirectorio($actor->user->id, $idEscuela, $periodo);
        $cursos = $this->cursosDeSecciones($seccionesFiltradas);
        $lineas = $this->lineasDeSecciones(array_column($seccionesFiltradas, 'id_seccion'));

        if ($idCurso !== null && ! in_array($idCurso, array_column($cursos, 'id_curso'), true)) {
            throw new AccesoDenegado('El curso solicitado no pertenece a su alcance de dirección.');
        }

        if ($idLinea !== null && ! in_array($idLinea, array_column($lineas, 'id_linea'), true)) {
            throw new AccesoDenegado('La línea solicitada no pertenece a su alcance de dirección.');
        }

        $seccionesBase = $this->seccionesDirectorio(
            $actor->user->id,
            $idEscuela,
            $periodo,
            $idCurso,
            $idLinea,
        );

        $filas = [];
        $lineasResumen = [];
        $proyectosTotales = 0;
        $proyectosCompletos = 0;
        $mayores16 = 0;
        $mayores18 = 0;
        $totales = (object) [
            'secciones' => 0,
            'habilitadas' => 0,
            'grupos' => 0,
            'evaluaciones' => 0,
            'respuestas' => 0,
            'aprobados' => 0,
            'destacados' => 0,
            'proyectos' => 0,
            'proyectos_completos' => 0,
            'proyectos_pendientes' => 0,
            'mayores_16' => 0,
            'mayores_18' => 0,
            'roster_activo' => 0,
            'participacion_porcentaje' => null,
        ];

        $ids = array_column($seccionesBase, 'id_seccion');
        $encuestas = DB::table('investigacion_formativa.encuesta')
            ->whereIn('id_seccion', $ids)->get(['id_seccion', 'estado'])->keyBy('id_seccion');
        $respuestas = DB::table('investigacion_formativa.encuesta_respuesta as r')
            ->join('investigacion_formativa.encuesta as en', 'en.id_encuesta', '=', 'r.id_encuesta')
            ->whereIn('en.id_seccion', $ids)->groupBy('en.id_seccion')
            ->selectRaw('en.id_seccion, count(*) as cantidad')->pluck('cantidad', 'id_seccion');
        $roster = DB::table('investigacion_formativa.participante_seccion')
            ->whereIn('id_seccion', $ids)->where('estado', 'activo')->groupBy('id_seccion')
            ->selectRaw('id_seccion, count(*) as cantidad')->pluck('cantidad', 'id_seccion');
        $gruposPorSeccion = collect($this->notasPorSecciones($ids, $idLinea))->groupBy('id_seccion');
        $proyectosPorSeccion = [];

        foreach ($gruposPorSeccion as $idSeccion => $grupos) {
            $proyectosPorSeccion[(int) $idSeccion] = array_values(array_filter(array_map(
                static fn (object $grupo): ?int => $grupo->id_proyecto === null ? null : (int) $grupo->id_proyecto,
                $grupos->all(),
            ), static fn (?int $idProyecto): bool => $idProyecto !== null));
        }

        $resumenDecisiones = $this->decisiones->resumenesPorSecciones(
            $ids,
            $idLinea === null ? null : $proyectosPorSeccion,
        );

        foreach ($seccionesBase as $seccion) {
            $idSeccion = (int) $seccion->id_seccion;
            $estadoEncuesta = $encuestas->get($idSeccion)?->estado;
            $respuestasCount = (int) $respuestas->get($idSeccion, 0);
            $rosterActivo = (int) $roster->get($idSeccion, 0);
            $grupos = $gruposPorSeccion->get($idSeccion, collect())->all();
            $entregasEvaluadas = array_reduce(
                $grupos,
                static fn (int $acumulado, object $grupo): int => $acumulado + (int) $grupo->entregas_evaluadas,
                0,
            );
            $gruposConNota = count(array_filter(
                $grupos,
                static fn (object $grupo): bool => $grupo->nota_final !== null,
            ));
            $notas = array_values(array_filter(
                array_map(static fn (object $grupo): ?int => $grupo->nota_final_centesimas, $grupos),
                static fn (?int $nota): bool => $nota !== null,
            ));
            $decisionSeccion = $resumenDecisiones[$idSeccion] ?? null;
            $aprobados = $decisionSeccion?->aprobados ?? 0;
            $destacados = $decisionSeccion?->destacados ?? 0;

            foreach ($grupos as $grupo) {
                if ($grupo->id_proyecto === null) {
                    continue;
                }

                $proyectosTotales++;
                $idLineaProyecto = (int) $grupo->id_linea;

                if (! isset($lineasResumen[$idLineaProyecto])) {
                    $lineasResumen[$idLineaProyecto] = (object) [
                        'id_linea' => $idLineaProyecto,
                        'codigo' => (string) $grupo->linea_codigo,
                        'nombre' => (string) $grupo->linea_nombre,
                        'total' => 0,
                        'completos' => 0,
                        'pendientes' => 0,
                        'mayores_16' => 0,
                        'mayores_18' => 0,
                    ];
                }

                $lineasResumen[$idLineaProyecto]->total++;

                if ($grupo->nota_final_centesimas === null) {
                    $lineasResumen[$idLineaProyecto]->pendientes++;

                    continue;
                }

                $proyectosCompletos++;
                $lineasResumen[$idLineaProyecto]->completos++;

                if ($grupo->nota_final_centesimas > 1600) {
                    $mayores16++;
                    $lineasResumen[$idLineaProyecto]->mayores_16++;
                }

                if ($grupo->nota_final_centesimas > 1800) {
                    $mayores18++;
                    $lineasResumen[$idLineaProyecto]->mayores_18++;
                }
            }

            $filas[] = (object) [
                'id_seccion' => $idSeccion,
                'id_curso' => (int) $seccion->id_curso,
                'seccion' => (string) $seccion->seccion,
                'curso' => (string) $seccion->curso,
                'periodo' => (string) $seccion->periodo,
                'escuela' => (string) $seccion->escuela,
                'docente' => $seccion->docente_nombres === null
                    ? null
                    : trim((string) $seccion->docente_nombres.' '.(string) $seccion->docente_apellido),
                'habilitada' => (bool) $seccion->habilitada,
                'encuesta' => $estadoEncuesta === null ? null : (string) $estadoEncuesta,
                'respuestas_count' => $respuestasCount,
                'roster_activo' => $rosterActivo,
                'participacion_porcentaje' => $rosterActivo > 0
                    ? round($respuestasCount / $rosterActivo * 100, 2)
                    : null,
                'grupos' => count($grupos),
                'entregas_evaluadas' => $entregasEvaluadas,
                'grupos_con_nota' => $gruposConNota,
                'mejor_nota' => $notas === [] ? null : $this->calificacion->formatear(max($notas)),
                'peor_nota' => $notas === [] ? null : $this->calificacion->formatear(min($notas)),
                'aprobados' => $aprobados,
                'destacados' => $destacados,
            ];

            $totales->secciones++;
            $totales->habilitadas += (bool) $seccion->habilitada ? 1 : 0;
            $totales->grupos += count($grupos);
            $totales->evaluaciones += $entregasEvaluadas;
            $totales->respuestas += $respuestasCount;
            $totales->roster_activo += $rosterActivo;
            $totales->aprobados += $aprobados;
            $totales->destacados += $destacados;
        }

        usort($lineasResumen, static fn (object $a, object $b): int => strcmp($a->nombre, $b->nombre) ?: strcmp($a->codigo, $b->codigo));

        $totales->proyectos = $proyectosTotales;
        $totales->proyectos_completos = $proyectosCompletos;
        $totales->proyectos_pendientes = $proyectosTotales - $proyectosCompletos;
        $totales->mayores_16 = $mayores16;
        $totales->mayores_18 = $mayores18;
        $totales->participacion_porcentaje = $totales->roster_activo > 0
            ? round($totales->respuestas / $totales->roster_activo * 100, 2)
            : null;

        return (object) [
            'escuelas' => $escuelas,
            'periodos' => $periodos,
            'cursos' => $cursos,
            'lineas' => $lineas,
            'filtros' => (object) [
                'escuela' => $idEscuela,
                'periodo' => $periodo,
                'curso' => $idCurso,
                'linea' => $idLinea,
            ],
            'secciones' => $filas,
            'lineas_resumen' => array_values($lineasResumen),
            'umbrales' => (object) [
                'base' => $proyectosCompletos,
                'mayores_16' => $mayores16,
                'mayores_18' => $mayores18,
            ],
            'encuesta' => $this->resumenEncuesta($ids, $respuestas, $roster, $encuestas),
            'decisiones' => (object) [
                'aprobados' => $totales->aprobados,
                'destacados' => $totales->destacados,
            ],
            'totales' => $totales,
        ];
    }

    /**
     * @param  list<object{id_curso: int, curso_codigo: string, curso: string}>  $secciones
     * @return list<object{id_curso: int, codigo: string, nombre: string}>
     */
    private function cursosDeSecciones(array $secciones): array
    {
        $cursos = [];

        foreach ($secciones as $seccion) {
            $idCurso = (int) $seccion->id_curso;

            if (! isset($cursos[$idCurso])) {
                $cursos[$idCurso] = (object) [
                    'id_curso' => $idCurso,
                    'codigo' => (string) $seccion->curso_codigo,
                    'nombre' => (string) $seccion->curso,
                ];
            }
        }

        usort($cursos, static fn (object $a, object $b): int => strcmp($a->nombre, $b->nombre) ?: strcmp($a->codigo, $b->codigo));

        return array_values($cursos);
    }

    /**
     * @param  list<int>  $idsSecciones
     * @return list<object{id_linea: int, codigo: string, nombre: string}>
     */
    private function lineasDeSecciones(array $idsSecciones): array
    {
        if ($idsSecciones === []) {
            return [];
        }

        return DB::table('investigacion_formativa.grupo as g')
            ->join('investigacion_formativa.proyecto as p', 'p.id_grupo', '=', 'g.id_grupo')
            ->join('core.linea_investigacion as l', 'l.id_linea', '=', 'p.id_linea')
            ->whereIn('g.id_seccion', $idsSecciones)
            ->distinct()
            ->orderBy('l.nombre')
            ->orderBy('l.codigo')
            ->get(['l.id_linea', 'l.codigo', 'l.nombre'])
            ->map(static fn (object $fila): object => (object) [
                'id_linea' => (int) $fila->id_linea,
                'codigo' => (string) $fila->codigo,
                'nombre' => (string) $fila->nombre,
            ])
            ->all();
    }

    /**
     * @param  list<int>  $idsSecciones
     * @param  Collection<int, int|string>  $respuestas
     * @param  Collection<int, int|string>  $roster
     * @param  Collection<int, object{estado: string}>  $encuestas
     */
    private function resumenEncuesta(array $idsSecciones, $respuestas, $roster, $encuestas): object
    {
        $totalRespuestas = (int) $respuestas->sum();
        $totalRoster = (int) $roster->sum();

        return (object) [
            'respuestas' => $totalRespuestas,
            'roster_activo' => $totalRoster,
            'participacion_porcentaje' => $totalRoster > 0
                ? round($totalRespuestas / $totalRoster * 100, 2)
                : null,
            'secciones_con_encuesta' => $encuestas->count(),
            'secciones_con_respuestas' => $respuestas->filter(
                static fn (int|string $cantidad): bool => (int) $cantidad > 0,
            )->count(),
            'escala_min' => 1,
            'escala_max' => 5,
            'preguntas' => $this->preguntasEncuesta($idsSecciones),
        ];
    }

    /**
     * @param  list<int>  $idsSecciones
     * @return list<object{
     *     texto: string,
     *     orden: int,
     *     respuestas: int,
     *     valor_promedio: string|null,
     *     distribucion: list<object{valor: int, conteo: int}>
     * }>
     */
    private function preguntasEncuesta(array $idsSecciones): array
    {
        if ($idsSecciones === []) {
            return [];
        }

        $filas = DB::table('investigacion_formativa.encuesta_pregunta as ep')
            ->join('investigacion_formativa.encuesta as e', 'e.id_encuesta', '=', 'ep.id_encuesta')
            ->leftJoin('investigacion_formativa.encuesta_respuesta_item as i', function ($join): void {
                $join->on('i.id_encuesta', '=', 'ep.id_encuesta')
                    ->on('i.id_pregunta', '=', 'ep.id_pregunta');
            })
            ->whereIn('e.id_seccion', $idsSecciones)
            ->groupBy('ep.texto', 'ep.orden', 'i.valor')
            ->orderBy('ep.orden')
            ->orderBy('ep.texto')
            ->orderBy('i.valor')
            ->selectRaw('ep.texto as texto, ep.orden as orden, i.valor as valor, count(i.valor) as conteo')
            ->get();

        $preguntas = [];

        foreach ($filas as $fila) {
            $clave = $fila->texto.'|'.$fila->orden;

            if (! isset($preguntas[$clave])) {
                $preguntas[$clave] = (object) [
                    'texto' => (string) $fila->texto,
                    'orden' => (int) $fila->orden,
                    'respuestas' => 0,
                    'valor_promedio' => null,
                    'distribucion' => [
                        1 => 0,
                        2 => 0,
                        3 => 0,
                        4 => 0,
                        5 => 0,
                    ],
                ];
            }

            if ($fila->valor === null) {
                continue;
            }

            $valor = (int) $fila->valor;
            $conteo = (int) $fila->conteo;
            $preguntas[$clave]->distribucion[$valor] += $conteo;
            $preguntas[$clave]->respuestas += $conteo;
        }

        $resultado = [];

        foreach ($preguntas as $pregunta) {
            $suma = 0;

            foreach ($pregunta->distribucion as $valor => $conteo) {
                $suma += $valor * $conteo;
            }

            $pregunta->valor_promedio = $pregunta->respuestas > 0
                ? $this->calificacion->formatear($this->promedioEntero($suma * 100, $pregunta->respuestas))
                : null;
            $pregunta->distribucion = array_map(
                static fn (int $valor, int $conteo): object => (object) ['valor' => $valor, 'conteo' => $conteo],
                array_keys($pregunta->distribucion),
                array_values($pregunta->distribucion),
            );

            $resultado[] = $pregunta;
        }

        return $resultado;
    }

    /**
     * @return list<object{
     *     id_seccion: int,
     *     id_curso: int,
     *     curso_codigo: string,
     *     seccion: string,
     *     curso: string,
     *     periodo: string,
     *     escuela: string,
     *     docente_nombres: string|null,
     *     docente_apellido: string|null,
     *     habilitada: bool
     * }>
     */
    private function seccionesDirectorio(
        int $userId,
        ?int $idEscuela,
        ?string $periodo,
        ?int $idCurso = null,
        ?int $idLinea = null,
    ): array {
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
            ->when($idEscuela !== null, static fn ($query) => $query->where('d.id_escuela', $idEscuela))
            ->when($periodo !== null, static fn ($query) => $query->where('p.codigo', $periodo))
            ->when($idCurso !== null, static fn ($query) => $query->where('c.id_curso', $idCurso))
            ->when($idLinea !== null, static fn ($query) => $query->whereExists(
                static function ($subconsulta) use ($idLinea): void {
                    $subconsulta->selectRaw('1')
                        ->from('investigacion_formativa.grupo as g')
                        ->join('investigacion_formativa.proyecto as p', 'p.id_grupo', '=', 'g.id_grupo')
                        ->whereColumn('g.id_seccion', 's.id_seccion')
                        ->where('p.id_linea', $idLinea);
                },
            ))
            ->orderByDesc('p.fecha_inicio')
            ->orderBy('c.codigo')
            ->orderBy('s.codigo')
            ->get([
                's.id_seccion',
                'c.id_curso',
                'c.codigo as curso_codigo',
                's.codigo as seccion',
                'c.nombre as curso',
                'p.codigo as periodo',
                'e.nombre as escuela',
                'per.nombres as docente_nombres',
                'per.apellido_paterno as docente_apellido',
                'sc.habilitada',
            ])
            ->map(static fn (object $fila): object => (object) [
                'id_seccion' => (int) $fila->id_seccion,
                'id_curso' => (int) $fila->id_curso,
                'curso_codigo' => (string) $fila->curso_codigo,
                'seccion' => (string) $fila->seccion,
                'curso' => (string) $fila->curso,
                'periodo' => (string) $fila->periodo,
                'escuela' => (string) $fila->escuela,
                'docente_nombres' => $fila->docente_nombres === null ? null : (string) $fila->docente_nombres,
                'docente_apellido' => $fila->docente_apellido === null ? null : (string) $fila->docente_apellido,
                'habilitada' => (bool) $fila->habilitada,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, object>  $valores
     */
    private function promedioEncuesta($valores): ?string
    {
        $total = $valores->count();

        if ($total === 0) {
            return null;
        }

        return $this->calificacion->formatear(
            $this->promedioEntero($valores->sum(static fn (object $fila): int => (int) $fila->valor) * 100, $total),
        );
    }

    /**
     * Promedio con redondeo medio-arriba sin puntos flotantes.
     */
    private function promedioEntero(int $suma, int $cantidad): int
    {
        return intdiv(($suma * 2) + $cantidad, $cantidad * 2);
    }

    /**
     * @param  Collection<int, object>  $valores
     * @return list<object{valor: int, conteo: int}>
     */
    private function distribucion($valores): array
    {
        $distribucion = [];

        foreach ([1, 2, 3, 4, 5] as $valor) {
            $distribucion[] = (object) [
                'valor' => $valor,
                'conteo' => (int) $valores->filter(static fn (object $fila): bool => (int) $fila->valor === $valor)->count(),
            ];
        }

        return $distribucion;
    }

    /**
     * @return LengthAwarePaginator<int, object{comentario: string, respondida_en: string}>
     */
    private function paginadosComentarios(?int $idEncuesta, int $pagina, int $porPagina): LengthAwarePaginator
    {
        if ($idEncuesta === null) {
            return new Paginador([], 0, $porPagina, $pagina, [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'comentarios',
            ]);
        }

        $consulta = DB::table('investigacion_formativa.encuesta_respuesta')
            ->where('id_encuesta', $idEncuesta)
            ->whereNotNull('comentario')
            ->where('comentario', '<>', '')
            ->orderBy('respondida_en')
            ->orderBy('id_respuesta');

        $total = (int) $consulta->count();

        $filas = $consulta
            ->forPage($pagina, $porPagina)
            ->get(['comentario', 'respondida_en'])
            ->map(static fn (object $fila): object => (object) [
                'comentario' => (string) $fila->comentario,
                'respondida_en' => (string) $fila->respondida_en,
            ])
            ->all();

        return new Paginador($filas, $total, $porPagina, $pagina, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'comentarios',
        ]);
    }

    /**
     * @param  list<int>  $idsSecciones
     * @return list<object{
     *     id_grupo: int,
     *     id_seccion: int,
     *     id_proyecto: int|null,
     *     id_linea: int|null,
     *     linea_codigo: string|null,
     *     linea_nombre: string|null,
     *     nombre: string,
     *     entregas_evaluadas: int,
     *     nota_final: string|null,
     *     nota_final_centesimas: int|null,
     *     notas_unidades: array<int, string|null>
     * }>
     */
    private function notasPorSecciones(array $idsSecciones, ?int $idLinea = null): array
    {
        $entregables = DB::table('investigacion_formativa.entregable')
            ->whereIn('id_seccion', $idsSecciones)
            ->whereIn('orden', [1, 2, 3])
            ->get(['id_entregable', 'orden', 'id_rubrica'])
            ->keyBy('id_entregable');

        $maximos = $this->maximosPorRubrica($entregables->pluck('id_rubrica')->filter()->unique()->all());

        $grupos = DB::table('investigacion_formativa.grupo as g')
            ->leftJoin('investigacion_formativa.proyecto as p', 'p.id_grupo', '=', 'g.id_grupo')
            ->leftJoin('core.linea_investigacion as l', 'l.id_linea', '=', 'p.id_linea')
            ->whereIn('g.id_seccion', $idsSecciones)
            ->when($idLinea !== null, static fn ($query) => $query->where('p.id_linea', $idLinea))
            ->orderBy('g.id_grupo')
            ->get([
                'g.id_grupo',
                'g.id_seccion',
                'g.nombre',
                'p.id_proyecto',
                'p.id_linea',
                'l.codigo as linea_codigo',
                'l.nombre as linea_nombre',
            ]);

        if ($grupos->isEmpty() || $entregables->isEmpty()) {
            return $grupos->map(static fn (object $grupo): object => (object) [
                'id_grupo' => (int) $grupo->id_grupo,
                'id_seccion' => (int) $grupo->id_seccion,
                'id_proyecto' => $grupo->id_proyecto === null ? null : (int) $grupo->id_proyecto,
                'id_linea' => $grupo->id_linea === null ? null : (int) $grupo->id_linea,
                'linea_codigo' => $grupo->linea_codigo === null ? null : (string) $grupo->linea_codigo,
                'linea_nombre' => $grupo->linea_nombre === null ? null : (string) $grupo->linea_nombre,
                'nombre' => (string) $grupo->nombre,
                'entregas_evaluadas' => 0,
                'nota_final' => null,
                'nota_final_centesimas' => null,
                'notas_unidades' => [1 => null, 2 => null, 3 => null],
            ])->all();
        }

        $idsProyectos = $grupos->pluck('id_proyecto')
            ->filter(static fn (mixed $id): bool => $id !== null)
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $entregas = DB::table('investigacion_formativa.entrega')
            ->whereIn('id_proyecto', $idsProyectos)
            ->whereIn('id_entregable', $entregables->keys()->all())
            ->get(['id_entrega', 'id_proyecto', 'id_entregable', 'version']);

        $ultima = [];
        foreach ($entregas as $entrega) {
            $clave = (int) $entrega->id_proyecto.'-'.(int) $entrega->id_entregable;
            if (! isset($ultima[$clave]) || (int) $entrega->version > (int) $ultima[$clave]->version) {
                $ultima[$clave] = $entrega;
            }
        }

        $idsEntregas = array_map(static fn (object $entrega): int => (int) $entrega->id_entrega, array_values($ultima));

        $evaluaciones = $idsEntregas === []
            ? collect()
            : DB::table('investigacion_formativa.evaluacion')
                ->whereIn('id_entrega', $idsEntregas)
                ->get(['id_entrega', 'id_evaluacion'])
                ->keyBy('id_entrega');

        $puntos = $evaluaciones->isEmpty()
            ? collect()
            : DB::table('investigacion_formativa.evaluacion_criterio')
                ->whereIn('id_evaluacion', $evaluaciones->pluck('id_evaluacion')->all())
                ->get(['id_evaluacion', 'puntaje'])
                ->groupBy('id_evaluacion')
                ->map(static fn ($filas): int => (int) array_reduce(
                    $filas->all(),
                    static fn (int $suma, object $fila): int => $suma + (PuntajeRubrica::aCentavos($fila->puntaje) ?? 0),
                    0,
                ));

        $notasPorProyecto = [];

        foreach ($ultima as $entrega) {
            $idEntrega = (int) $entrega->id_entrega;
            $evaluacion = $evaluaciones->get($idEntrega);

            if ($evaluacion === null) {
                continue;
            }

            $entregable = $entregables->get((int) $entrega->id_entregable);
            $maximo = $entregable === null || $entregable->id_rubrica === null
                ? 0
                : ($maximos[(int) $entregable->id_rubrica] ?? 0);
            $seleccionado = (int) $puntos->get((int) $evaluacion->id_evaluacion, 0);

            if ($maximo <= 0) {
                continue;
            }

            try {
                $nota = $this->calificacion->normalizar($seleccionado, $maximo);
            } catch (\InvalidArgumentException) {
                continue;
            }

            $notasPorProyecto[(int) $entrega->id_proyecto][(int) $entregable->orden] = $nota;
        }

        $resultado = [];

        foreach ($grupos as $grupo) {
            $notas = $notasPorProyecto[(int) $grupo->id_proyecto] ?? [];
            ksort($notas);

            $final = count($notas) === 3 && array_keys($notas) === [1, 2, 3]
                ? $this->calificacion->promedioFinal(array_values($notas))
                : null;

            $resultado[] = (object) [
                'id_grupo' => (int) $grupo->id_grupo,
                'id_seccion' => (int) $grupo->id_seccion,
                'id_proyecto' => $grupo->id_proyecto === null ? null : (int) $grupo->id_proyecto,
                'id_linea' => $grupo->id_linea === null ? null : (int) $grupo->id_linea,
                'linea_codigo' => $grupo->linea_codigo === null ? null : (string) $grupo->linea_codigo,
                'linea_nombre' => $grupo->linea_nombre === null ? null : (string) $grupo->linea_nombre,
                'nombre' => (string) $grupo->nombre,
                'entregas_evaluadas' => count($notas),
                'nota_final_centesimas' => $final,
                'notas_unidades' => array_replace([1 => null, 2 => null, 3 => null], array_map(fn (int $nota): string => $this->calificacion->formatear($nota), $notas)),
                'nota_final' => $final === null ? null : $this->calificacion->formatear($final),
            ];
        }

        return $resultado;
    }

    /**
     * @param  list<mixed>  $idsRubricas
     * @return array<int, int>
     */
    private function maximosPorRubrica(array $idsRubricas): array
    {
        if ($idsRubricas === []) {
            return [];
        }

        $criterios = DB::table('investigacion_formativa.criterio')
            ->whereIn('id_rubrica', $idsRubricas)
            ->get(['id_rubrica', 'puntaje_maximo']);

        $maximos = [];

        foreach ($criterios as $criterio) {
            $idRubrica = (int) $criterio->id_rubrica;
            $maximos[$idRubrica] = ($maximos[$idRubrica] ?? 0) + (PuntajeRubrica::aCentavos($criterio->puntaje_maximo) ?? 0);
        }

        return $maximos;
    }

    private function exigirSeccionVisible(ActorInvestigacion $actor, int $idSeccion): void
    {
        if (! $actor->tieneRol('director_escuela')
            || ! $actor->user->can('investigacion-formativa.reports.view')) {
            throw new AccesoDenegado('No tiene permiso para ver reportes de sección.');
        }

        if (! $this->acceso->puedeVerResultados($actor->user, $idSeccion)) {
            throw new AccesoDenegado('La sección solicitada no pertenece a su alcance de dirección.');
        }
    }
}
