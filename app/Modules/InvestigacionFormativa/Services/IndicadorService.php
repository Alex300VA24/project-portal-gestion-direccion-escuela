<?php

namespace App\Modules\InvestigacionFormativa\Services;

use Illuminate\Support\Facades\DB;

/**
 * Calcula los indicadores 3.1 y 3.2 con reglas explícitas y configurables.
 *
 * Indicador 3.1: porcentaje de experiencias curriculares que aplican
 * Investigación Formativa sobre las experiencias implementadas del alcance. La
 * unidad de conteo es un curso del plan implementado en un periodo académico.
 * Dos secciones del mismo curso en el mismo periodo son una sola experiencia.
 * El numerador usa los registros de `indicador_experiencia`; no se deduce de
 * la habilitación ni de la configuración del módulo.
 *
 * Indicador 3.2: porcentaje de estudiantes satisfechos con las estrategias de
 * investigación formativa. Usa la pregunta de satisfacción configurada y sus
 * categorías excluyentes. El denominador son estudiantes distintos, no ítems.
 */
final class IndicadorService
{
    private const PARAMETRO_PREGUNTA = 'pregunta_satisfaccion';

    private const PARAMETRO_CATEGORIAS = 'categorias_satisfaccion';

    private const UNIDAD_EXPERIENCIA = 'Un curso del plan implementado en un periodo académico. '
        .'Varias secciones del mismo curso en el mismo periodo cuentan como una sola experiencia.';

    private const REGLA_EXPERIENCIA = 'Numerador: experiencias con un registro que declara que aplican '
        .'Investigación Formativa. Denominador: todas las experiencias implementadas del alcance, '
        .'incluidas las que no aplican el módulo. Sin denominador no hay valor.';

    /**
     * Indicadores del alcance de las escuelas indicadas.
     *
     * @param  list<int>  $idsEscuela
     * @param  list<int>  $idsSeccionesEncuesta  Secciones cuyas encuestas alimentan el indicador 3.2.
     * @return object{
     *     experiencia_curricular: object,
     *     satisfaccion: object
     * }
     */
    public function indicadoresDeAlcance(
        array $idsEscuela,
        ?string $periodo = null,
        ?int $idCurso = null,
        array $idsSeccionesEncuesta = [],
    ): object {
        $idsEscuela = array_values(array_unique(array_map('intval', $idsEscuela)));
        $experiencias = $this->experienciasCurriculares($idsEscuela, $periodo, $idCurso);
        $idsSeccionesAlcance = [];

        foreach ($experiencias as $experiencia) {
            foreach ($experiencia['secciones'] as $idSeccion) {
                $idsSeccionesAlcance[] = $idSeccion;
            }
        }

        $idsSeccionesEncuesta = array_values(array_intersect(
            array_unique($idsSeccionesEncuesta),
            array_unique($idsSeccionesAlcance),
        ));
        $seccionesPorExperiencia = $this->seccionesPorExperiencia($idsSeccionesEncuesta);
        $satisfaccionPorExperiencia = $this->satisfaccionPorExperiencia($seccionesPorExperiencia);

        $filas = [];
        $numerador = 0;
        $denominador = 0;
        $sinClasificar = 0;

        foreach ($experiencias as $experiencia) {
            $aplica = $experiencia['aplica'];

            if ($aplica === true) {
                $numerador++;
            }

            if ($aplica === null) {
                $sinClasificar++;
            }

            $denominador++;

            $filas[] = (object) [
                'clave' => $experiencia['clave'],
                'id_escuela' => $experiencia['id_escuela'],
                'escuela' => $experiencia['escuela'],
                'id_periodo' => $experiencia['id_periodo'],
                'periodo' => $experiencia['periodo'],
                'id_curso_plan' => $experiencia['id_curso_plan'],
                'id_curso' => $experiencia['id_curso'],
                'curso_codigo' => $experiencia['curso_codigo'],
                'curso' => $experiencia['curso'],
                'secciones' => count($experiencia['secciones']),
                'aplica' => $aplica,
                'fuente' => $experiencia['fuente'],
                'satisfaccion' => $satisfaccionPorExperiencia[$experiencia['clave']] ?? null,
            ];
        }

        $totalSatisfaccion = $this->totalSatisfaccion(
            $satisfaccionPorExperiencia,
            count($experiencias),
        );

        return (object) [
            'experiencia_curricular' => (object) [
                'indicador' => '3.1',
                'nombre' => 'Experiencias curriculares con investigación formativa',
                'numerador' => $numerador,
                'denominador' => $denominador,
                'valor' => $denominador > 0 ? round($numerador / $denominador * 100, 2) : null,
                'unidad' => self::UNIDAD_EXPERIENCIA,
                'regla' => self::REGLA_EXPERIENCIA,
                'sin_clasificar' => $sinClasificar,
                'experiencias' => $filas,
            ],
            'satisfaccion' => (object) [
                'indicador' => '3.2',
                'nombre' => 'Satisfacción estudiantil con estrategias de investigación formativa',
                'numerador' => $totalSatisfaccion->numerador,
                'denominador' => $totalSatisfaccion->denominador,
                'valor' => $totalSatisfaccion->valor,
                'pregunta' => $totalSatisfaccion->pregunta,
                'categorias' => $totalSatisfaccion->categorias,
                'provisional' => $totalSatisfaccion->provisional,
                'fuente' => $totalSatisfaccion->fuente,
                'regla' => $totalSatisfaccion->regla,
                'motivo' => $totalSatisfaccion->motivo,
                'alcance' => 'Suma de las experiencias del alcance. Un estudiante que respondió en dos '
                    .'experiencias cuenta una vez en cada experiencia.',
            ],
        ];
    }

    /**
     * Indicador 3.2 de una sección.
     */
    public function satisfaccionDeSeccion(int $idSeccion): object
    {
        $mapa = $this->seccionesPorExperiencia([$idSeccion]);
        $resultados = $this->satisfaccionPorExperiencia($mapa);
        $resultado = reset($resultados);

        if ($resultado === false) {
            return $this->satisfaccionVacio('La sección no tiene encuesta registrada.');
        }

        return $resultado;
    }

    /**
     * Experiencias curriculares implementadas del alcance.
     *
     * @return list<array{
     *     clave: string,
     *     id_escuela: int,
     *     escuela: string,
     *     id_periodo: int,
     *     periodo: string,
     *     id_curso_plan: int,
     *     id_curso: int,
     *     curso_codigo: string,
     *     curso: string,
     *     secciones: list<int>,
     *     aplica: bool|null,
     *     fuente: string|null
     * }>
     */
    private function experienciasCurriculares(array $idsEscuela, ?string $periodo, ?int $idCurso): array
    {
        if ($idsEscuela === []) {
            return [];
        }

        $filas = DB::table('core.seccion as s')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->join('core.plan_estudios as pe', 'pe.id_plan', '=', 'cp.id_plan')
            ->join('core.escuela_profesional as e', 'e.id_escuela', '=', 'pe.id_escuela')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->join('core.periodo_academico as p', 'p.id_periodo', '=', 's.id_periodo')
            ->whereIn('pe.id_escuela', $idsEscuela)
            ->when($periodo !== null, static fn ($query) => $query->where('p.codigo', $periodo))
            ->when($idCurso !== null, static fn ($query) => $query->where('cp.id_curso', $idCurso))
            ->orderBy('p.fecha_inicio')
            ->orderBy('c.codigo')
            ->get([
                's.id_seccion',
                's.id_periodo',
                's.id_curso_plan',
                'pe.id_escuela',
                'e.nombre as escuela',
                'p.codigo as periodo',
                'c.id_curso',
                'c.codigo as curso_codigo',
                'c.nombre as curso',
            ]);

        $experiencias = [];

        foreach ($filas as $fila) {
            $clave = $fila->id_periodo.'-'.$fila->id_curso_plan;

            if (! isset($experiencias[$clave])) {
                $experiencias[$clave] = [
                    'clave' => $clave,
                    'id_escuela' => (int) $fila->id_escuela,
                    'escuela' => (string) $fila->escuela,
                    'id_periodo' => (int) $fila->id_periodo,
                    'periodo' => (string) $fila->periodo,
                    'id_curso_plan' => (int) $fila->id_curso_plan,
                    'id_curso' => (int) $fila->id_curso,
                    'curso_codigo' => (string) $fila->curso_codigo,
                    'curso' => (string) $fila->curso,
                    'secciones' => [],
                    'aplica' => null,
                    'fuente' => null,
                ];
            }

            $experiencias[$clave]['secciones'][] = (int) $fila->id_seccion;
        }

        if ($experiencias === []) {
            return [];
        }

        $registros = DB::table('investigacion_formativa.indicador_experiencia')
            ->whereIn('id_escuela', $idsEscuela)
            ->get(['id_periodo', 'id_curso_plan', 'aplica_investigacion_formativa', 'fuente'])
            ->keyBy(static fn (object $fila): string => $fila->id_periodo.'-'.$fila->id_curso_plan);

        foreach ($experiencias as $clave => $experiencia) {
            $registro = $registros->get($clave);

            if ($registro === null) {
                continue;
            }

            $experiencias[$clave]['aplica'] = (bool) $registro->aplica_investigacion_formativa;
            $experiencias[$clave]['fuente'] = (string) $registro->fuente;
        }

        return array_values($experiencias);
    }

    /**
     * @param  list<int>  $idsSecciones
     * @return array<string, list<int>>
     */
    private function seccionesPorExperiencia(array $idsSecciones): array
    {
        if ($idsSecciones === []) {
            return [];
        }

        $filas = DB::table('core.seccion')
            ->whereIn('id_seccion', $idsSecciones)
            ->get(['id_seccion', 'id_periodo', 'id_curso_plan']);

        $mapa = [];

        foreach ($filas as $fila) {
            $clave = $fila->id_periodo.'-'.$fila->id_curso_plan;
            $mapa[$clave][] = (int) $fila->id_seccion;
        }

        return $mapa;
    }

    /**
     * @param  array<string, list<int>>  $seccionesPorExperiencia
     * @return array<string, object>
     */
    private function satisfaccionPorExperiencia(array $seccionesPorExperiencia): array
    {
        if ($seccionesPorExperiencia === []) {
            return [];
        }

        $configuracion = $this->configuracionSatisfaccion();
        $idsSecciones = [];

        foreach ($seccionesPorExperiencia as $ids) {
            foreach ($ids as $idSeccion) {
                $idsSecciones[] = $idSeccion;
            }
        }

        $idsSecciones = array_values(array_unique($idsSecciones));
        $resultados = [];

        foreach ($seccionesPorExperiencia as $clave => $ids) {
            $resultados[$clave] = $this->resultadoVacio($configuracion);
        }

        if ($configuracion['pregunta'] === null || $configuracion['categorias'] === null) {
            return $resultados;
        }

        $preguntas = DB::table('investigacion_formativa.encuesta_pregunta as ep')
            ->join('investigacion_formativa.encuesta as e', 'e.id_encuesta', '=', 'ep.id_encuesta')
            ->whereIn('e.id_seccion', $idsSecciones)
            ->where('ep.texto', $configuracion['pregunta'])
            ->get(['ep.id_pregunta', 'e.id_seccion']);

        if ($preguntas->isEmpty()) {
            foreach ($resultados as $clave => $resultado) {
                $resultados[$clave] = $this->resultadoVacio($configuracion, [
                    'motivo' => 'Ninguna encuesta de la sección incluye la pregunta de satisfacción configurada.',
                ]);
            }

            return $resultados;
        }

        $respuestas = DB::table('investigacion_formativa.encuesta_respuesta_item as i')
            ->join('investigacion_formativa.encuesta_respuesta as r', 'r.id_respuesta', '=', 'i.id_respuesta')
            ->join('investigacion_formativa.encuesta as e', 'e.id_encuesta', '=', 'i.id_encuesta')
            ->whereIn('i.id_pregunta', $preguntas->pluck('id_pregunta')->all())
            ->orderByDesc('r.respondida_en')
            ->orderByDesc('r.id_respuesta')
            ->get([
                'i.id_respuesta',
                'i.valor',
                'r.id_estudiante',
                'e.id_seccion',
            ]);

        $experienciaDeSeccion = [];

        foreach ($seccionesPorExperiencia as $clave => $ids) {
            foreach ($ids as $idSeccion) {
                $experienciaDeSeccion[$idSeccion] = $clave;
            }
        }

        $valoresPorExperiencia = [];

        foreach ($respuestas as $respuesta) {
            $clave = $experienciaDeSeccion[(int) $respuesta->id_seccion] ?? null;

            if ($clave === null) {
                continue;
            }

            $idEstudiante = (int) $respuesta->id_estudiante;

            if (isset($valoresPorExperiencia[$clave][$idEstudiante])) {
                continue;
            }

            $valoresPorExperiencia[$clave][$idEstudiante] = (int) $respuesta->valor;
        }

        foreach ($resultados as $clave => $resultado) {
            $valores = array_values($valoresPorExperiencia[$clave] ?? []);
            $resultados[$clave] = $this->resultadoSatisfaccion($configuracion, $valores);
        }

        return $resultados;
    }

    /**
     * @param  array<string, int>  $valores
     */
    private function resultadoSatisfaccion(array $configuracion, array $valores): object
    {
        $conteos = [];
        $satisfechos = 0;
        $valoresSatisfechos = [];

        foreach ($configuracion['categorias'] ?? [] as $categoria) {
            if ($categoria['satisfecho']) {
                $valoresSatisfechos[] = $categoria['valor'];
            }
        }

        foreach ($valores as $valor) {
            $conteos[$valor] = ($conteos[$valor] ?? 0) + 1;

            if (in_array($valor, $valoresSatisfechos, true)) {
                $satisfechos++;
            }
        }

        $categorias = [];

        foreach ($configuracion['categorias'] ?? [] as $categoria) {
            $categorias[] = (object) [
                'valor' => $categoria['valor'],
                'etiqueta' => $categoria['etiqueta'],
                'satisfecho' => $categoria['satisfecho'],
                'conteo' => $conteos[$categoria['valor']] ?? 0,
            ];
        }

        $denominador = count($valores);
        $otros = 0;

        foreach ($conteos as $valor => $conteo) {
            $otros += $conteo;
        }

        foreach ($categorias as $categoria) {
            $otros -= $categoria->conteo;
        }

        return (object) [
            'indicador' => '3.2',
            'nombre' => 'Satisfacción estudiantil con estrategias de investigación formativa',
            'pregunta' => $configuracion['pregunta'],
            'categorias' => $categorias,
            'otros' => max(0, $otros),
            'numerador' => $satisfechos,
            'denominador' => $denominador,
            'valor' => $denominador > 0 ? round($satisfechos / $denominador * 100, 2) : null,
            'provisional' => $configuracion['provisional'],
            'fuente' => $configuracion['fuente'],
            'regla' => $this->reglaSatisfaccion($configuracion),
            'motivo' => $denominador > 0 ? null : 'No hay estudiantes que respondieron la pregunta de satisfacción.',
        ];
    }

    /**
     * @param  array<string, mixed>  $configuracion
     */
    private function resultadoVacio(array $configuracion, array $cambios = []): object
    {
        $base = [
            'indicador' => '3.2',
            'nombre' => 'Satisfacción estudiantil con estrategias de investigación formativa',
            'pregunta' => $configuracion['pregunta'],
            'categorias' => [],
            'otros' => 0,
            'numerador' => 0,
            'denominador' => 0,
            'valor' => null,
            'provisional' => $configuracion['provisional'],
            'fuente' => $configuracion['fuente'],
            'regla' => $this->reglaSatisfaccion($configuracion),
            'motivo' => $configuracion['motivo'],
        ];

        return (object) array_merge($base, $cambios);
    }

    /**
     * @param  array<string, object>  $resultados
     */
    private function totalSatisfaccion(array $resultados, int $cantidadExperiencias): object
    {
        $numerador = 0;
        $denominador = 0;
        $pregunta = null;
        $provisional = true;
        $fuente = null;
        $regla = '';
        $motivo = null;
        $categorias = [];

        foreach ($resultados as $resultado) {
            $numerador += (int) $resultado->numerador;
            $denominador += (int) $resultado->denominador;

            if ($pregunta === null) {
                $pregunta = $resultado->pregunta;
                $provisional = (bool) $resultado->provisional;
                $fuente = $resultado->fuente;
                $regla = (string) $resultado->regla;
                $motivo = $resultado->motivo;
            }

            foreach ($resultado->categorias as $categoria) {
                $clave = (string) $categoria->valor;

                if (! isset($categorias[$clave])) {
                    $categorias[$clave] = (object) [
                        'valor' => $categoria->valor,
                        'etiqueta' => $categoria->etiqueta,
                        'satisfecho' => $categoria->satisfecho,
                        'conteo' => 0,
                    ];
                }

                $categorias[$clave]->conteo += (int) $categoria->conteo;
            }
        }

        if ($resultados === []) {
            $configuracion = $this->configuracionSatisfaccion();
            $pregunta = $configuracion['pregunta'];
            $provisional = $configuracion['provisional'];
            $fuente = $configuracion['fuente'];
            $regla = $this->reglaSatisfaccion($configuracion);

            if ($cantidadExperiencias === 0) {
                $motivo = 'El alcance no tiene experiencias curriculares.';
            } elseif ($configuracion['motivo'] !== null) {
                $motivo = $configuracion['motivo'];
            } else {
                $motivo = 'Ninguna sección del alcance tiene una encuesta con la pregunta de satisfacción configurada.';
            }
        }

        if ($denominador > 0) {
            $motivo = null;
        }

        return (object) [
            'numerador' => $numerador,
            'denominador' => $denominador,
            'valor' => $denominador > 0 ? round($numerador / $denominador * 100, 2) : null,
            'pregunta' => $pregunta,
            'categorias' => array_values($categorias),
            'provisional' => $provisional,
            'fuente' => $fuente,
            'regla' => $regla,
            'motivo' => $motivo,
        ];
    }

    /**
     * @return array{
     *     pregunta: string|null,
     *     categorias: list<array{valor: int, etiqueta: string, satisfecho: bool}>|null,
     *     provisional: bool,
     *     fuente: string|null,
     *     motivo: string|null
     * }
     */
    private function configuracionSatisfaccion(): array
    {
        $filas = DB::table('investigacion_formativa.configuracion_indicador')
            ->where('indicador', '3.2')
            ->get(['parametro', 'valor', 'fuente', 'provisional'])
            ->keyBy('parametro');

        $preguntaFila = $filas->get(self::PARAMETRO_PREGUNTA);
        $categoriasFila = $filas->get(self::PARAMETRO_CATEGORIAS);

        $pregunta = $preguntaFila === null ? null : trim((string) $preguntaFila->valor);
        $provisional = $preguntaFila !== null ? (bool) $preguntaFila->provisional : true;
        $fuente = $preguntaFila === null ? null : (string) $preguntaFila->fuente;

        if ($pregunta === null || $pregunta === '') {
            return [
                'pregunta' => null,
                'categorias' => null,
                'provisional' => true,
                'fuente' => null,
                'motivo' => 'No hay una pregunta de satisfacción configurada para el indicador 3.2.',
            ];
        }

        if ($categoriasFila === null) {
            return [
                'pregunta' => $pregunta,
                'categorias' => null,
                'provisional' => $provisional,
                'fuente' => $fuente,
                'motivo' => 'No hay categorías de satisfacción configuradas para el indicador 3.2.',
            ];
        }

        $decodificado = json_decode((string) $categoriasFila->valor, true);

        if (! is_array($decodificado) || $decodificado === []) {
            return [
                'pregunta' => $pregunta,
                'categorias' => null,
                'provisional' => $provisional,
                'fuente' => $fuente,
                'motivo' => 'La configuración de categorías de satisfacción no es válida.',
            ];
        }

        $categorias = [];
        $valores = [];

        foreach ($decodificado as $categoria) {
            if (! is_array($categoria)) {
                return [
                    'pregunta' => $pregunta,
                    'categorias' => null,
                    'provisional' => $provisional,
                    'fuente' => $fuente,
                    'motivo' => 'La configuración de categorías de satisfacción no es válida.',
                ];
            }

            $valor = $categoria['valor'] ?? null;
            $etiqueta = trim((string) ($categoria['etiqueta'] ?? ''));
            $satisfecho = $categoria['satisfecho'] ?? null;

            if (! is_int($valor)
                || $valor < 1
                || $valor > 5
                || $etiqueta === ''
                || ! is_bool($satisfecho)) {
                return [
                    'pregunta' => $pregunta,
                    'categorias' => null,
                    'provisional' => $provisional,
                    'fuente' => $fuente,
                    'motivo' => 'La configuración de categorías de satisfacción no es válida.',
                ];
            }

            if (isset($valores[$valor])) {
                return [
                    'pregunta' => $pregunta,
                    'categorias' => null,
                    'provisional' => $provisional,
                    'fuente' => $fuente,
                    'motivo' => 'Las categorías de satisfacción se superponen. Cada valor debe tener una sola categoría.',
                ];
            }

            $valores[$valor] = true;
            $categorias[] = [
                'valor' => $valor,
                'etiqueta' => $etiqueta,
                'satisfecho' => $satisfecho,
            ];
        }

        if (! array_filter($categorias, static fn (array $categoria): bool => $categoria['satisfecho'])) {
            return [
                'pregunta' => $pregunta,
                'categorias' => null,
                'provisional' => $provisional,
                'fuente' => $fuente,
                'motivo' => 'Ninguna categoría de satisfacción está marcada como satisfactoria.',
            ];
        }

        return [
            'pregunta' => $pregunta,
            'categorias' => $categorias,
            'provisional' => $provisional,
            'fuente' => $fuente,
            'motivo' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $configuracion
     */
    private function reglaSatisfaccion(array $configuracion): string
    {
        if ($configuracion['pregunta'] === null) {
            return 'Cada estudiante cuenta una vez. El valor se clasifica con las categorías configuradas, '
                .'que son excluyentes entre sí.';
        }

        $etiquetas = [];

        foreach ($configuracion['categorias'] ?? [] as $categoria) {
            $etiquetas[] = $categoria['valor'].' = '.$categoria['etiqueta'];
        }

        return 'Pregunta: «'.$configuracion['pregunta'].'». Cada estudiante cuenta una vez. '
            .'Si respondió en más de una sección de la experiencia, se usa su respuesta más reciente. '
            .'Categorías: '.(implode(', ', $etiquetas) === '' ? 'sin configurar' : implode(', ', $etiquetas)).'. '
            .'El denominador son los estudiantes distintos que respondieron la pregunta.';
    }
}
