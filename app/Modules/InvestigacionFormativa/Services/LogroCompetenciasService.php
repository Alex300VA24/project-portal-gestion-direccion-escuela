<?php

namespace App\Modules\InvestigacionFormativa\Services;

use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Calcula el indicador 3.3 con evidencia de criterios y mapeos explícitos.
 *
 * La unidad de análisis es el estudiante dentro de una competencia de una
 * sección. El puntaje de cada criterio se normaliza con el máximo de la
 * rúbrica y se combina mediante los pesos del mapeo. Solo participan
 * evaluaciones de la versión vigente de cada entrega.
 *
 * La nota final, el estado del título y las decisiones docentes no se leen ni
 * se usan como sustituto del logro de competencia.
 */
final class LogroCompetenciasService
{
    private const PARAMETRO_BANDAS = 'bandas_logro';

    private const PARAMETRO_COBERTURA_MINIMA = 'cobertura_minima';

    private const REGLA = 'Unidad: estudiante y competencia. El puntaje es el promedio ponderado de los '
        .'puntajes normalizados de los criterios mapeados y solo usa evaluaciones vigentes de las entregas '
        .'actuales. Un estudiante es válido cuando alcanza la cobertura mínima configurada. El porcentaje '
        .'cuenta estudiantes en bandas con alcance verdadero. La nota final, el estado del título y las '
        .'decisiones docentes no participan.';

    /**
     * Logro de competencias para las secciones indicadas.
     *
     * @param  list<int>  $idsSecciones
     */
    public function porSecciones(array $idsSecciones, ?int $idLinea = null): object
    {
        $idsSecciones = array_values(array_unique(array_filter(
            array_map('intval', $idsSecciones),
            static fn (int $idSeccion): bool => $idSeccion > 0,
        )));
        $configuracion = $this->configuracion();

        if (! $configuracion->valida) {
            return $this->resultadoVacio(
                $configuracion->motivo,
                $configuracion->provisional,
                $configuracion->fuente,
                [],
            );
        }

        if ($idsSecciones === []) {
            return $this->resultadoVacio(
                'El alcance seleccionado no tiene secciones.',
                $configuracion->provisional,
                $configuracion->fuente,
                $configuracion->bandas,
            );
        }

        $mapeos = $this->mapeos($idsSecciones);

        if ($mapeos->isEmpty()) {
            return $this->resultadoVacio(
                'No existen mapeos explícitos entre criterios y competencias seleccionadas.',
                $configuracion->provisional,
                $configuracion->fuente,
                $configuracion->bandas,
            );
        }

        $idsCriterios = $mapeos->pluck('id_criterio')->map(static fn (mixed $id): int => (int) $id)->unique()->all();
        $estudiantes = $this->estudiantesElegibles($idsSecciones, $idLinea);
        $evidencia = $this->evidenciaCriterios($idsSecciones, $idLinea, $idsCriterios);
        $secciones = $this->datosDeSecciones($idsSecciones);
        $filas = [];

        foreach ($mapeos->groupBy(static fn (object $mapeo): string => $mapeo->id_seccion.'-'.$mapeo->id_competencia) as $grupo) {
            $filas[] = $this->calcularCompetencia(
                $grupo->values()->all(),
                $estudiantes,
                $evidencia,
                $secciones,
                $configuracion,
            );
        }

        return $this->agregar($filas, $configuracion);
    }

    /**
     * Logro de competencias de una sección.
     */
    public function porSeccion(int $idSeccion, ?int $idLinea = null): object
    {
        return $this->porSecciones([$idSeccion], $idLinea);
    }

    /**
     * @param  list<object>  $filas
     */
    private function agregar(array $filas, object $configuracion): object
    {
        $calculables = array_values(array_filter(
            $filas,
            static fn (object $fila): bool => (bool) $fila->calculable,
        ));
        $numerador = 0;
        $denominador = 0;
        $esperados = 0;
        $sumaPuntajes = 0.0;
        $conteoBandas = [];

        foreach ($configuracion->bandas as $banda) {
            $conteoBandas[$banda->etiqueta] = 0;
        }

        foreach ($calculables as $fila) {
            $numerador += $fila->numerador;
            $denominador += $fila->denominador;
            $esperados += $fila->esperados;
            $sumaPuntajes += ($fila->puntaje_promedio ?? 0) * $fila->denominador;

            foreach ($fila->bandas as $banda) {
                $conteoBandas[$banda->etiqueta] = ($conteoBandas[$banda->etiqueta] ?? 0) + $banda->conteo;
            }
        }

        $bandas = array_map(
            static fn (object $banda): object => (object) [
                'etiqueta' => $banda->etiqueta,
                'minimo' => $banda->minimo,
                'maximo' => $banda->maximo,
                'alcanzado' => $banda->alcanzado,
                'conteo' => $conteoBandas[$banda->etiqueta] ?? 0,
            ],
            $configuracion->bandas,
        );
        $provisional = $configuracion->provisional;
        $fuentes = [];

        if ($configuracion->fuente !== null) {
            $fuentes[] = $configuracion->fuente;
        }

        foreach ($filas as $fila) {
            $provisional = $provisional || $fila->provisional;

            foreach ($fila->fuentes as $fuente) {
                $fuentes[] = $fuente;
            }
        }

        $fuentes = array_values(array_unique(array_filter($fuentes)));
        $motivo = null;

        if ($calculables === []) {
            $motivo = 'Los mapeos disponibles no son válidos para las rúbricas de las secciones.';
        } elseif ($denominador === 0) {
            $motivo = 'No hay estudiantes con cobertura suficiente para calcular el logro.';
        }

        return (object) [
            'indicador' => '3.3',
            'nombre' => 'Logro de competencias relacionadas con investigación formativa',
            'valor' => $denominador > 0 ? round($numerador / $denominador * 100, 2) : null,
            'numerador' => $numerador,
            'denominador' => $denominador,
            'esperados' => $esperados,
            'cobertura' => $esperados > 0 ? round($denominador / $esperados * 100, 2) : null,
            'puntaje_promedio' => $denominador > 0 ? round($sumaPuntajes / $denominador, 2) : null,
            'estado' => $denominador > 0 ? 'Calculado' : 'Sin datos',
            'provisional' => $provisional,
            'fuente' => $fuentes === [] ? null : implode(' · ', $fuentes),
            'regla' => self::REGLA,
            'motivo' => $motivo,
            'bandas' => $bandas,
            'competencias' => $filas,
        ];
    }

    /**
     * @param  list<object>  $mapeos
     * @param  array<int, list<int>>  $estudiantes
     * @param  array<int, array<int, array{promedio: float, evidencias: int}>>  $evidencia
     * @param  array<int, object>  $secciones
     */
    private function calcularCompetencia(
        array $mapeos,
        array $estudiantes,
        array $evidencia,
        array $secciones,
        object $configuracion,
    ): object {
        $primero = $mapeos[0];
        $idSeccion = (int) $primero->id_seccion;
        $idCompetencia = (int) $primero->id_competencia;
        $estudiantesSeccion = $estudiantes[$idSeccion] ?? [];
        $esperados = count($estudiantesSeccion);
        $criterios = [];
        $fuentes = [];
        $provisional = false;
        $invalido = null;

        foreach ($mapeos as $mapeo) {
            $idCriterio = (int) $mapeo->id_criterio;
            $peso = (float) $mapeo->peso;
            $criterios[] = (object) [
                'id_criterio' => $idCriterio,
                'descripcion' => (string) $mapeo->descripcion_criterio,
                'peso' => $peso,
                'fuente' => (string) $mapeo->fuente,
            ];
            $fuentes[] = (string) $mapeo->fuente;
            $provisional = $provisional || (bool) $mapeo->provisional;

            if ($mapeo->id_rubrica_seccion === null
                || (int) $mapeo->id_rubrica_seccion !== (int) $mapeo->id_rubrica_criterio) {
                $invalido = 'Un criterio mapeado no pertenece a la rúbrica vigente de la sección.';
            }
        }

        $fuentes = array_values(array_unique(array_filter($fuentes)));
        $datosSeccion = $secciones[$idSeccion] ?? null;
        $conteoBandas = [];

        foreach ($configuracion->bandas as $banda) {
            $conteoBandas[$banda->etiqueta] = 0;
        }

        if ($invalido !== null) {
            return (object) [
                'id_seccion' => $idSeccion,
                'seccion' => $datosSeccion?->seccion,
                'escuela' => $datosSeccion?->escuela,
                'periodo' => $datosSeccion?->periodo,
                'curso' => $datosSeccion?->curso,
                'id_competencia' => $idCompetencia,
                'codigo' => (string) $primero->codigo,
                'descripcion' => (string) $primero->descripcion,
                'fuente_competencia' => (string) $primero->fuente_competencia,
                'fuentes' => $fuentes,
                'provisional' => $provisional,
                'calculable' => false,
                'valor' => null,
                'numerador' => 0,
                'denominador' => 0,
                'esperados' => $esperados,
                'cobertura' => null,
                'puntaje_promedio' => null,
                'estado' => 'Sin datos',
                'motivo' => $invalido,
                'bandas' => $this->bandasConConteo($configuracion->bandas, $conteoBandas),
                'criterios' => $criterios,
            ];
        }

        $numerador = 0;
        $denominador = 0;
        $sumaPuntajes = 0.0;

        foreach ($estudiantesSeccion as $idEstudiante) {
            $puntajes = [];
            $pesoDisponible = 0.0;
            $sumaPonderada = 0.0;

            foreach ($criterios as $criterio) {
                $dato = $evidencia[$idEstudiante][$criterio->id_criterio] ?? null;

                if ($dato === null) {
                    continue;
                }

                $puntajes[$criterio->id_criterio] = $dato['promedio'];
                $pesoDisponible += $criterio->peso;
                $sumaPonderada += $dato['promedio'] * $criterio->peso;
            }

            $cobertura = count($criterios) > 0 ? count($puntajes) / count($criterios) * 100 : 0.0;

            if ($cobertura + 0.00001 < $configuracion->cobertura_minima || $pesoDisponible <= 0) {
                continue;
            }

            $puntaje = $sumaPonderada / $pesoDisponible * 100;
            $banda = $this->bandaPara($puntaje, $configuracion->bandas);

            if ($banda === null) {
                continue;
            }

            $denominador++;
            $sumaPuntajes += $puntaje;
            $conteoBandas[$banda->etiqueta] = ($conteoBandas[$banda->etiqueta] ?? 0) + 1;

            if ($banda->alcanzado) {
                $numerador++;
            }
        }

        $motivo = null;

        if ($esperados === 0) {
            $motivo = 'No hay estudiantes con proyectos evaluables en la sección.';
        } elseif ($denominador === 0) {
            $motivo = 'No hay estudiantes con cobertura suficiente para calcular el logro.';
        }

        return (object) [
            'id_seccion' => $idSeccion,
            'seccion' => $datosSeccion?->seccion,
            'escuela' => $datosSeccion?->escuela,
            'periodo' => $datosSeccion?->periodo,
            'curso' => $datosSeccion?->curso,
            'id_competencia' => $idCompetencia,
            'codigo' => (string) $primero->codigo,
            'descripcion' => (string) $primero->descripcion,
            'fuente_competencia' => (string) $primero->fuente_competencia,
            'fuentes' => $fuentes,
            'provisional' => $provisional,
            'calculable' => true,
            'valor' => $denominador > 0 ? round($numerador / $denominador * 100, 2) : null,
            'numerador' => $numerador,
            'denominador' => $denominador,
            'esperados' => $esperados,
            'cobertura' => $esperados > 0 ? round($denominador / $esperados * 100, 2) : null,
            'puntaje_promedio' => $denominador > 0 ? round($sumaPuntajes / $denominador, 2) : null,
            'estado' => $denominador > 0 ? 'Calculado' : 'Sin datos',
            'motivo' => $motivo,
            'bandas' => $this->bandasConConteo($configuracion->bandas, $conteoBandas),
            'criterios' => $criterios,
        ];
    }

    /**
     * @param  list<object>  $bandas
     * @param  array<string, int>  $conteo
     * @return list<object>
     */
    private function bandasConConteo(array $bandas, array $conteo): array
    {
        return array_map(
            static fn (object $banda): object => (object) [
                'etiqueta' => $banda->etiqueta,
                'minimo' => $banda->minimo,
                'maximo' => $banda->maximo,
                'alcanzado' => $banda->alcanzado,
                'conteo' => $conteo[$banda->etiqueta] ?? 0,
            ],
            $bandas,
        );
    }

    /**
     * @param  list<object>  $bandas
     */
    private function bandaPara(float $puntaje, array $bandas): ?object
    {
        $ultimo = count($bandas) - 1;

        foreach ($bandas as $indice => $banda) {
            $cumpleMinimo = $puntaje + 0.00001 >= $banda->minimo;
            $cumpleMaximo = $indice === $ultimo
                ? $puntaje <= $banda->maximo + 0.00001
                : $puntaje < $banda->maximo;

            if ($cumpleMinimo && $cumpleMaximo) {
                return $banda;
            }
        }

        return null;
    }

    /**
     * @param  list<int>  $idsSecciones
     */
    private function mapeos(array $idsSecciones)
    {
        return DB::table('investigacion_formativa.indicador_competencia_criterio as m')
            ->join(
                'investigacion_formativa.competencia_seccion as co',
                'co.id_competencia',
                '=',
                'm.id_competencia',
            )
            ->join(
                'investigacion_formativa.criterio as c',
                'c.id_criterio',
                '=',
                'm.id_criterio',
            )
            ->leftJoin(
                'investigacion_formativa.seccion_configuracion as sc',
                'sc.id_seccion',
                '=',
                'co.id_seccion',
            )
            ->whereIn('co.id_seccion', $idsSecciones)
            ->where('co.seleccionada', true)
            ->orderBy('co.id_seccion')
            ->orderBy('co.codigo')
            ->orderBy('c.orden')
            ->get([
                'co.id_seccion',
                'co.id_competencia',
                'co.codigo',
                'co.descripcion',
                'co.fuente as fuente_competencia',
                'm.id_criterio',
                'm.peso',
                'm.fuente',
                'm.provisional',
                'c.descripcion as descripcion_criterio',
                'c.id_rubrica as id_rubrica_criterio',
                'sc.id_rubrica as id_rubrica_seccion',
            ]);
    }

    /**
     * @param  list<int>  $idsSecciones
     * @return array<int, list<int>>
     */
    private function estudiantesElegibles(array $idsSecciones, ?int $idLinea): array
    {
        $filas = DB::table('investigacion_formativa.grupo_estudiante as ge')
            ->join('investigacion_formativa.grupo as g', 'g.id_grupo', '=', 'ge.id_grupo')
            ->join('investigacion_formativa.proyecto as p', 'p.id_grupo', '=', 'g.id_grupo')
            ->whereIn('g.id_seccion', $idsSecciones)
            ->when($idLinea !== null, static fn ($query) => $query->where('p.id_linea', $idLinea))
            ->distinct()
            ->orderBy('g.id_seccion')
            ->orderBy('ge.id_estudiante')
            ->get(['g.id_seccion', 'ge.id_estudiante']);
        $estudiantes = [];

        foreach ($filas as $fila) {
            $estudiantes[(int) $fila->id_seccion][] = (int) $fila->id_estudiante;
        }

        return $estudiantes;
    }

    /**
     * @param  list<int>  $idsSecciones
     * @param  list<int>  $idsCriterios
     * @return array<int, array<int, array{promedio: float, evidencias: int}>>
     */
    private function evidenciaCriterios(array $idsSecciones, ?int $idLinea, array $idsCriterios): array
    {
        if ($idsCriterios === []) {
            return [];
        }

        $entregas = DB::table('investigacion_formativa.entrega as e')
            ->join('investigacion_formativa.entregable as en', 'en.id_entregable', '=', 'e.id_entregable')
            ->join('investigacion_formativa.proyecto as p', 'p.id_proyecto', '=', 'e.id_proyecto')
            ->join('investigacion_formativa.grupo as g', 'g.id_grupo', '=', 'p.id_grupo')
            ->whereIn('g.id_seccion', $idsSecciones)
            ->when($idLinea !== null, static fn ($query) => $query->where('p.id_linea', $idLinea))
            ->get([
                'e.id_entrega',
                'e.id_proyecto',
                'e.id_entregable',
                'e.version',
                'p.id_grupo',
            ]);
        $entregasVigentes = [];

        foreach ($entregas as $entrega) {
            $clave = $entrega->id_proyecto.'-'.$entrega->id_entregable;
            $actual = $entregasVigentes[$clave] ?? null;

            if ($actual === null
                || (int) $entrega->version > (int) $actual->version
                || ((int) $entrega->version === (int) $actual->version
                    && (int) $entrega->id_entrega > (int) $actual->id_entrega)) {
                $entregasVigentes[$clave] = $entrega;
            }
        }

        if ($entregasVigentes === []) {
            return [];
        }

        $entregaPorId = [];

        foreach ($entregasVigentes as $entrega) {
            $entregaPorId[(int) $entrega->id_entrega] = $entrega;
        }

        $estudiantesPorGrupo = DB::table('investigacion_formativa.grupo_estudiante')
            ->whereIn('id_grupo', array_values(array_unique(array_map(
                static fn (object $entrega): int => (int) $entrega->id_grupo,
                $entregaPorId,
            ))))
            ->orderBy('id_grupo')
            ->orderBy('id_estudiante')
            ->get(['id_grupo', 'id_estudiante'])
            ->groupBy('id_grupo');
        $maximos = DB::table('investigacion_formativa.criterio')
            ->whereIn('id_criterio', $idsCriterios)
            ->pluck('puntaje_maximo', 'id_criterio');
        $evaluaciones = DB::table('investigacion_formativa.evaluacion_criterio as ec')
            ->join(
                'investigacion_formativa.evaluacion as ev',
                'ev.id_evaluacion',
                '=',
                'ec.id_evaluacion',
            )
            ->whereIn('ev.id_entrega', array_keys($entregaPorId))
            ->whereIn('ec.id_criterio', $idsCriterios)
            ->get([
                'ev.id_entrega',
                'ec.id_criterio',
                'ec.puntaje',
            ]);
        $ratios = [];

        foreach ($evaluaciones as $evaluacion) {
            $entrega = $entregaPorId[(int) $evaluacion->id_entrega] ?? null;

            if ($entrega === null) {
                continue;
            }

            $idCriterio = (int) $evaluacion->id_criterio;
            $maximo = $this->numero($maximos->get($idCriterio));
            $puntaje = $this->numero($evaluacion->puntaje);

            if ($maximo === null || $maximo <= 0 || $puntaje === null || $puntaje < 0 || $puntaje > $maximo) {
                continue;
            }

            $ratio = $puntaje / $maximo;

            foreach ($estudiantesPorGrupo->get((int) $entrega->id_grupo, collect()) as $estudiante) {
                $ratios[(int) $estudiante->id_estudiante][$idCriterio][] = $ratio;
            }
        }

        $evidencia = [];

        foreach ($ratios as $idEstudiante => $criterios) {
            foreach ($criterios as $idCriterio => $valores) {
                $evidencia[$idEstudiante][$idCriterio] = [
                    'promedio' => array_sum($valores) / count($valores),
                    'evidencias' => count($valores),
                ];
            }
        }

        return $evidencia;
    }

    /**
     * @param  list<int>  $idsSecciones
     * @return array<int, object>
     */
    private function datosDeSecciones(array $idsSecciones): array
    {
        $filas = DB::table('core.seccion as s')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->join('core.plan_estudios as pe', 'pe.id_plan', '=', 'cp.id_plan')
            ->join('core.escuela_profesional as e', 'e.id_escuela', '=', 'pe.id_escuela')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->join('core.periodo_academico as p', 'p.id_periodo', '=', 's.id_periodo')
            ->whereIn('s.id_seccion', $idsSecciones)
            ->get([
                's.id_seccion',
                's.codigo as seccion',
                'e.nombre as escuela',
                'p.codigo as periodo',
                'c.nombre as curso',
            ]);

        return $filas->keyBy('id_seccion')->map(static fn (object $fila): object => $fila)->all();
    }

    private function configuracion(): object
    {
        $filas = DB::table('investigacion_formativa.configuracion_indicador')
            ->where('indicador', '3.3')
            ->get()
            ->keyBy('parametro');
        $provisional = $filas->isNotEmpty();
        $fuentes = [];

        foreach ($filas as $fila) {
            $provisional = $provisional || (bool) $fila->provisional;
            $fuentes[] = (string) $fila->fuente;
        }

        $fuentes = array_values(array_unique(array_filter($fuentes)));
        $fuente = $fuentes === [] ? null : implode(' · ', $fuentes);
        $filaBandas = $filas->get(self::PARAMETRO_BANDAS);
        $filaCobertura = $filas->get(self::PARAMETRO_COBERTURA_MINIMA);

        if ($filaBandas === null || $filaCobertura === null) {
            return (object) [
                'valida' => false,
                'motivo' => 'Falta la configuración de bandas o de cobertura mínima para el indicador 3.3.',
                'bandas' => [],
                'cobertura_minima' => 100.0,
                'provisional' => true,
                'fuente' => $fuente,
            ];
        }

        $cobertura = $this->numero($filaCobertura->valor);

        if ($cobertura === null || $cobertura < 0 || $cobertura > 100) {
            return (object) [
                'valida' => false,
                'motivo' => 'La cobertura mínima configurada para el indicador 3.3 no es válida.',
                'bandas' => [],
                'cobertura_minima' => 100.0,
                'provisional' => $provisional,
                'fuente' => $fuente,
            ];
        }

        try {
            $bandasCrudas = json_decode((string) $filaBandas->valor, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $bandasCrudas = null;
        }

        $bandas = $this->validarBandas($bandasCrudas);

        if ($bandas === null) {
            return (object) [
                'valida' => false,
                'motivo' => 'Las bandas de logro configuradas para el indicador 3.3 no son válidas.',
                'bandas' => [],
                'cobertura_minima' => $cobertura,
                'provisional' => $provisional,
                'fuente' => $fuente,
            ];
        }

        return (object) [
            'valida' => true,
            'motivo' => null,
            'bandas' => $bandas,
            'cobertura_minima' => $cobertura,
            'provisional' => $provisional,
            'fuente' => $fuente,
        ];
    }

    /**
     * @return list<object>|null
     */
    private function validarBandas(mixed $bandasCrudas): ?array
    {
        if (! is_array($bandasCrudas) || $bandasCrudas === []) {
            return null;
        }

        $bandas = [];

        foreach ($bandasCrudas as $bandaCruda) {
            if (! is_array($bandaCruda)) {
                return null;
            }

            $etiqueta = trim((string) ($bandaCruda['etiqueta'] ?? ''));
            $minimo = $this->numero($bandaCruda['minimo'] ?? null);
            $maximo = $this->numero($bandaCruda['maximo'] ?? null);
            $alcanzado = $bandaCruda['alcanzado'] ?? null;

            if ($etiqueta === ''
                || $minimo === null
                || $maximo === null
                || $minimo < 0
                || $maximo > 100
                || $minimo >= $maximo
                || ! is_bool($alcanzado)) {
                return null;
            }

            $bandas[] = (object) [
                'etiqueta' => $etiqueta,
                'minimo' => $minimo,
                'maximo' => $maximo,
                'alcanzado' => $alcanzado,
            ];
        }

        usort(
            $bandas,
            static fn (object $a, object $b): int => $a->minimo <=> $b->minimo,
        );

        if (abs($bandas[0]->minimo) > 0.00001) {
            return null;
        }

        $total = count($bandas);

        foreach ($bandas as $indice => $banda) {
            if ($indice > 0 && abs($bandas[$indice - 1]->maximo - $banda->minimo) > 0.00001) {
                return null;
            }

            if ($indice === $total - 1 && abs($banda->maximo - 100) > 0.00001) {
                return null;
            }
        }

        return $bandas;
    }

    private function numero(mixed $valor): ?float
    {
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        if (is_string($valor) && is_numeric(trim($valor))) {
            return (float) trim($valor);
        }

        return null;
    }

    /**
     * @param  list<object>  $bandas
     */
    private function resultadoVacio(
        string $motivo,
        bool $provisional,
        ?string $fuente,
        array $bandas,
    ): object {
        return (object) [
            'indicador' => '3.3',
            'nombre' => 'Logro de competencias relacionadas con investigación formativa',
            'valor' => null,
            'numerador' => 0,
            'denominador' => 0,
            'esperados' => 0,
            'cobertura' => null,
            'puntaje_promedio' => null,
            'estado' => 'Sin datos',
            'provisional' => $provisional,
            'fuente' => $fuente,
            'regla' => self::REGLA,
            'motivo' => $motivo,
            'bandas' => $this->bandasConConteo($bandas, []),
            'competencias' => [],
        ];
    }
}
