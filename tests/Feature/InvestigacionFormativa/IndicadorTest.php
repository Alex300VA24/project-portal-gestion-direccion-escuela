<?php

namespace Tests\Feature\InvestigacionFormativa;

use App\Modules\InvestigacionFormativa\Services\IndicadorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\InvestigacionFormativa\Concerns\CreaDatosInvestigacion;
use Tests\TestCase;

final class IndicadorTest extends TestCase
{
    use CreaDatosInvestigacion;
    use DatabaseTransactions;

    private const PREGUNTA_SATISFACCION = 'Satisfacción general con la experiencia de investigación formativa';

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL.');
        }
        $this->datos = $this->crearDatosInvestigacion();
    }

    public function test_el_indicador_3_1_cuenta_una_experiencia_por_curso_y_periodo(): void
    {
        $this->crearExperienciasAdicionales();
        $this->registrarExperiencias([
            [202602, 9501, true],
            [202602, 9503, false],
        ]);

        $indicadores = $this->indicadores(920, '2026-II', [9901]);

        $experiencia = $indicadores->experiencia_curricular;
        $this->assertSame(1, $experiencia->numerador);
        $this->assertSame(2, $experiencia->denominador);
        $this->assertSame(50.0, $experiencia->valor);
        $this->assertSame(0, $experiencia->sin_clasificar);
        $this->assertCount(2, $experiencia->experiencias);

        $filas = [];
        foreach ($experiencia->experiencias as $fila) {
            $filas[$fila->id_periodo.'-'.$fila->id_curso_plan] = $fila;
        }

        // Las secciones 9901 y 9903 comparten curso y periodo: una sola experiencia.
        $this->assertSame(2, $filas['202602-9501']->secciones);
        $this->assertTrue($filas['202602-9501']->aplica);
        $this->assertFalse($filas['202602-9503']->aplica);
        $this->assertSame('IF-501', $filas['202602-9501']->curso_codigo);
        $this->assertStringContainsString('curso del plan', $experiencia->unidad);
    }

    public function test_el_denominador_incluye_experiencias_sin_investigacion_formativa(): void
    {
        $this->crearExperienciasAdicionales();
        $this->registrarExperiencias([
            [202602, 9501, true],
            [202602, 9503, false],
        ]);

        $experiencia = $this->indicadores(920, '2026-II', [9901])->experiencia_curricular;

        $this->assertSame(2, $experiencia->denominador);
        $this->assertSame(1, $experiencia->numerador);
        $this->assertStringContainsString('incluidas las que no aplican el módulo', $experiencia->regla);
    }

    public function test_la_habilitacion_del_modulo_no_sustituye_el_registro_de_experiencias(): void
    {
        $this->crearExperienciasAdicionales();
        $this->registrarExperiencias([
            [202602, 9501, true],
        ]);

        // La sección sin registro queda habilitada para el módulo.
        DB::table('investigacion_formativa.seccion_configuracion')->insert([
            'id_seccion' => 9904,
            'habilitada' => true,
            'version' => 1,
        ]);

        $experiencia = $this->indicadores(920, '2026-II', [9901])->experiencia_curricular;

        $this->assertSame(1, $experiencia->numerador);
        $this->assertSame(2, $experiencia->denominador);
        $this->assertSame(1, $experiencia->sin_clasificar);
        $this->assertSame(50.0, $experiencia->valor);
    }

    public function test_sin_denominador_el_indicador_3_1_no_inventa_un_cero(): void
    {
        $experiencia = $this->indicadores(921, '2026-I', [])->experiencia_curricular;

        $this->assertSame(0, $experiencia->denominador);
        $this->assertSame(0, $experiencia->numerador);
        $this->assertNull($experiencia->valor);
    }

    public function test_el_filtro_de_periodo_limita_el_denominador(): void
    {
        $this->crearExperienciasAdicionales();
        $this->registrarExperiencias([
            [202602, 9501, true],
            [202601, 9501, false],
        ]);

        $periodoPrimero = $this->indicadores(920, '2026-I', [])->experiencia_curricular;
        $this->assertSame(1, $periodoPrimero->denominador);
        $this->assertSame(0, $periodoPrimero->numerador);
        $this->assertSame(0.0, $periodoPrimero->valor);

        $periodoSegundo = $this->indicadores(920, '2026-II', [])->experiencia_curricular;
        $this->assertSame(2, $periodoSegundo->denominador);
        $this->assertSame(1, $periodoSegundo->numerador);
        $this->assertSame(1, $periodoSegundo->sin_clasificar);
        $this->assertSame(50.0, $periodoSegundo->valor);
    }

    public function test_el_indicador_3_2_cuenta_estudiantes_distintos_y_categorias_excluyentes(): void
    {
        $this->configurarSatisfaccion();
        $this->crearEncuestaConSatisfaccion(9901, [
            9901 => [5, 1],
            9902 => [4, 5],
            9905 => [3, 5],
        ]);

        $satisfaccion = $this->indicadores(920, '2026-II', [9901])->satisfaccion;

        $this->assertSame(3, $satisfaccion->denominador);
        $this->assertSame(2, $satisfaccion->numerador);
        $this->assertSame(66.67, $satisfaccion->valor);
        $this->assertSame(self::PREGUNTA_SATISFACCION, $satisfaccion->pregunta);
        $this->assertTrue($satisfaccion->provisional);
        $this->assertNull($satisfaccion->motivo);

        $conteos = [];
        foreach ($satisfaccion->categorias as $categoria) {
            $conteos[$categoria->valor] = $categoria->conteo;
        }

        $this->assertSame([4 => 1, 5 => 1], $conteos);
        $this->assertStringContainsString('4 = Satisfecho', $satisfaccion->regla);
        $this->assertStringContainsString('5 = Muy satisfecho', $satisfaccion->regla);
        $this->assertStringContainsString('estudiantes distintos', $satisfaccion->regla);

        // Los ítems de la encuesta no son el denominador del indicador.
        $this->assertSame(6, DB::table('investigacion_formativa.encuesta_respuesta_item')->count());
    }

    public function test_el_indicador_3_2_no_promedia_otras_preguntas(): void
    {
        $this->configurarSatisfaccion();
        $this->crearEncuestaConSatisfaccion(9901, [
            9901 => [4, 1],
            9902 => [2, 5],
        ]);

        $satisfaccion = $this->indicadores(920, '2026-II', [9901])->satisfaccion;

        $this->assertSame(2, $satisfaccion->denominador);
        $this->assertSame(1, $satisfaccion->numerador);
        $this->assertSame(50.0, $satisfaccion->valor);
    }

    public function test_un_estudiante_cuenta_una_vez_por_experiencia(): void
    {
        $this->crearExperienciasAdicionales();
        $this->configurarSatisfaccion();
        $this->crearEncuestaConSatisfaccion(9901, [9901 => [5, 5]], '2026-11-01 10:00:00+00');
        $this->crearEncuestaConSatisfaccion(9903, [9901 => [3, 5]], '2026-11-02 10:00:00+00');

        $indicadores = $this->indicadores(920, '2026-II', [9901, 9903]);
        $filas = [];
        foreach ($indicadores->experiencia_curricular->experiencias as $fila) {
            $filas[$fila->id_periodo.'-'.$fila->id_curso_plan] = $fila;
        }

        $satisfaccion = $filas['202602-9501']->satisfaccion;
        $this->assertSame(1, $satisfaccion->denominador);
        $this->assertSame(0, $satisfaccion->numerador);
        $this->assertSame(0.0, $satisfaccion->valor);
    }

    public function test_el_indicador_3_2_sin_configuracion_no_calcula_y_explica_el_faltante(): void
    {
        $this->crearEncuestaConSatisfaccion(9901, [9901 => [5, 5]]);

        $satisfaccion = $this->indicadores(920, '2026-II', [9901])->satisfaccion;

        $this->assertNull($satisfaccion->valor);
        $this->assertSame(0, $satisfaccion->denominador);
        $this->assertStringContainsString('pregunta de satisfacción configurada', (string) $satisfaccion->motivo);
    }

    public function test_las_categorias_superpuestas_se_rechazan(): void
    {
        DB::table('investigacion_formativa.configuracion_indicador')->insert([
            [
                'indicador' => '3.2',
                'parametro' => 'pregunta_satisfaccion',
                'valor' => self::PREGUNTA_SATISFACCION,
                'fuente' => 'Prueba',
                'provisional' => true,
            ],
            [
                'indicador' => '3.2',
                'parametro' => 'categorias_satisfaccion',
                'valor' => json_encode([
                    ['valor' => 4, 'etiqueta' => 'Satisfecho', 'satisfecho' => true],
                    ['valor' => 4, 'etiqueta' => 'Muy satisfecho', 'satisfecho' => true],
                ], JSON_THROW_ON_ERROR),
                'fuente' => 'Prueba',
                'provisional' => true,
            ],
        ]);

        $this->crearEncuestaConSatisfaccion(9901, [9901 => [4, 4]]);

        $satisfaccion = $this->indicadores(920, '2026-II', [9901])->satisfaccion;

        $this->assertNull($satisfaccion->valor);
        $this->assertSame(0, $satisfaccion->denominador);
        $this->assertStringContainsString('superponen', (string) $satisfaccion->motivo);
    }

    public function test_el_indicador_3_2_de_una_seccion_usa_su_propia_encuesta(): void
    {
        $this->configurarSatisfaccion();
        $this->crearEncuestaConSatisfaccion(9901, [9901 => [5, 5], 9902 => [4, 5]]);

        $satisfaccion = app(IndicadorService::class)->satisfaccionDeSeccion(9901);

        $this->assertSame(2, $satisfaccion->denominador);
        $this->assertSame(2, $satisfaccion->numerador);
        $this->assertSame(100.0, $satisfaccion->valor);
        $this->assertSame(self::PREGUNTA_SATISFACCION, $satisfaccion->pregunta);
    }

    public function test_agrega_varias_escuelas_sin_duplicar_experiencias(): void
    {
        DB::table('investigacion_formativa.indicador_experiencia')->insert([
            [
                'id_periodo' => 202602,
                'id_curso_plan' => 9501,
                'id_escuela' => 920,
                'aplica_investigacion_formativa' => true,
                'fuente' => 'Prueba',
            ],
            [
                'id_periodo' => 202602,
                'id_curso_plan' => 9502,
                'id_escuela' => 921,
                'aplica_investigacion_formativa' => true,
                'fuente' => 'Prueba',
            ],
        ]);

        $indicadores = app(IndicadorService::class)->indicadoresDeAlcance(
            [920, 921],
            '2026-II',
        );

        $this->assertSame(2, $indicadores->experiencia_curricular->denominador);
        $this->assertSame(2, $indicadores->experiencia_curricular->numerador);
        $this->assertSame(100.0, $indicadores->experiencia_curricular->valor);
        $this->assertCount(2, $indicadores->experiencia_curricular->experiencias);
    }

    public function test_no_incluye_una_encuesta_fuera_del_alcance(): void
    {
        $this->configurarSatisfaccion();
        $this->crearEncuestaConSatisfaccion(9902, [9903 => [5, 5]]);

        $satisfaccion = $this->indicadores(920, '2026-II', [9902])->satisfaccion;

        $this->assertNull($satisfaccion->valor);
        $this->assertSame(0, $satisfaccion->denominador);
        $this->assertStringContainsString(
            'Ninguna sección del alcance',
            (string) $satisfaccion->motivo,
        );
    }

    private function indicadores(int $idEscuela, ?string $periodo, array $idsSecciones): object
    {
        return app(IndicadorService::class)->indicadoresDeAlcance(
            [$idEscuela],
            $periodo,
            null,
            $idsSecciones,
        );
    }

    private function crearExperienciasAdicionales(): void
    {
        DB::table('core.periodo_academico')->insert([
            'id_periodo' => 202601,
            'codigo' => '2026-I',
            'anio' => 2026,
            'tipo' => 'I',
            'fecha_inicio' => '2026-02-01',
            'fecha_fin' => '2026-07-20',
            'numero_semanas' => 17,
            'estado' => 'Cerrado',
        ]);

        DB::table('core.curso')->insert([
            'id_curso' => 9703,
            'codigo' => 'GP-201',
            'nombre' => 'Gestión de proyectos',
            'creditos' => 2,
            'estado' => 'Activo',
        ]);

        DB::table('core.curso_plan')->insert([
            'id_curso_plan' => 9503,
            'id_plan' => 950,
            'id_curso' => 9703,
            'ciclo' => 6,
            'tipo' => 'Obligatorio',
            'id_area' => 960,
            'creditos_requeridos' => 0,
        ]);

        DB::table('core.seccion')->insert([
            [
                'id_seccion' => 9903,
                'id_periodo' => 202602,
                'id_curso_plan' => 9501,
                'codigo' => 'A2',
                'capacidad' => 30,
                'id_docente_responsable' => 9801,
                'estado' => 'Activa',
            ],
            [
                'id_seccion' => 9904,
                'id_periodo' => 202602,
                'id_curso_plan' => 9503,
                'codigo' => 'C',
                'capacidad' => 30,
                'id_docente_responsable' => 9801,
                'estado' => 'Activa',
            ],
            [
                'id_seccion' => 9905,
                'id_periodo' => 202601,
                'id_curso_plan' => 9501,
                'codigo' => 'A-I',
                'capacidad' => 30,
                'id_docente_responsable' => 9801,
                'estado' => 'Cerrada',
            ],
        ]);
    }

    /**
     * @param  list<array{0: int, 1: int, 2: bool}>  $registros
     */
    private function registrarExperiencias(array $registros): void
    {
        foreach ($registros as [$idPeriodo, $idCursoPlan, $aplica]) {
            DB::table('investigacion_formativa.indicador_experiencia')->insert([
                'id_periodo' => $idPeriodo,
                'id_curso_plan' => $idCursoPlan,
                'id_escuela' => 920,
                'aplica_investigacion_formativa' => $aplica,
                'fuente' => 'Catálogo de demostración; sustituir por la fuente autorizada.',
            ]);
        }
    }

    private function configurarSatisfaccion(): void
    {
        DB::table('investigacion_formativa.configuracion_indicador')->insert([
            [
                'indicador' => '3.2',
                'parametro' => 'pregunta_satisfaccion',
                'valor' => self::PREGUNTA_SATISFACCION,
                'fuente' => 'Configuración de demostración; sustituir por la definición oficial.',
                'provisional' => true,
            ],
            [
                'indicador' => '3.2',
                'parametro' => 'categorias_satisfaccion',
                'valor' => json_encode([
                    ['valor' => 4, 'etiqueta' => 'Satisfecho', 'satisfecho' => true],
                    ['valor' => 5, 'etiqueta' => 'Muy satisfecho', 'satisfecho' => true],
                ], JSON_THROW_ON_ERROR),
                'fuente' => 'Configuración de demostración; sustituir por la definición oficial.',
                'provisional' => true,
            ],
        ]);
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $respuestas  valor de satisfacción y valor de la otra pregunta
     */
    private function crearEncuestaConSatisfaccion(int $idSeccion, array $respuestas, string $respondidaEn = '2026-11-01 12:00:00+00'): void
    {
        $encuesta = DB::table('investigacion_formativa.encuesta')->insertGetId([
            'id_seccion' => $idSeccion,
            'estado' => 'publicada',
            'creada_por' => $this->datos['users']['director']->id,
            'publicada_en' => '2026-10-01 08:00:00+00',
            'version' => 2,
        ], 'id_encuesta');

        $preguntaSatisfaccion = DB::table('investigacion_formativa.encuesta_pregunta')->insertGetId([
            'id_encuesta' => $encuesta,
            'orden' => 1,
            'texto' => self::PREGUNTA_SATISFACCION,
        ], 'id_pregunta');

        $preguntaSecundaria = DB::table('investigacion_formativa.encuesta_pregunta')->insertGetId([
            'id_encuesta' => $encuesta,
            'orden' => 2,
            'texto' => '¿La orientación del docente fue clara?',
        ], 'id_pregunta');

        foreach ($respuestas as $idEstudiante => [$valorSatisfaccion, $valorSecundario]) {
            $respuesta = DB::table('investigacion_formativa.encuesta_respuesta')->insertGetId([
                'id_encuesta' => $encuesta,
                'id_estudiante' => $idEstudiante,
                'comentario' => null,
                'respondida_en' => $respondidaEn,
            ], 'id_respuesta');

            DB::table('investigacion_formativa.encuesta_respuesta_item')->insert([
                [
                    'id_encuesta' => $encuesta,
                    'id_respuesta' => $respuesta,
                    'id_pregunta' => $preguntaSatisfaccion,
                    'valor' => $valorSatisfaccion,
                ],
                [
                    'id_encuesta' => $encuesta,
                    'id_respuesta' => $respuesta,
                    'id_pregunta' => $preguntaSecundaria,
                    'valor' => $valorSecundario,
                ],
            ]);
        }
    }
}
