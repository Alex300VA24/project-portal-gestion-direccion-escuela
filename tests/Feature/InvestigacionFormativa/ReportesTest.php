<?php

namespace Tests\Feature\InvestigacionFormativa;

use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;
use App\Modules\InvestigacionFormativa\Services\DecisionTrabajoService;
use App\Modules\InvestigacionFormativa\Services\ReporteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\InvestigacionFormativa\Concerns\CreaDatosInvestigacion;
use Tests\TestCase;

final class ReportesTest extends TestCase
{
    use CreaDatosInvestigacion;
    use DatabaseTransactions;

    private array $datos;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL.');
        }
        $this->datos = $this->crearDatosInvestigacion();
    }

    public function test_muestra_estados_vacios_sin_notas_inventadas(): void
    {
        $reporte = $this->reporte();
        $this->assertNull($reporte->estado);
        $this->assertNull($reporte->promedio_curso);
        $this->assertSame([], $reporte->grupos_mejor);
        $this->assertSame(0, $reporte->respuestas_count);
        $pagina = $this->actingAs($this->datos['users']['director'])->get($this->ruta())
            ->assertOk()->assertSee('Sin encuesta')->assertSee('No hay grupos registrados.')
            ->assertSee('Todavía no hay respuestas registradas.');
        $this->guardarHtml('vacio', $pagina->getContent());
    }

    public function test_las_notas_parciales_no_se_convierten_en_ceros(): void
    {
        $proyecto = $this->proyecto();
        $this->evaluar($proyecto, 1, [3, 2, 1]);
        $reporte = $this->reporte();
        $this->assertSame('12.00', $reporte->grupos[0]->notas_unidades[1]);
        $this->assertNull($reporte->grupos[0]->notas_unidades[2]);
        $this->assertSame(1, $reporte->entregas_evaluadas);
        $this->assertNull($reporte->grupos[0]->nota_final);
        $this->assertNull($reporte->promedio_curso);
        $this->assertSame(0, $reporte->promedio_denominador);
    }

    public function test_un_proyecto_completo_ocupa_ambos_extremos(): void
    {
        $proyecto = $this->proyecto();
        foreach ([1 => [3, 2, 1], 2 => [4, 3, 1], 3 => [3, 3, 1]] as $unidad => $puntos) {
            $this->evaluar($proyecto, $unidad, $puntos);
        }
        $reporte = $this->reporte();
        $this->assertSame([1 => '12.00', 2 => '16.00', 3 => '14.00'], $reporte->grupos[0]->notas_unidades);
        $this->assertSame('14.00', $reporte->promedio_curso);
        $this->assertSame(1, $reporte->promedio_denominador);
        $this->assertSame($reporte->grupos_mejor, $reporte->grupos_peor);
        $this->actingAs($this->datos['users']['director'])->get($this->ruta())
            ->assertOk()->assertSee('Solo hay un proyecto completo');
    }

    public function test_incluye_todos_los_empates_y_excluye_el_proyecto_incompleto(): void
    {
        $primero = $this->proyecto();
        $segundo = $this->otroProyecto('Segundo');
        $tercero = $this->otroProyecto('Incompleto');
        foreach ([1, 2, 3] as $unidad) {
            $this->evaluar($primero, $unidad, [3, 3, 1]);
            $this->evaluar($segundo, $unidad, [3, 3, 1]);
            if ($unidad < 3) {
                $this->evaluar($tercero, $unidad, [4, 4, 2]);
            }
        }
        $reporte = $this->reporte();
        $this->assertSame(2, $reporte->promedio_denominador);
        $this->assertSame('14.00', $reporte->promedio_curso);
        $this->assertCount(2, $reporte->grupos_mejor);
        $this->assertCount(2, $reporte->grupos_peor);
        $this->assertSame(8, $this->resumen()->totales->evaluaciones);
        $this->assertSame(5, $this->resumen()->totales->grupos);
        $ids = array_column($reporte->grupos_mejor, 'id_grupo');
        $ordenados = $ids;
        sort($ordenados);
        $this->assertSame($ordenados, $ids);
        $pagina = $this->actingAs($this->datos['users']['director'])->get($this->ruta())->assertOk();
        $this->guardarHtml('empates', $pagina->getContent());
    }

    public function test_la_version_nueva_sin_evaluar_no_reutiliza_la_nota_anterior(): void
    {
        $proyecto = $this->proyecto();
        foreach ([1, 2, 3] as $unidad) {
            $this->evaluar($proyecto, $unidad, [4, 4, 2]);
        }
        $entrega = DB::table('investigacion_formativa.entrega')->where('id_proyecto', $proyecto)->first();
        DB::table('investigacion_formativa.entrega')->insert([
            'id_proyecto' => $proyecto, 'id_entregable' => $entrega->id_entregable,
            'version' => 2, 'id_archivo' => $entrega->id_archivo,
        ]);
        $reporte = $this->reporte();
        $this->assertSame(2, $reporte->entregas_evaluadas);
        $this->assertNull($reporte->grupos[0]->notas_unidades[1]);
        $this->assertNull($reporte->grupos[0]->nota_final);
        $this->assertSame([], $reporte->grupos_mejor);
        $this->assertSame(3, DB::table('investigacion_formativa.evaluacion')->count());
    }

    public function test_el_promedio_del_curso_redondea_medios_hacia_arriba(): void
    {
        $primero = $this->proyecto();
        $segundo = $this->otroProyecto('Decimal');
        foreach ([1, 2, 3] as $unidad) {
            $this->evaluar($primero, $unidad, [$unidad === 1 ? '0.01' : '0.00', '0.00', '0.00']);
            $this->evaluar($segundo, $unidad, ['0.01', '0.00', '0.00']);
        }
        $reporte = $this->reporte();
        $this->assertSame('0.01', $reporte->peor_nota);
        $this->assertSame('0.02', $reporte->mejor_nota);
        $this->assertSame('0.02', $reporte->promedio_curso);
    }

    public function test_cuenta_cabeceras_y_calcula_la_media_por_pregunta(): void
    {
        $this->encuestaConRespuestas();
        $reporte = $this->reporte();
        $this->assertSame(3, $reporte->respuestas_count);
        $this->assertSame(6, DB::table('investigacion_formativa.encuesta_respuesta_item')->count());
        $this->assertSame('3.00', $reporte->preguntas[0]->valor_promedio);
        $this->assertSame([1, 0, 1, 0, 1], array_column($reporte->preguntas[0]->distribucion, 'conteo'));
        $this->assertSame('5.00', $reporte->preguntas[1]->valor_promedio);
        $this->assertSame(4, $reporte->roster_activo);
        $this->assertSame(75.0, $reporte->participacion_porcentaje);
        $resumen = $this->resumen();
        $this->assertSame(3, $resumen->totales->respuestas);
        $this->assertSame(3, $resumen->encuesta->respuestas);
        $this->assertSame(4, $resumen->encuesta->roster_activo);
        $this->assertSame(75.0, $resumen->encuesta->participacion_porcentaje);
        $this->assertSame(1, $resumen->encuesta->secciones_con_encuesta);
        $this->assertSame(1, $resumen->encuesta->secciones_con_respuestas);
        $this->assertSame(1, $resumen->encuesta->escala_min);
        $this->assertSame(5, $resumen->encuesta->escala_max);
        $this->assertCount(2, $resumen->encuesta->preguntas);
        $primera = $resumen->encuesta->preguntas[0];
        $this->assertSame(3, $primera->respuestas);
        $this->assertSame('3.00', $primera->valor_promedio);
        $this->assertSame([1, 0, 1, 0, 1], array_column($primera->distribucion, 'conteo'));
        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.index'))
            ->assertOk()
            ->assertSee('Escala 1–5')
            ->assertSee('estadística descriptiva');
    }

    public function test_los_retiros_cambian_el_denominador_sin_limitar_la_tasa(): void
    {
        $this->encuestaConRespuestas();
        DB::table('investigacion_formativa.participante_seccion')->where('id_seccion', 9901)
            ->where('id_estudiante', '<>', 9901)->update(['estado' => 'retirado']);
        $reporte = $this->reporte();
        $this->assertSame(1, $reporte->roster_activo);
        $this->assertSame(300.0, $reporte->participacion_porcentaje);
        $this->actingAs($this->datos['users']['director'])->get($this->ruta())
            ->assertOk()->assertSee('300%')->assertSee('Puede superar el 100% después de retiros');
        DB::table('investigacion_formativa.participante_seccion')->where('id_seccion', 9901)->update(['estado' => 'retirado']);
        $this->assertNull($this->reporte()->participacion_porcentaje);
        $this->assertNull($this->resumen()->encuesta->participacion_porcentaje);
        $this->actingAs($this->datos['users']['director'])->get($this->ruta())
            ->assertOk()
            ->assertSee('No calculable');
    }

    public function test_agrupa_proyectos_por_linea_y_separa_completos_de_pendientes(): void
    {
        DB::table('core.linea_investigacion')->insert([
            'id_linea' => 9803,
            'id_escuela' => 920,
            'codigo' => 'LIN-C',
            'nombre' => 'Línea C',
            'estado' => 'Activo',
        ]);

        $completo = $this->proyecto();
        foreach ([1, 2, 3] as $unidad) {
            $this->evaluar($completo, $unidad, [4, 4, 2]);
        }

        $pendiente = $this->otroProyecto('Proyecto pendiente de línea C', null, 9803);
        $this->evaluar($pendiente, 1, [4, 4, 2]);

        $resumen = $this->resumen();
        $porLinea = [];
        foreach ($resumen->lineas_resumen as $linea) {
            $porLinea[$linea->id_linea] = $linea;
        }

        $this->assertSame(1, $porLinea[9801]->total);
        $this->assertSame(1, $porLinea[9801]->completos);
        $this->assertSame(0, $porLinea[9801]->pendientes);
        $this->assertSame(1, $porLinea[9803]->total);
        $this->assertSame(0, $porLinea[9803]->completos);
        $this->assertSame(1, $porLinea[9803]->pendientes);
        $this->assertSame(2, $resumen->totales->proyectos);
        $this->assertSame(1, $resumen->totales->proyectos_completos);
        $this->assertSame(1, $resumen->totales->proyectos_pendientes);
        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.index'))
            ->assertOk()
            ->assertSee('Proyectos por línea de investigación')
            ->assertSee('Línea A')
            ->assertSee('Línea C')
            ->assertSee('Completos 1')
            ->assertSee('Pendientes 1');
    }

    public function test_los_umbrales_de_nota_son_estrictos_y_superpuestos(): void
    {
        $exacto16 = $this->proyecto();
        $exacto18 = $this->otroProyecto('Exacto 18');
        $mayor18 = $this->otroProyecto('Mayor 18');
        $incompleto = $this->otroProyecto('Incompleto');

        foreach ([1, 2, 3] as $unidad) {
            $this->evaluar($exacto16, $unidad, [4, 3, 1]);
            $this->evaluar($exacto18, $unidad, [4, 4, 1]);
            $this->evaluar($mayor18, $unidad, [4, 4, 2]);

            if ($unidad < 3) {
                $this->evaluar($incompleto, $unidad, [4, 4, 2]);
            }
        }

        $resumen = $this->resumen();
        $this->assertSame(4, $resumen->totales->proyectos);
        $this->assertSame(3, $resumen->totales->proyectos_completos);
        $this->assertSame(1, $resumen->totales->proyectos_pendientes);
        $this->assertSame(2, $resumen->totales->mayores_16);
        $this->assertSame(1, $resumen->totales->mayores_18);
        $this->assertSame(3, $resumen->umbrales->base);
        $this->assertSame(2, $resumen->umbrales->mayores_16);
        $this->assertSame(1, $resumen->umbrales->mayores_18);

        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.index'))
            ->assertOk()
            ->assertSee('Los umbrales se superponen')
            ->assertSee('No son intervalos excluyentes')
            ->assertDontSee('16 a 18')
            ->assertDontSee('18 a 20');
    }

    public function test_filtra_por_curso_y_linea_y_conserva_los_filtros_en_los_enlaces(): void
    {
        DB::table('core.curso')->insert([
            'id_curso' => 9703,
            'codigo' => 'IF-503',
            'nombre' => 'Investigación Formativa III',
            'creditos' => 3,
            'estado' => 'Activo',
        ]);
        DB::table('core.curso_plan')->insert([
            'id_curso_plan' => 9503,
            'id_plan' => 950,
            'id_curso' => 9703,
            'ciclo' => 5,
            'tipo' => 'Obligatorio',
            'id_area' => 960,
            'creditos_requeridos' => 0,
        ]);
        DB::table('core.seccion')->insert([
            'id_seccion' => 9903,
            'id_periodo' => 202602,
            'id_curso_plan' => 9503,
            'codigo' => 'C',
            'capacidad' => 30,
            'id_docente_responsable' => 9801,
            'estado' => 'Activa',
        ]);
        DB::table('core.linea_investigacion')->insert([
            'id_linea' => 9803,
            'id_escuela' => 920,
            'codigo' => 'LIN-C',
            'nombre' => 'Línea C',
            'estado' => 'Activo',
        ]);
        $this->crearTipoProducto();
        $this->otroProyecto('Proyecto de línea C', null, 9803);

        $porCurso = $this->resumen(null, 9703);
        $this->assertSame([9903], array_column($porCurso->secciones, 'id_seccion'));
        $this->assertSame(9703, $porCurso->filtros->curso);
        $this->assertContains(9703, array_column($porCurso->cursos, 'id_curso'));

        $porLinea = $this->resumen(null, null, 9803);
        $this->assertSame([9901], array_column($porLinea->secciones, 'id_seccion'));
        $this->assertSame(9803, $porLinea->filtros->linea);
        $this->assertCount(1, $porLinea->lineas_resumen);
        $this->assertSame(9803, $porLinea->lineas_resumen[0]->id_linea);
        $this->assertSame(1, $porLinea->totales->proyectos);

        $pagina = $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.index', ['curso' => 9701, 'linea' => 9803]))
            ->assertOk();
        $this->assertSame([9901], array_column($pagina->viewData('secciones'), 'id_seccion'));
        $this->assertStringContainsString('curso=9701', $pagina->getContent());
        $this->assertStringContainsString('linea=9803', $pagina->getContent());
    }

    public function test_rechaza_curso_y_linea_fuera_del_alcance(): void
    {
        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.index', ['curso' => 99999]))
            ->assertForbidden();

        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.index', ['linea' => 9802]))
            ->assertForbidden();
    }

    public function test_la_aprobacion_no_se_deduce_del_estado_del_titulo(): void
    {
        $proyecto = $this->proyecto();
        foreach ([1, 2, 3] as $unidad) {
            $this->evaluar($proyecto, $unidad, [4, 4, 2]);
        }

        $this->assertSame(1, $this->resumen()->totales->proyectos_completos);
        $this->assertSame(0, $this->resumen()->totales->aprobados);
        $this->assertSame(0, $this->resumen()->totales->destacados);

        $actor = app(AccesoInvestigacionService::class)->resolver($this->datos['users']['docente_a']);
        $decisiones = app(DecisionTrabajoService::class);
        $decisiones->aprobar($actor, $proyecto, 0);
        $decisiones->seleccionar($actor, $proyecto, 0);

        $resumen = $this->resumen();
        $this->assertSame(1, $resumen->totales->aprobados);
        $this->assertSame(1, $resumen->totales->destacados);
        $this->assertSame(1, $resumen->decisiones->aprobados);
        $this->assertSame(1, $resumen->decisiones->destacados);

        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.index'))
            ->assertOk()
            ->assertSee('Aprobación y trabajo destacado')
            ->assertSee('Trabajos aprobados')
            ->assertSee('La aprobación es una decisión explícita del docente');
    }

    public function test_los_resultados_por_seccion_filtran_por_linea_y_lo_avisan(): void
    {
        DB::table('core.linea_investigacion')->insert([
            'id_linea' => 9803,
            'id_escuela' => 920,
            'codigo' => 'LIN-C',
            'nombre' => 'Línea C',
            'estado' => 'Activo',
        ]);

        $proyectoA = $this->proyecto();
        $proyectoB = $this->otroProyecto('Proyecto de línea C', null, 9803);

        foreach ([1, 2, 3] as $unidad) {
            $this->evaluar($proyectoA, $unidad, [4, 4, 2]);
            $this->evaluar($proyectoB, $unidad, [4, 3, 1]);
        }

        $docente = app(AccesoInvestigacionService::class)->resolver($this->datos['users']['docente_a']);
        $decisiones = app(DecisionTrabajoService::class);
        $decisiones->aprobar($docente, $proyectoB, 0);
        $decisiones->seleccionar($docente, $proyectoB, 0);

        $director = app(AccesoInvestigacionService::class)->resolver($this->datos['users']['director']);
        $servicio = app(ReporteService::class);

        $lineaC = $servicio->porSeccion($director, 9901, 1, 10, 9803);
        $this->assertCount(1, $lineaC->grupos);
        $this->assertSame($proyectoB, $lineaC->grupos[0]->id_proyecto);
        $this->assertSame($proyectoB, $lineaC->seleccion_destacada->id_proyecto);
        $this->assertSame(1, $lineaC->aprobados_count);
        $this->assertSame(1, $lineaC->destacados_count);

        $lineaA = $servicio->porSeccion($director, 9901, 1, 10, 9801);
        $this->assertCount(1, $lineaA->grupos);
        $this->assertSame($proyectoA, $lineaA->grupos[0]->id_proyecto);
        $this->assertNull($lineaA->seleccion_destacada);
        $this->assertSame(0, $lineaA->aprobados_count);
        $this->assertSame(0, $lineaA->destacados_count);

        $this->actingAs($this->datos['users']['director'])
            ->get($this->ruta().'?linea=9803')
            ->assertOk()
            ->assertSee('filtradas por línea de investigación')
            ->assertSee('Proyecto de línea C');
    }

    public function test_pagina_comentarios_sin_identidades_y_conserva_filtros_y_tamano(): void
    {
        $this->encuestaConRespuestas();
        $pagina = $this->actingAs($this->datos['users']['director'])
            ->get($this->ruta().'?comentarios=2&por_pagina=1&escuela=920&periodo=2026-II')
            ->assertOk()->assertSee('Comentario dos')->assertDontSee('Comentario tres')
            ->assertDontSee('<script>primero</script>', false)
            ->assertDontSee('Alumna Prueba')->assertDontSee('Alumno Prueba');
        $this->assertSame(2, $pagina->viewData('reporte')->comentarios->currentPage());
        $this->assertStringContainsString('comentarios=3', $pagina->getContent());
        $this->assertStringContainsString('por_pagina=1', $pagina->getContent());
        $this->assertStringContainsString('periodo=2026-II', $pagina->getContent());
        $this->assertStringContainsString('escuela=920', $pagina->getContent());
        $primera = $this->get($this->ruta().'?por_pagina=1')->assertOk()
            ->assertSee('&lt;script&gt;primero&lt;/script&gt;', false);
        $this->guardarHtml('comentarios', $pagina->getContent());
        $this->guardarHtml('escapado', $primera->getContent());
        $this->assertSame(['comentario', 'respondida_en'], array_keys((array) $this->reporte()->comentarios->items()[0]));
    }

    public function test_el_historial_usa_periodos_reales_e_incluye_secciones_cerradas(): void
    {
        DB::table('core.periodo_academico')->insert([
            'id_periodo' => 202501, 'codigo' => '2025-I', 'anio' => 2025, 'tipo' => 'I',
            'fecha_inicio' => '2025-03-01', 'fecha_fin' => '2025-07-01', 'numero_semanas' => 17, 'estado' => 'Cerrado',
        ]);
        $seccion = (array) DB::table('core.seccion')->where('id_seccion', 9901)->first();
        $seccion['id_seccion'] = 9991;
        $seccion['id_periodo'] = 202501;
        $seccion['codigo'] = 'HIST-IF';
        $seccion['estado'] = 'Cerrada';
        DB::table('core.seccion')->insert($seccion);
        $resumen = $this->resumen('2025-I');
        $this->assertSame(1, $resumen->totales->secciones);
        $this->assertSame('HIST-IF', $resumen->secciones[0]->seccion);
        $this->assertContains('2025-I', $resumen->periodos);
        $pagina = $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.index', ['periodo' => '2025-I']))
            ->assertOk()->assertSee('HIST-IF')->assertDontSee('IF-501');
        $this->guardarHtml('historial', $pagina->getContent());
        $this->get($this->ruta(9991))->assertOk()->assertSee('2025-I')->assertSee('Cerrada');
        $this->assertNotContains(9991, array_column($this->resumen('2026-II')->secciones, 'id_seccion'));
    }

    public function test_conserva_el_alcance_de_escuela_de_los_reportes(): void
    {
        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.index', ['escuela' => 921]))->assertForbidden();
        $this->get($this->ruta(9902))->assertForbidden();
        $this->assertNotContains(9902, array_column($this->resumen()->secciones, 'id_seccion'));
    }

    public function test_las_consultas_del_resumen_no_crecen_por_seccion(): void
    {
        $proyecto = $this->proyecto();
        $this->evaluar($proyecto, 1, [3, 2, 1]);
        $contar = function (): int {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->resumen();
            $cantidad = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $cantidad;
        };
        $contar();
        $antes = $contar();
        $seccion = (array) DB::table('core.seccion')->where('id_seccion', 9901)->first();
        foreach (range(1, 8) as $indice) {
            $seccion['id_seccion'] = 10000 + $indice;
            $seccion['codigo'] = 'BATCH-'.$indice;
            DB::table('core.seccion')->insert($seccion);
        }
        $this->assertSame($antes, $contar());
        $this->assertSame(9, $this->resumen()->totales->secciones);
    }

    private function reporte(): object
    {
        return app(ReporteService::class)->porSeccion(app(AccesoInvestigacionService::class)->resolver($this->datos['users']['director']), 9901);
    }

    private function resumen(?string $periodo = null, ?int $idCurso = null, ?int $idLinea = null): object
    {
        return app(ReporteService::class)->resumenDirector(
            app(AccesoInvestigacionService::class)->resolver($this->datos['users']['director']),
            null,
            $periodo,
            $idCurso,
            $idLinea,
        );
    }

    private function ruta(int $seccion = 9901): string
    {
        return route('investigacion-formativa.director.resultados', $seccion);
    }

    private function proyecto(): int
    {
        $this->crearGruposInvestigacion();
        $this->crearTipoProducto();
        $this->configurarSeccionCompleta(9901);
        $rubrica = DB::table('investigacion_formativa.seccion_configuracion')->where('id_seccion', 9901)->value('id_rubrica');
        foreach ([2 => 4, 3 => 2] as $orden => $maximo) {
            $idCriterio = DB::table('investigacion_formativa.criterio')->insertGetId([
                'id_rubrica' => $rubrica, 'descripcion' => 'Criterio '.$orden, 'orden' => $orden, 'puntaje_maximo' => $maximo,
            ], 'id_criterio');

            foreach ([1, 2, 3, 4] as $nivel) {
                DB::table('investigacion_formativa.criterio_nivel')->insert([
                    'id_criterio' => $idCriterio,
                    'nivel' => $nivel,
                    'descripcion' => 'Nivel '.$nivel,
                    'puntaje' => min($nivel, $maximo),
                ]);
            }
        }

        return $this->otroProyecto('Primero', 9951);
    }

    private function otroProyecto(string $nombre, ?int $grupo = null, int $idLinea = 9801): int
    {
        $grupo ??= DB::table('investigacion_formativa.grupo')->insertGetId(['id_seccion' => 9901, 'nombre' => $nombre], 'id_grupo');

        return DB::table('investigacion_formativa.proyecto')->insertGetId([
            'id_grupo' => $grupo, 'id_linea' => $idLinea, 'id_tipo_producto' => 9981,
            'titulo' => $nombre, 'estado' => 'Aceptado', 'version' => 1,
        ], 'id_proyecto');
    }

    private function evaluar(int $proyecto, int $unidad, array $puntos): void
    {
        // Fixture de lectura: no usa el gateway de escritura de producción.
        $archivo = DB::table('comun.archivo')->insertGetId([
            'id_modulo' => DB::table('seguridad.modulo_sistema')->where('codigo', 'investigacion_formativa')->value('id_modulo'),
            'nombre_original' => 'fixture.pdf', 'ruta_almacenamiento' => 'investigacion-formativa/test/fixture-'.$proyecto.'-'.$unidad.'.pdf',
            'tipo_mime' => 'application/pdf', 'tamano_bytes' => 10, 'hash_sha256' => str_repeat('b', 64), 'subido_por' => 8802,
        ], 'id_archivo');
        $entregable = DB::table('investigacion_formativa.entregable')->where('id_seccion', 9901)->where('orden', $unidad)->first();
        $entrega = DB::table('investigacion_formativa.entrega')->insertGetId([
            'id_proyecto' => $proyecto, 'id_entregable' => $entregable->id_entregable, 'version' => 1, 'id_archivo' => $archivo,
        ], 'id_entrega');
        $evaluacion = DB::table('investigacion_formativa.evaluacion')->insertGetId([
            'id_entrega' => $entrega, 'id_docente_evaluador' => 9801, 'version' => 1, 'sin_observaciones' => true,
        ], 'id_evaluacion');
        foreach (DB::table('investigacion_formativa.criterio')->where('id_rubrica', $entregable->id_rubrica)->orderBy('orden')->get() as $indice => $criterio) {
            DB::table('investigacion_formativa.evaluacion_criterio')->insert([
                'id_evaluacion' => $evaluacion, 'id_criterio' => $criterio->id_criterio, 'puntaje' => $puntos[$indice],
            ]);
        }
    }

    private function encuestaConRespuestas(): void
    {
        DB::table('investigacion_formativa.participante_seccion')->where('id_seccion', 9901)->update(['estado' => 'activo']);
        $encuesta = DB::table('investigacion_formativa.encuesta')->insertGetId([
            'id_seccion' => 9901, 'estado' => 'cerrada', 'creada_por' => $this->datos['users']['director']->id,
            'publicada_en' => now('UTC'), 'cerrada_en' => now('UTC'), 'version' => 3,
        ], 'id_encuesta');
        $preguntas = [];
        foreach ([1, 2] as $orden) {
            $preguntas[] = DB::table('investigacion_formativa.encuesta_pregunta')->insertGetId([
                'id_encuesta' => $encuesta, 'orden' => $orden, 'texto' => 'Pregunta '.$orden,
            ], 'id_pregunta');
        }
        foreach ([9901, 9902, 9905] as $indice => $estudiante) {
            $respuesta = DB::table('investigacion_formativa.encuesta_respuesta')->insertGetId([
                'id_encuesta' => $encuesta, 'id_estudiante' => $estudiante,
                'comentario' => ['<script>primero</script>', 'Comentario dos', 'Comentario tres'][$indice],
                'respondida_en' => '2026-11-01 12:00:0'.$indice.'+00',
            ], 'id_respuesta');
            foreach ($preguntas as $posicion => $pregunta) {
                DB::table('investigacion_formativa.encuesta_respuesta_item')->insert([
                    'id_encuesta' => $encuesta, 'id_respuesta' => $respuesta, 'id_pregunta' => $pregunta,
                    'valor' => $posicion === 0 ? [1, 3, 5][$indice] : 5,
                ]);
            }
        }
    }

    private function guardarHtml(string $nombre, string $html): void
    {
        if ($directorio = getenv('P08_HTML_DIR')) {
            file_put_contents($directorio.'/'.$nombre.'.html', $html);
        }
    }
}
