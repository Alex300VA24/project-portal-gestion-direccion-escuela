<?php

namespace Tests\Feature\InvestigacionFormativa;

use App\Modules\InvestigacionFormativa\Models\Entrega;
use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;
use App\Modules\InvestigacionFormativa\Services\DecisionTrabajoService;
use App\Modules\InvestigacionFormativa\Services\EvaluacionService;
use App\Modules\InvestigacionFormativa\Services\GrupoService;
use App\Modules\InvestigacionFormativa\Services\LogroCompetenciasService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\InvestigacionFormativa\Concerns\CreaDatosIndicador;
use Tests\Feature\InvestigacionFormativa\Concerns\CreaDatosInvestigacion;
use Tests\TestCase;

final class LogroCompetenciasTest extends TestCase
{
    use CreaDatosIndicador;
    use CreaDatosInvestigacion;
    use DatabaseTransactions;

    private array $datos;

    private array $grupos;

    private int $idCriterioPrimero;

    private int $idCriterioSegundo;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL.');
        }

        $this->datos = $this->crearDatosInvestigacion();
        $this->configurarSeccionCompleta(9901);
        $this->crearTipoProducto();
        $this->crearCriterioSecundario();
        $this->grupos = $this->crearGruposPorServicio();
    }

    public function test_calcula_el_logro_solo_con_puntajes_de_criterios_y_muestra_la_cobertura(): void
    {
        $this->configurarLogroIndicador();
        $this->registrarMapeos();
        $this->evaluarProyecto($this->grupos['alumno_a']['id_proyecto'], 1, [4, 4]);
        $this->evaluarProyecto($this->grupos['multi']['id_proyecto'], 1, [3, 2]);

        $resultado = $this->logro()->porSeccion(9901);

        $this->assertSame(50.0, $resultado->valor);
        $this->assertSame(1, $resultado->numerador);
        $this->assertSame(2, $resultado->denominador);
        $this->assertSame(2, $resultado->esperados);
        $this->assertSame(100.0, $resultado->cobertura);
        $this->assertSame(82.5, $resultado->puntaje_promedio);
        $this->assertSame('Calculado', $resultado->estado);
        $this->assertTrue($resultado->provisional);
        $this->assertStringContainsString('nota final', (string) $resultado->regla);
        $this->assertStringContainsString('decisiones docentes no participan', (string) $resultado->regla);
        $this->assertCount(1, $resultado->competencias);

        $competencia = $resultado->competencias[0];
        $this->assertSame('C1', $competencia->codigo);
        $this->assertSame(50.0, $competencia->valor);
        $this->assertSame(100.0, $competencia->cobertura);
        $this->assertSame(1, $competencia->bandas[2]->conteo);
        $this->assertSame(1, $competencia->bandas[1]->conteo);
    }

    public function test_los_pesos_y_las_bandas_son_reemplazables(): void
    {
        $this->configurarLogroIndicador();
        $this->registrarMapeos();
        $this->evaluarProyecto($this->grupos['multi']['id_proyecto'], 1, [3, 2]);

        DB::table('investigacion_formativa.indicador_competencia_criterio')
            ->where('id_competencia', 9861)
            ->where('id_criterio', $this->idCriterioPrimero)
            ->update(['peso' => 0.2]);
        DB::table('investigacion_formativa.indicador_competencia_criterio')
            ->where('id_competencia', 9861)
            ->where('id_criterio', $this->idCriterioSegundo)
            ->update(['peso' => 0.8]);
        DB::table('investigacion_formativa.configuracion_indicador')
            ->where('indicador', '3.3')
            ->where('parametro', 'bandas_logro')
            ->update([
                'valor' => json_encode([
                    ['etiqueta' => 'Insuficiente', 'minimo' => 0, 'maximo' => 50, 'alcanzado' => false],
                    ['etiqueta' => 'Suficiente', 'minimo' => 50, 'maximo' => 100, 'alcanzado' => true],
                ], JSON_THROW_ON_ERROR),
            ]);

        $resultado = $this->logro()->porSeccion(9901);

        $this->assertSame(55.0, $resultado->puntaje_promedio);
        $this->assertSame(100.0, $resultado->valor);
        $this->assertSame(1, $resultado->bandas[1]->conteo);
    }

    public function test_exige_cobertura_minima_y_no_inventa_un_logro(): void
    {
        $this->configurarLogroIndicador();
        $this->registrarMapeos();
        $this->evaluarProyecto($this->grupos['alumno_a']['id_proyecto'], 1, [4, 4]);
        DB::table('investigacion_formativa.evaluacion_criterio')
            ->where('id_criterio', $this->idCriterioSegundo)
            ->delete();

        $resultado = $this->logro()->porSeccion(9901);

        $this->assertNull($resultado->valor);
        $this->assertSame(0, $resultado->numerador);
        $this->assertSame(0, $resultado->denominador);
        $this->assertSame(2, $resultado->esperados);
        $this->assertSame(0.0, $resultado->cobertura);
        $this->assertSame('Sin datos', $resultado->estado);
        $this->assertStringContainsString('cobertura suficiente', (string) $resultado->motivo);
    }

    public function test_no_infiere_logro_desde_titulo_aceptado_aprobacion_o_seleccion(): void
    {
        $this->configurarLogroIndicador();
        $this->registrarMapeos();
        $actorDocente = $this->acceso()->resolver($this->datos['users']['docente_a']);
        $this->grupos()->revisarTitulo(
            $actorDocente,
            $this->grupos['alumno_a']['id_proyecto'],
            'Aceptado',
            null,
            1,
        );

        foreach ([1, 2, 3] as $unidad) {
            $this->evaluarProyecto($this->grupos['alumno_a']['id_proyecto'], $unidad, [4, 4]);
        }
        $this->evaluarProyecto($this->grupos['multi']['id_proyecto'], 1, [4, 4]);

        $antes = $this->logro()->porSeccion(9901);
        $decisiones = app(DecisionTrabajoService::class);
        $decisiones->aprobar($actorDocente, $this->grupos['alumno_a']['id_proyecto'], 0);
        $decisiones->seleccionar($actorDocente, $this->grupos['alumno_a']['id_proyecto'], 0);
        $despues = $this->logro()->porSeccion(9901);

        $this->assertSame(100.0, $antes->valor);
        $this->assertSame($antes->valor, $despues->valor);
        $this->assertSame($antes->numerador, $despues->numerador);
        $this->assertSame($antes->denominador, $despues->denominador);
        $this->assertSame(2, $despues->denominador);
        $this->assertDatabaseHas('investigacion_formativa.decision_trabajo', [
            'id_proyecto' => $this->grupos['alumno_a']['id_proyecto'],
            'tipo' => 'aprobacion',
        ]);
        $this->assertDatabaseHas('investigacion_formativa.decision_trabajo', [
            'id_proyecto' => $this->grupos['alumno_a']['id_proyecto'],
            'tipo' => 'seleccion',
        ]);
    }

    public function test_sin_configuracion_o_mapeo_no_inventa_un_valor(): void
    {
        $sinConfiguracion = $this->logro()->porSeccion(9901);
        $this->assertNull($sinConfiguracion->valor);
        $this->assertStringContainsString('Falta la configuración', (string) $sinConfiguracion->motivo);

        $this->configurarLogroIndicador();
        $sinMapeo = $this->logro()->porSeccion(9901);
        $this->assertNull($sinMapeo->valor);
        $this->assertStringContainsString('No existen mapeos', (string) $sinMapeo->motivo);
    }

    public function test_rechaza_un_mapeo_con_un_criterio_de_otra_rubrica(): void
    {
        $this->configurarLogroIndicador();
        DB::table('investigacion_formativa.rubrica')->insert([
            'id_rubrica' => 99991,
            'nombre' => 'Rúbrica ajena',
            'id_docente_autor' => 9801,
        ]);
        $idCriterioAjeno = DB::table('investigacion_formativa.criterio')->insertGetId([
            'id_rubrica' => 99991,
            'descripcion' => 'Criterio ajeno',
            'puntaje_maximo' => 4,
            'orden' => 1,
        ], 'id_criterio');
        $this->registrarMapeoIndicador(9861, $idCriterioAjeno, 1);

        $resultado = $this->logro()->porSeccion(9901);

        $this->assertNull($resultado->valor);
        $this->assertCount(1, $resultado->competencias);
        $this->assertFalse($resultado->competencias[0]->calculable);
        $this->assertStringContainsString('no pertenece a la rúbrica vigente', (string) $resultado->competencias[0]->motivo);
    }

    private function crearCriterioSecundario(): void
    {
        $idRubrica = DB::table('investigacion_formativa.seccion_configuracion')
            ->where('id_seccion', 9901)
            ->value('id_rubrica');
        $this->idCriterioPrimero = (int) DB::table('investigacion_formativa.criterio')
            ->where('id_rubrica', $idRubrica)
            ->orderBy('orden')
            ->value('id_criterio');
        $this->idCriterioSegundo = DB::table('investigacion_formativa.criterio')->insertGetId([
            'id_rubrica' => $idRubrica,
            'descripcion' => 'Desarrollo metodológico',
            'puntaje_maximo' => 4,
            'orden' => 2,
        ], 'id_criterio');

        foreach ([1, 2, 3, 4] as $nivel) {
            DB::table('investigacion_formativa.criterio_nivel')->insert([
                'id_criterio' => $this->idCriterioSegundo,
                'nivel' => $nivel,
                'descripcion' => 'Nivel '.$nivel,
                'puntaje' => $nivel,
            ]);
        }
    }

    /**
     * @return array<string, array{id_grupo: int, id_proyecto: int}>
     */
    private function crearGruposPorServicio(): array
    {
        $servicio = app(GrupoService::class);
        $alumnoA = $servicio->crear(
            $this->acceso()->resolver($this->datos['users']['alumno_a']),
            9901,
            [
                'nombre' => 'Grupo de alcance A',
                'titulo' => 'Proyecto con criterio suficiente',
                'integrante_ids' => [9901],
                'id_linea' => 9801,
                'id_tipo_producto' => 9981,
            ],
        );
        $multi = $servicio->crear(
            $this->acceso()->resolver($this->datos['users']['multi']),
            9901,
            [
                'nombre' => 'Grupo de alcance B',
                'titulo' => 'Proyecto con evidencia parcial',
                'integrante_ids' => [9905],
                'id_linea' => 9801,
                'id_tipo_producto' => 9981,
            ],
        );

        return [
            'alumno_a' => [
                'id_grupo' => (int) $alumnoA->id_grupo,
                'id_proyecto' => (int) $alumnoA->id_proyecto,
            ],
            'multi' => [
                'id_grupo' => (int) $multi->id_grupo,
                'id_proyecto' => (int) $multi->id_proyecto,
            ],
        ];
    }

    private function registrarMapeos(): void
    {
        $this->registrarMapeoIndicador(9861, $this->idCriterioPrimero, 0.6);
        $this->registrarMapeoIndicador(9861, $this->idCriterioSegundo, 0.4);
    }

    /**
     * @param  list<int>  $niveles
     */
    private function evaluarProyecto(int $idProyecto, int $unidad, array $niveles): void
    {
        $entregable = DB::table('investigacion_formativa.entregable')
            ->where('id_seccion', 9901)
            ->where('orden', $unidad)
            ->first();
        $nombreArchivo = 'evidencia-criterios-p'.$idProyecto.'-u'.$unidad.'.pdf';
        $idArchivo = DB::table('comun.archivo')->insertGetId([
            'id_modulo' => DB::table('seguridad.modulo_sistema')
                ->where('codigo', 'investigacion_formativa')
                ->value('id_modulo'),
            'nombre_original' => $nombreArchivo,
            'ruta_almacenamiento' => 'investigacion-formativa/test/'.$nombreArchivo,
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 0,
            'hash_sha256' => null,
            'subido_por' => 8802,
        ], 'id_archivo');
        $idEntrega = DB::table('investigacion_formativa.entrega')->insertGetId([
            'id_proyecto' => $idProyecto,
            'id_entregable' => $entregable->id_entregable,
            'version' => 1,
            'id_archivo' => $idArchivo,
        ], 'id_entrega');
        $criterios = [
            ['id_criterio' => $this->idCriterioPrimero, 'nivel' => $niveles[0]],
            ['id_criterio' => $this->idCriterioSegundo, 'nivel' => $niveles[1]],
        ];

        $entrega = new Entrega;
        $entrega->id_entrega = $idEntrega;

        app(EvaluacionService::class)->guardar(
            $this->acceso()->resolver($this->datos['users']['docente_a']),
            $entrega,
            $criterios,
            'Evaluación de criterios para el indicador 3.3.',
            true,
            [],
            0,
        );
    }

    private function logro(): LogroCompetenciasService
    {
        return app(LogroCompetenciasService::class);
    }

    private function acceso(): AccesoInvestigacionService
    {
        return app(AccesoInvestigacionService::class);
    }

    private function grupos(): GrupoService
    {
        return app(GrupoService::class);
    }
}
