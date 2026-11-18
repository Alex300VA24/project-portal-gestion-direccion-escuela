<?php

namespace Tests\Feature\SeguimientoEgresado;

use App\Modules\SeguimientoEgresado\Database\Seeders\DatosPruebaSeeder;
use App\Modules\SeguimientoEgresado\Models\CampaniaSeguimiento;
use App\Modules\SeguimientoEgresado\Models\CatCanalEnvio;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\Competencia;
use App\Modules\SeguimientoEgresado\Models\ContactoEgresado;
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
            ->assertSee('+ Nueva campaña')
            ->assertSee('egresados convocados')
            ->assertSee('respondieron')
            ->assertSee('de respuesta')
            ->assertSee('Campaña Anual de Seguimiento al Egresado 2023')
            ->assertSee('Ver detalle')
            ->assertDontSee('Matriz de Formatos')
            ->assertDontSee('WhatsApp')
            ->assertDontSee('Convocatoria Mixta')
            ->assertDontSee('Estado inicial');
    }

    public function test_crear_campania_valida_servidor_y_persiste_en_postgresql(): void
    {
        $cohorte = Cohorte::first();

        $response = $this->post(route('seguimiento-egresado.campanias.store'), [
            'nombre' => 'Campaña de Inserción Laboral Cohorte 2019',
            'tipo' => 'EGRESADOS',
            'horizonte_anios' => 1,
            'periodo' => '2026-II',
            'fecha_inicio' => date('Y-m-d'),
            'fecha_fin' => date('Y-m-d', strtotime('+60 days')),
            'cohortes' => [$cohorte->id],
            'descripcion' => 'Relevamiento laboral a 1 año de egreso cohorte 2019.',
        ]);

        $campania = CampaniaSeguimiento::where('nombre', 'Campaña de Inserción Laboral Cohorte 2019')->firstOrFail();
        $response->assertRedirect(route('seguimiento-egresado.campanias', ['ver' => $campania->id]));
        $response->assertSessionHas('success');

        $this->assertSame('ABIERTA', $campania->estado, 'una campaña que ya empezó nace abierta');
        $this->assertSame('SEGUIMIENTO', $campania->finalidad);
        $this->assertTrue($campania->cohortes->contains($cohorte->id));
        $this->assertCount(0, $campania->canales, 'ya no se eligen canales: es solo correo personal');
    }

    public function test_el_estado_de_la_campania_sale_de_sus_fechas(): void
    {
        $cohorte = Cohorte::first();
        $crear = fn (string $nombre, string $inicio, string $fin) => $this->post(route('seguimiento-egresado.campanias.store'), [
            'nombre' => $nombre, 'tipo' => 'EGRESADOS', 'periodo' => '2026-II', 'fecha_inicio' => $inicio, 'fecha_fin' => $fin, 'cohortes' => [$cohorte->id],
        ]);

        $crear('Empieza pronto', date('Y-m-d', strtotime('+10 days')), date('Y-m-d', strtotime('+40 days')))->assertSessionHasNoErrors();
        $this->assertSame('PROGRAMADA', CampaniaSeguimiento::where('nombre', 'Empieza pronto')->value('estado'));

        // Al llegar la fecha de inicio se abre sola, y al pasar la de fin se cierra sola (al entrar a cualquier pantalla del módulo).
        CampaniaSeguimiento::where('nombre', 'Empieza pronto')->update(['fecha_inicio' => date('Y-m-d', strtotime('-1 day'))]);
        $this->get(route('seguimiento-egresado.campanias'));
        $this->assertSame('ABIERTA', CampaniaSeguimiento::where('nombre', 'Empieza pronto')->value('estado'));

        CampaniaSeguimiento::where('nombre', 'Empieza pronto')->update(['fecha_inicio' => date('Y-m-d', strtotime('-30 days')), 'fecha_fin' => date('Y-m-d', strtotime('-1 day'))]);
        $this->get(route('seguimiento-egresado.campanias'));
        $this->assertSame('CERRADA', CampaniaSeguimiento::where('nombre', 'Empieza pronto')->value('estado'));
    }

    public function test_crear_campania_rechaza_datos_incoherentes(): void
    {
        $cohorte = Cohorte::first();
        $base = ['nombre' => 'Campaña de prueba', 'tipo' => 'EGRESADOS', 'periodo' => '2026-I', 'fecha_inicio' => date('Y-m-d'), 'fecha_fin' => date('Y-m-d', strtotime('+30 days')), 'cohortes' => [$cohorte->id]];
        $enviar = fn (array $cambios) => $this->post(route('seguimiento-egresado.campanias.store'), $cambios + $base);

        $enviar(['fecha_inicio' => date('Y-m-d', strtotime('+30 days')), 'fecha_fin' => date('Y-m-d', strtotime('+10 days'))])->assertSessionHasErrors('fecha_fin');
        $enviar(['fecha_inicio' => '2024-01-01', 'fecha_fin' => '2024-02-01'])->assertSessionHasErrors('fecha_fin');
        $enviar(['cohortes' => []])->assertSessionHasErrors('cohortes');
        $enviar(['periodo' => '2026'])->assertSessionHasErrors('periodo');
        $enviar(['tipo' => 'MIXTA'])->assertSessionHasErrors('tipo');
        $enviar(['nombre' => ''])->assertSessionHasErrors('nombre');

        $this->assertSame(0, CampaniaSeguimiento::where('nombre', 'Campaña de prueba')->count());
    }

    public function test_actualizar_campania_persiste_cambios_y_redirecciona(): void
    {
        $campania = CampaniaSeguimiento::first();
        $cohorte = Cohorte::first();

        $response = $this->put(route('seguimiento-egresado.campanias.update', $campania->id), [
            'nombre' => 'Campaña Anual Actualizada 2023',
            'horizonte_anios' => 1,
            'periodo' => '2023-II',
            'fecha_inicio' => $campania->fecha_inicio,
            'fecha_fin' => date('Y-m-d', strtotime('+45 days')),
            'cohortes' => [$cohorte->id],
            'descripcion' => 'Actualización de periodo y metas.',
        ]);
        $response->assertRedirect(route('seguimiento-egresado.campanias', ['ver' => $campania->id]));
        $response->assertSessionHas('success');

        $campania->refresh();
        $this->assertSame('Campaña Anual Actualizada 2023', $campania->nombre);
        $this->assertSame('EGRESADOS', $campania->tipo, 'el «para quién» no se cambia al editar');
        $this->assertSame('ABIERTA', $campania->estado, 'al ampliar la fecha de cierre la campaña vuelve a estar abierta');
    }

    // =========================================================================
    // S3-13 · Gestionar invitaciones y cobertura (Anti-duplicados, tokens, estados)
    // =========================================================================

    private function conCorreoPersonal(Cohorte $promocion): void
    {
        foreach (Egresado::where('id_cohorte', $promocion->id)->get() as $egresado) {
            ContactoEgresado::firstOrCreate(['id_egresado' => $egresado->id, 'vigente_hasta' => null], [
                'correo_personal' => "personal{$egresado->id}@correo.test", 'celular_whatsapp' => '+51900111222', 'vigente_desde' => now(), 'origen_registro' => 'FORMULARIO',
            ]);
        }
    }

    private function campaniaAbierta(Cohorte $promocion, array $cambios = []): CampaniaSeguimiento
    {
        $campania = CampaniaSeguimiento::create($cambios + [
            'nombre' => 'Encuesta Cohorte 2019', 'tipo' => 'EGRESADOS', 'finalidad' => 'SEGUIMIENTO', 'periodo' => '2026-II',
            'fecha_inicio' => now()->subDays(5)->toDateString(), 'fecha_fin' => now()->addDays(30)->toDateString(), 'estado' => 'ABIERTA',
            'id_usuario_creacion' => UsuarioInterno::idActual(),
        ]);
        $campania->cohortes()->attach($promocion->id);

        return $campania;
    }

    public function test_emitir_invitaciones_por_cohorte_evita_duplicados_y_asigna_token_hash(): void
    {
        $cohorte2019 = Cohorte::where('anio_ingreso', 2019)->first();
        $totalEgresados2019 = Egresado::where('id_cohorte', $cohorte2019->id)->count();
        $this->assertGreaterThan(0, $totalEgresados2019);
        $this->conCorreoPersonal($cohorte2019);
        $campania = $this->campaniaAbierta($cohorte2019);

        $res1 = $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $campania->id));
        $res1->assertRedirect(route('seguimiento-egresado.campanias', ['ver' => $campania->id]));
        $res1->assertSessionHas('success');

        $invitacionesCreadas = InvitacionEgresado::where('id_campania', $campania->id)->get();
        $this->assertSame($totalEgresados2019, $invitacionesCreadas->count());
        foreach ($invitacionesCreadas as $inv) {
            $this->assertSame(64, strlen($inv->token_hash));
            $this->assertSame('PENDIENTE', $inv->estado);
            $this->assertSame('SEGUIMIENTO', $inv->proposito);
            $this->assertNotNull($inv->expira_en);
        }

        $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $campania->id))->assertSessionHas('success');
        $this->assertSame($totalEgresados2019, InvitacionEgresado::where('id_campania', $campania->id)->count(), 'No deben duplicarse invitaciones para egresados ya convocados.');
    }

    public function test_quien_no_dejo_su_correo_personal_no_se_convoca_y_se_avisa(): void
    {
        $cohorte2019 = Cohorte::where('anio_ingreso', 2019)->first();
        $this->conCorreoPersonal($cohorte2019);
        $sin = Egresado::where('id_cohorte', $cohorte2019->id)->orderBy('id')->first();
        ContactoEgresado::where('id_egresado', $sin->id)->delete();
        $campania = $this->campaniaAbierta($cohorte2019);

        $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $campania->id));

        $this->assertFalse(InvitacionEgresado::where('id_campania', $campania->id)->where('id_egresado', $sin->id)->exists());
        $this->assertStringContainsString('no tiene contacto y no fue convocado', session('success'));
    }

    public function test_no_se_envian_invitaciones_ni_recordatorios_de_una_campania_cerrada_ni_de_empleadores(): void
    {
        $cohorte2019 = Cohorte::where('anio_ingreso', 2019)->first();
        $this->conCorreoPersonal($cohorte2019);

        $cerrada = $this->campaniaAbierta($cohorte2019, ['nombre' => 'Vieja', 'fecha_inicio' => now()->subDays(60)->toDateString(), 'fecha_fin' => now()->subDays(2)->toDateString(), 'estado' => 'CERRADA']);
        $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $cerrada->id))->assertSessionHasErrors('general');
        $this->post(route('seguimiento-egresado.campanias.recordar-pendientes', $cerrada->id))->assertSessionHasErrors('general');

        $empleadores = $this->campaniaAbierta($cohorte2019, ['nombre' => 'Empresas', 'tipo' => 'EMPRESAS']);
        $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $empleadores->id))->assertSessionHasErrors('general');
        $this->assertSame(0, InvitacionEgresado::whereIn('id_campania', [$cerrada->id, $empleadores->id])->count());
    }

    public function test_reenviar_recordatorio_incrementa_conteo_y_actualiza_timestamp(): void
    {
        $cohorte2019 = Cohorte::where('anio_ingreso', 2019)->first();
        $this->conCorreoPersonal($cohorte2019);
        $campania = $this->campaniaAbierta($cohorte2019);
        $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $campania->id));
        $invitacion = InvitacionEgresado::where('id_campania', $campania->id)->orderBy('id')->firstOrFail();
        $hashAnterior = $invitacion->token_hash;

        $response = $this->post(route('seguimiento-egresado.campanias.recordatorio', $invitacion->id));
        $response->assertRedirect(route('seguimiento-egresado.campanias', ['ver' => $campania->id]));
        $response->assertSessionHas('success');
        $response->assertSessionHas('enlaces_emitidos');

        $actualizada = InvitacionEgresado::find($invitacion->id);
        $this->assertSame(1, $actualizada->recordatorios_enviados);
        $this->assertNotNull($actualizada->ultimo_recordatorio_en);
        $this->assertNotSame($hashAnterior, $actualizada->token_hash, 'el enlace anterior deja de servir');
    }

    public function test_recordar_a_todos_los_pendientes_renueva_solo_los_pendientes(): void
    {
        $cohorte2019 = Cohorte::where('anio_ingreso', 2019)->first();
        $this->conCorreoPersonal($cohorte2019);
        $campania = $this->campaniaAbierta($cohorte2019);
        $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $campania->id));
        $invitaciones = InvitacionEgresado::where('id_campania', $campania->id)->orderBy('id')->get();
        $this->assertGreaterThanOrEqual(2, $invitaciones->count());
        $respondida = $invitaciones->first();
        $respondida->update(['estado' => 'COMPLETADA']);
        $hashes = $invitaciones->skip(1)->pluck('token_hash', 'id');

        $this->post(route('seguimiento-egresado.campanias.recordar-pendientes', $campania->id))->assertRedirect()->assertSessionHas('success')->assertSessionHas('enlaces_emitidos');

        $this->assertCount($invitaciones->count() - 1, session('enlaces_emitidos'));
        $this->assertSame(0, $respondida->refresh()->recordatorios_enviados, 'quien ya respondió no recibe recordatorio');
        foreach ($hashes as $id => $hash) {
            $nueva = InvitacionEgresado::find($id);
            $this->assertSame(1, $nueva->recordatorios_enviados);
            $this->assertNotSame($hash, $nueva->token_hash);
        }
    }

    public function test_el_detalle_de_la_campania_se_pide_aparte_y_la_lista_lo_abre_encima(): void
    {
        $campania = CampaniaSeguimiento::first();

        $this->get(route('seguimiento-egresado.campanias.detalle', $campania->id))
            ->assertOk()->assertSee($campania->nombre)->assertSee('Enviar invitaciones')->assertSee('Editar')
            ->assertDontSee('<html', false)
            ->assertSee('abrirEditarCampania(JSON.parse', false);
        $this->get(route('seguimiento-egresado.campanias', ['ver' => $campania->id]))->assertOk()->assertSee('dlg-detalle', false)->assertSee($campania->nombre);
    }

    public function test_cobertura_no_confunde_ausencia_con_desempleo(): void
    {
        $response = $this->get(route('seguimiento-egresado.campanias'));

        $response->assertOk()
            ->assertDontSee('Criterio metodológico fundamental')
            ->assertSee('de respuesta');
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

    public function test_la_pagina_de_reportes_y_su_matriz_ya_no_existen(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('seguimiento-egresado.reportes'));
        $this->get('/seguimiento-egresado/reportes')->assertNotFound();
        $this->get(route('seguimiento-egresado.campanias'))->assertDontSee('Matriz de Formatos');
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
