<?php

namespace Tests\Feature\InvestigacionFormativa\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Datos de entrada para verificar los indicadores del módulo.
 *
 * Requiere también el trait CreaDatosInvestigacion, que crea las secciones,
 * los estudiantes y el director de prueba.
 */
trait CreaDatosIndicador
{
    protected const PREGUNTA_SATISFACCION_INDICADOR = 'Satisfacción general con la experiencia de investigación formativa';

    protected function configurarSatisfaccionIndicador(): void
    {
        DB::table('investigacion_formativa.configuracion_indicador')->insert([
            [
                'indicador' => '3.2',
                'parametro' => 'pregunta_satisfaccion',
                'valor' => self::PREGUNTA_SATISFACCION_INDICADOR,
                'fuente' => 'Configuración de prueba; sustituir por la definición oficial.',
                'provisional' => true,
            ],
            [
                'indicador' => '3.2',
                'parametro' => 'categorias_satisfaccion',
                'valor' => json_encode([
                    ['valor' => 1, 'etiqueta' => 'Muy insatisfecho', 'satisfecho' => false],
                    ['valor' => 2, 'etiqueta' => 'Insatisfecho', 'satisfecho' => false],
                    ['valor' => 3, 'etiqueta' => 'Neutral', 'satisfecho' => false],
                    ['valor' => 4, 'etiqueta' => 'Satisfecho', 'satisfecho' => true],
                    ['valor' => 5, 'etiqueta' => 'Muy satisfecho', 'satisfecho' => true],
                ], JSON_THROW_ON_ERROR),
                'fuente' => 'Configuración de prueba; sustituir por la definición oficial.',
                'provisional' => true,
            ],
        ]);
    }

    protected function configurarLogroIndicador(
        ?array $bandas = null,
        string $coberturaMinima = '100',
        bool $provisional = true,
    ): void {
        $bandas ??= [
            ['etiqueta' => 'Por lograr', 'minimo' => 0, 'maximo' => 60, 'alcanzado' => false],
            ['etiqueta' => 'En proceso', 'minimo' => 60, 'maximo' => 80, 'alcanzado' => false],
            ['etiqueta' => 'Logrado', 'minimo' => 80, 'maximo' => 100, 'alcanzado' => true],
        ];

        DB::table('investigacion_formativa.configuracion_indicador')->insert([
            [
                'indicador' => '3.3',
                'parametro' => 'bandas_logro',
                'valor' => json_encode($bandas, JSON_THROW_ON_ERROR),
                'fuente' => 'Configuración de prueba; sustituir por la definición oficial.',
                'provisional' => $provisional,
            ],
            [
                'indicador' => '3.3',
                'parametro' => 'cobertura_minima',
                'valor' => $coberturaMinima,
                'fuente' => 'Configuración de prueba; sustituir por la definición oficial.',
                'provisional' => $provisional,
            ],
        ]);
    }

    protected function registrarMapeoIndicador(
        int $idCompetencia,
        int $idCriterio,
        float $peso,
        bool $provisional = true,
        string $fuente = 'Mapeo de prueba; sustituir por la matriz autorizada.',
    ): void {
        DB::table('investigacion_formativa.indicador_competencia_criterio')->insert([
            'id_competencia' => $idCompetencia,
            'id_criterio' => $idCriterio,
            'peso' => $peso,
            'fuente' => $fuente,
            'provisional' => $provisional,
        ]);
    }

    protected function registrarExperienciaIndicador(
        int $idPeriodo,
        int $idCursoPlan,
        int $idEscuela,
        bool $aplica,
        string $fuente = 'Catálogo de prueba; sustituir por la fuente autorizada.',
    ): void {
        DB::table('investigacion_formativa.indicador_experiencia')->insert([
            'id_periodo' => $idPeriodo,
            'id_curso_plan' => $idCursoPlan,
            'id_escuela' => $idEscuela,
            'aplica_investigacion_formativa' => $aplica,
            'fuente' => $fuente,
        ]);
    }

    /**
     * @param  array<int, int>  $respuestas  Estudiante y valor de satisfacción.
     */
    protected function crearEncuestaSatisfaccionIndicador(
        int $idSeccion,
        array $respuestas,
        string $respondidaEn = '2026-11-01 12:00:00+00',
    ): void {
        $encuesta = DB::table('investigacion_formativa.encuesta')->insertGetId([
            'id_seccion' => $idSeccion,
            'estado' => 'publicada',
            'creada_por' => $this->datos['users']['director']->id,
            'publicada_en' => '2026-10-01 08:00:00+00',
            'version' => 2,
        ], 'id_encuesta');
        $pregunta = DB::table('investigacion_formativa.encuesta_pregunta')->insertGetId([
            'id_encuesta' => $encuesta,
            'orden' => 1,
            'texto' => self::PREGUNTA_SATISFACCION_INDICADOR,
        ], 'id_pregunta');

        foreach ($respuestas as $idEstudiante => $valor) {
            $respuesta = DB::table('investigacion_formativa.encuesta_respuesta')->insertGetId([
                'id_encuesta' => $encuesta,
                'id_estudiante' => $idEstudiante,
                'comentario' => null,
                'respondida_en' => $respondidaEn,
            ], 'id_respuesta');

            DB::table('investigacion_formativa.encuesta_respuesta_item')->insert([
                'id_encuesta' => $encuesta,
                'id_respuesta' => $respuesta,
                'id_pregunta' => $pregunta,
                'valor' => $valor,
            ]);
        }
    }
}
