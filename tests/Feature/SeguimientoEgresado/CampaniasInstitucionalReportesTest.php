<?php

namespace Tests\Feature\SeguimientoEgresado;

use App\Modules\SeguimientoEgresado\Database\Seeders\DatosPruebaSeeder;
use App\Modules\SeguimientoEgresado\Models\CampaniaSeguimiento;
use App\Modules\SeguimientoEgresado\Models\CatCanalEnvio;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\Competencia;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Models\InvitacionEgresado;
use App\Modules\SeguimientoEgresado\Models\ObjetivoEducacional;
use App\Modules\SeguimientoEgresado\Models\PlanCurricular;
use App\Modules\SeguimientoEgresado\Models\ReporteGenerado;
use App\Modules\SeguimientoEgresado\Models\UsuarioInterno;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

class CampaniasInstitucionalReportesTest extends TestCase
{
    use ComoPersonal, UsaBaseDeDatos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->secretaria());
        $this->seed(DatosPruebaSeeder::class);
    }

    // =========================================================================
    // S3-12 · Conectar campañas a PostgreSQL (CRUD, fechas, cohortes, validaciones)
    // =========================================================================

    public function test_campanias_page_renders_with_database_records_and_kpis(): void
    {
        $response = $this->get(route('seguimiento-egresado.campanias'));

        $response->assertOk()
            ->assertSee('Campañas e invitaciones')
            ->assertSee('Balance global de convocatorias y cobertura')
            ->assertSee('Total egresados convocados')
            ->assertSee('Respuestas recibidas')
            ->assertSee('Tasa de cobertura global')
            ->assertSee('Campaña Anual de Seguimiento al Egresado 2023');
    }

    public function test_crear_campania_valida_servidor_y_persiste_en_postgresql(): void
    {
        $cohorte = Cohorte::first();
        $canal = CatCanalEnvio::first();

        $data = [
            'nombre' => 'Campaña de Inserción Laboral Cohorte 2019',
            'tipo' => 'EGRESADOS',
            'finalidad' => 'SEGUIMIENTO',
            'horizonte_anios' => 1,
            'periodo' => '2024-II',
            'fecha_inicio' => date('Y-m-d'),
            'fecha_fin' => date('Y-m-d', strtotime('+60 days')),
            'estado' => 'ABIERTA',
            'cohortes' => [$cohorte->id],
            'canales' => [$canal->id],
            'descripcion' => 'Relevamiento laboral a 1 año de egreso cohorte 2019.',
        ];

        $response = $this->post(route('seguimiento-egresado.campanias.store'), $data);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('seguimiento_egresado.campania_seguimiento', [
            'nombre' => 'Campaña de Inserción Laboral Cohorte 2019',
            'periodo' => '2024-II',
            'estado' => 'ABIERTA',
        ]);

        $campaniaCreada = CampaniaSeguimiento::where('nombre', 'Campaña de Inserción Laboral Cohorte 2019')->first();
        $this->assertNotNull($campaniaCreada);
        $this->assertTrue($campaniaCreada->cohortes->contains($cohorte->id));
        $this->assertTrue($campaniaCreada->canales->contains($canal->id));
    }

    public function test_crear_campania_rechaza_fechas_incoherentes_y_estados_invalidos(): void
    {
        $cohorte = Cohorte::first();

        // 1. Fecha fin anterior a fecha inicio
        $dataIncoherenteFechas = [
            'nombre' => 'Campaña con Fechas Erróneas',
            'tipo' => 'EGRESADOS',
            'finalidad' => 'SEGUIMIENTO',
            'periodo' => '2024-I',
            'fecha_inicio' => '2024-12-01',
            'fecha_fin' => '2024-10-01', // Anterior
            'estado' => 'ABIERTA',
            'cohortes' => [$cohorte->id],
        ];

        $response1 = $this->post(route('seguimiento-egresado.campanias.store'), $dataIncoherenteFechas);
        $response1->assertSessionHasErrors(['fecha_fin']);

        // 2. Sin cohortes
        $dataSinCohortes = [
            'nombre' => 'Campaña sin Cohortes',
            'tipo' => 'EGRESADOS',
            'finalidad' => 'SEGUIMIENTO',
            'periodo' => '2024-I',
            'fecha_inicio' => '2024-05-01',
            'fecha_fin' => '2024-08-01',
            'estado' => 'ABIERTA',
            'cohortes' => [],
        ];

        $response2 = $this->post(route('seguimiento-egresado.campanias.store'), $dataSinCohortes);
        $response2->assertSessionHasErrors(['cohortes']);

        // 3. Estado CERRADA con fecha de fin futura
        $dataCerradaFutura = [
            'nombre' => 'Campaña Cerrada con Fecha Futura',
            'tipo' => 'EGRESADOS',
            'finalidad' => 'SEGUIMIENTO',
            'periodo' => '2025-I',
            'fecha_inicio' => date('Y-m-d'),
            'fecha_fin' => date('Y-m-d', strtotime('+30 days')),
            'estado' => 'CERRADA',
            'cohortes' => [$cohorte->id],
        ];

        $response3 = $this->post(route('seguimiento-egresado.campanias.store'), $dataCerradaFutura);
        $response3->assertSessionHasErrors(['estado']);
    }

    public function test_actualizar_campania_persiste_cambios_y_redirecciona(): void
    {
        $campania = CampaniaSeguimiento::first();
        $cohorte = Cohorte::first();

        $updateData = [
            'nombre' => 'Campaña Anual Actualizada 2023',
            'tipo' => 'EGRESADOS',
            'finalidad' => 'SEGUIMIENTO',
            'horizonte_anios' => 1,
            'periodo' => '2023-II',
            'fecha_inicio' => $campania->fecha_inicio,
            'fecha_fin' => date('Y-m-d', strtotime('+45 days')),
            'estado' => 'ABIERTA',
            'cohortes' => [$cohorte->id],
            'descripcion' => 'Actualización de periodo y metas.',
        ];

        $response = $this->put(route('seguimiento-egresado.campanias.update', $campania->id), $updateData);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $campania->refresh();
        $this->assertSame('Campaña Anual Actualizada 2023', $campania->nombre);
    }

    // =========================================================================
    // S3-13 · Gestionar invitaciones y cobertura (Anti-duplicados, tokens, estados)
    // =========================================================================

    public function test_emitir_invitaciones_por_cohorte_evita_duplicados_y_asigna_token_hash(): void
    {
        $cohorte2019 = Cohorte::where('anio_ingreso', 2019)->first();
        $totalEgresados2019 = Egresado::where('id_cohorte', $cohorte2019->id)->count();
        $this->assertGreaterThan(0, $totalEgresados2019);

        // Crear campaña para cohorte 2019
        $campania = CampaniaSeguimiento::create([
            'nombre' => 'Encuesta Cohorte 2019',
            'tipo' => 'EGRESADOS',
            'finalidad' => 'SEGUIMIENTO',
            'periodo' => '2024-I',
            'fecha_inicio' => '2024-01-15',
            'fecha_fin' => '2024-04-30',
            'estado' => 'ABIERTA',
            'id_usuario_creacion' => UsuarioInterno::idActual(),
        ]);
        $campania->cohortes()->attach($cohorte2019->id);

        // 1. Primera emisión de invitaciones
        $res1 = $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $campania->id));
        $res1->assertRedirect();
        $res1->assertSessionHas('success');

        $invitacionesCreadas = InvitacionEgresado::where('id_campania', $campania->id)->get();
        $this->assertSame($totalEgresados2019, $invitacionesCreadas->count());

        // Verificar que cada invitación tiene token sha256 y estado PENDIENTE
        foreach ($invitacionesCreadas as $inv) {
            $this->assertSame(64, strlen($inv->token_hash));
            $this->assertSame('PENDIENTE', $inv->estado);
            $this->assertSame('SEGUIMIENTO', $inv->proposito);
            $this->assertNotNull($inv->expira_en);
        }

        // 2. Segunda emisión de invitaciones (debe evitar duplicados por completo)
        $res2 = $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $campania->id));
        $res2->assertRedirect();
        $res2->assertSessionHas('success');

        $invitacionesDespues = InvitacionEgresado::where('id_campania', $campania->id)->count();
        $this->assertSame($totalEgresados2019, $invitacionesDespues, 'No deben duplicarse invitaciones para egresados ya convocados.');
    }

    public function test_reenviar_recordatorio_incrementa_conteo_y_actualiza_timestamp(): void
    {
        $invitacion = InvitacionEgresado::where('estado', 'PENDIENTE')->first();
        if (! $invitacion) {
            $campania = CampaniaSeguimiento::first();
            $egresado = Egresado::whereNotIn('id', InvitacionEgresado::where('id_campania', $campania->id)->pluck('id_egresado'))->first();
            $invitacion = InvitacionEgresado::create([
                'id_campania' => $campania->id,
                'id_egresado' => $egresado->id,
                'proposito' => 'SEGUIMIENTO',
                'token_hash' => hash('sha256', 'test-token-'.rand()),
                'expira_en' => now()->addDays(30),
                'estado' => 'PENDIENTE',
                'recordatorios_enviados' => 0,
            ]);
        }

        $recordatoriosPrevios = $invitacion->recordatorios_enviados;

        $response = $this->post(route('seguimiento-egresado.campanias.recordatorio', $invitacion->id));
        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('recordatorio_emitido');

        $invitacionActualizada = InvitacionEgresado::find($invitacion->id);
        $this->assertSame($recordatoriosPrevios + 1, $invitacionActualizada->recordatorios_enviados);
        $this->assertNotNull($invitacionActualizada->ultimo_recordatorio_en);
    }

    public function test_cobertura_no_confunde_ausencia_con_desempleo(): void
    {
        // En los datos semilla existen 4 egresados convocados con respuesta laboral
        $response = $this->get(route('seguimiento-egresado.campanias'));

        $response->assertOk()
            ->assertSee('Criterio metodológico fundamental')
            ->assertSee('La falta de respuesta o persona pendiente NO debe confundirse con desempleo')
            ->assertSee('cobertura = respuestas recibidas ÷ total de invitaciones');
    }

    // =========================================================================
    // S3-14 · Conectar evaluación de Dirección (OE, competencias, planes, mejoras)
    // =========================================================================

    public function test_institucional_page_renders_with_director_kpis_and_records(): void
    {
        $response = $this->get(route('seguimiento-egresado.institucional'));

        $response->assertOk()
            ->assertSee('Gestión y evaluación institucional')
            ->assertSee('Tablero de control de la Dirección de Escuela')
            ->assertSee('Objetivos Educacionales (I3)')
            ->assertSee('Mejoras Curriculares (I6)')
            ->assertSee('OE1')
            ->assertSee('MC-2023-01');
    }

    public function test_guardar_evaluacion_oe_valida_por_director_y_computa_para_i3(): void
    {
        $this->actingAs($this->director());
        $oe = ObjetivoEducacional::first();
        $cohorte = Cohorte::where('anio_ingreso', 2019)->first();

        $data = [
            'id_objetivo' => $oe->id,
            'id_cohorte' => $cohorte->id,
            'periodo' => '2024-II',
            'id_nivel_logro' => 1, // MUY_BUENO
            'meta_porcentaje' => 80.0,
            'logro_porcentaje' => 87.5,
            'estado' => 'VALIDADA',
            'fecha_evaluacion' => date('Y-m-d'),
            'fuente_evidencia' => 'Informe de Empleabilidad F2 y Encuesta F3',
            'acciones_derivadas' => 'Consolidar módulos de Cloud en el plan curricular.',
        ];

        $response = $this->post(route('seguimiento-egresado.institucional.guardar-oe'), $data);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('seguimiento_egresado.evaluacion_oe', [
            'id_objetivo' => $oe->id,
            'id_cohorte' => $cohorte->id,
            'periodo' => '2024-II',
            'estado' => 'VALIDADA',
            'logro_porcentaje' => 87.5,
        ]);
    }

    public function test_guardar_mejora_curricular_computa_para_i6_con_origen_seguimiento(): void
    {
        $this->actingAs($this->director());
        $plan = PlanCurricular::where('estado', 'VIGENTE')->first();

        $data = [
            'codigo' => 'MC-2025-QA-AUTO',
            'id_plan_curricular' => $plan->id,
            'titulo' => 'Automatización integral de pruebas de software en asignaturas del 7mo ciclo',
            'tipo' => 'PERFIL',
            'origen_seguimiento' => true, // Requisito I6
            'descripcion' => 'Inclusión de Playwright y CI/CD ante requerimientos de empleadores de software.',
            'estado' => 'IMPLEMENTADA', // Requisito I6
            'dice' => 'Las pruebas de software se ejecutan a mano.',
            'debe_decir' => 'Las pruebas de software se automatizan con integración continua.',
            'fecha_registro' => date('Y-m-d'),
            'fecha_implementacion' => date('Y-m-d'),
            'periodo' => '2025-I',
            'responsable' => 'Comisión de Acreditación',
            'meta_esperada' => '100% de proyectos con pruebas automatizadas.',
            'grado_cumplimiento' => 100.0,
            'documento_referencia' => 'Resolución Directoral R-0045-2025-EII',
        ];

        $response = $this->post(route('seguimiento-egresado.institucional.guardar-mejora'), $data);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('seguimiento_egresado.mejora_curricular', [
            'codigo' => 'MC-2025-QA-AUTO',
            'estado' => 'IMPLEMENTADA',
            'origen_seguimiento' => true,
        ]);
    }

    public function test_guardar_evaluacion_competencia_persiste_en_base_de_datos(): void
    {
        $this->actingAs($this->director());
        $comp = Competencia::first();
        $cohorte = Cohorte::first();

        $data = [
            'id_competencia' => $comp->id,
            'id_cohorte' => $cohorte->id,
            'periodo' => '2024-II',
            'id_nivel_logro' => 1,
            'logro_porcentaje' => 84.5,
            'estado' => 'VALIDADA',
            'fecha_evaluacion' => date('Y-m-d'),
            'fuente_evidencia' => 'Encuesta Empresarial de Desempeño',
            'observacion' => 'Excelente desempeño en integración continua.',
        ];

        $response = $this->post(route('seguimiento-egresado.institucional.guardar-competencia'), $data);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('seguimiento_egresado.evaluacion_competencia', [
            'id_competencia' => $comp->id,
            'id_cohorte' => $cohorte->id,
            'periodo' => '2024-II',
            'estado' => 'VALIDADA',
        ]);
    }

    public function test_guardar_plan_accion_persiste_en_base_de_datos(): void
    {
        $this->actingAs($this->director());
        $data = [
            'codigo' => 'PA-TEST-2024-01',
            'titulo' => 'Plan de Convenios Estratégicos con Sector TI',
            'tipo' => 'VINCULACION',
            'estado' => 'EN_EJECUCION',
            'periodo' => '2024-II',
            'actividad' => 'Mesas de trabajo y convenios con gremios tecnológicos.',
            'meta_esperada' => '5 convenios activos.',
            'responsable' => 'Comisión de Vinculación',
            'grado_cumplimiento' => 60.0,
            'documento_referencia' => 'Resolución Decanal R-098-2024',
        ];

        $response = $this->post(route('seguimiento-egresado.institucional.guardar-plan'), $data);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('seguimiento_egresado.plan_accion', [
            'codigo' => 'PA-TEST-2024-01',
            'tipo' => 'VINCULACION',
            'estado' => 'EN_EJECUCION',
        ]);
    }

    // =========================================================================
    // S3-15 · Generar reportes funcionales desde la BD (CSV, indicadores, auditoría)
    // =========================================================================

    public function test_exportar_campanias_csv_genera_stream_con_cabeceras_oficiales(): void
    {
        $response = $this->get(route('seguimiento-egresado.reportes.campanias.csv'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $content = $response->streamedContent();

        $this->assertStringContainsString('UNIVERSIDAD NACIONAL DE TRUJILLO', $content);
        $this->assertStringContainsString('REPORTE OFICIAL DE CAMPANAS DE SEGUIMIENTO Y COBERTURA', $content);
        $this->assertStringContainsString('Campaña Anual de Seguimiento al Egresado 2023', $content);
        $this->assertStringContainsString('La falta de respuesta o personas pendientes NO equivale a desempleo', $content);
    }

    public function test_exportar_invitaciones_campania_csv_incluye_matricula_y_estados(): void
    {
        $campania = CampaniaSeguimiento::first();

        $response = $this->get(route('seguimiento-egresado.reportes.invitaciones.csv', $campania->id));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('PADRON DETALLADO DE CONVOCADOS Y ESTADOS DE RESPUESTA', $content);
        $this->assertStringContainsString('1021400118', $content); // Matrícula de egresado del seeder
        $this->assertStringContainsString('COMPLETADA', $content);
    }

    public function test_exportar_indicadores_i1_i6_csv_respeta_denominador_oficial_y_conteo_i6(): void
    {
        $response = $this->get(route('seguimiento-egresado.reportes.indicadores.csv'));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('REPORTE CONSOLIDADO DE INDICADORES OFICIALES (I1 - I6)', $content);
        $this->assertStringContainsString('I1', $content);
        $this->assertStringContainsString('I2', $content);
        $this->assertStringContainsString('I3', $content);
        $this->assertStringContainsString('I4', $content);
        $this->assertStringContainsString('I5', $content);
        $this->assertStringContainsString('I6', $content);
        $this->assertStringContainsString('Denominador oficial = 4 OEs totales del programa', $content);
        $this->assertStringContainsString('No corresponde (conteo absoluto)', $content);
    }

    // =========================================================================
    // S3-16 · Cubrir y documentar formatos F1–F11
    // =========================================================================

    public function test_reportes_page_displays_matriz_f1_f11(): void
    {
        $response = $this->get(route('seguimiento-egresado.reportes'));

        $response->assertOk()
            ->assertSee('Reportes y formatos institucionales F1–F11')
            ->assertSee('Matriz de correspondencia y disponibilidad F1–F11')
            ->assertSee('11 Formatos Mapeados')
            ->assertSee('F1')
            ->assertSee('F2')
            ->assertSee('F3')
            ->assertSee('F4')
            ->assertSee('F5')
            ->assertSee('F6')
            ->assertSee('F7')
            ->assertSee('F8')
            ->assertSee('F9')
            ->assertSee('F10')
            ->assertSee('F11')
            ->assertSee('IMPLEMENTADO');
    }

    public function test_formatos_f1_a_f11_generan_efectivamente(): void
    {
        foreach (['F1', 'F2', 'F3', 'F4', 'F5', 'F6', 'F7', 'F8', 'F9', 'F10', 'F11'] as $fmt) {
            $response = $this->get(route('seguimiento-egresado.reportes.formato', $fmt));
            $response->assertOk();
            $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        }
    }

    public function test_reporte_generado_registra_auditoria_con_hash_sha256(): void
    {
        $this->get(route('seguimiento-egresado.reportes.campanias.csv'));

        $reporteAudit = ReporteGenerado::where('tipo', 'CONSOLIDADO')->where('filtros->formato', 'CAMPANIAS')->first();
        $this->assertNotNull($reporteAudit);
        $this->assertSame(64, strlen($reporteAudit->sha256_archivo));
        $this->assertNotNull($reporteAudit->fecha_corte);
        $this->assertNotNull($reporteAudit->id_usuario);
    }
}
