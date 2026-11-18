<?php

namespace Tests\Feature\SeguimientoEgresado;

use App\Modules\SeguimientoEgresado\Database\Seeders\DatosPruebaSeeder;
use App\Modules\SeguimientoEgresado\Models\CampaniaSeguimiento;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\Competencia;
use App\Modules\SeguimientoEgresado\Models\ContactoEgresado;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Models\EvaluacionCompetencia;
use App\Modules\SeguimientoEgresado\Models\EvaluacionOe;
use App\Modules\SeguimientoEgresado\Models\InvitacionEgresado;
use App\Modules\SeguimientoEgresado\Models\MejoraCurricular;
use App\Modules\SeguimientoEgresado\Models\ObjetivoEducacional;
use App\Modules\SeguimientoEgresado\Models\PlanAccion;
use App\Modules\SeguimientoEgresado\Models\ReporteGenerado;
use App\Modules\SeguimientoEgresado\Models\UsuarioInterno;
use App\Modules\SeguimientoEgresado\Services\Indicadores\CalculadoraIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores;
use Illuminate\Support\Facades\DB;
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
    // Evaluación institucional: informes de logro (F8, F9), cambios «Dice / Debe decir» (F10, F11) y planes de acción
    // La Secretaría prepara los registros y la Dirección los valida; solo lo validado cuenta en I3 e I6.
    // =========================================================================

    /** Nivel de logro (por código) de cada elemento: ['id del elemento' => id del nivel]. */
    private function nivelesDe(array $ids, string $codigo): array
    {
        $nivel = (int) DB::table('seguimiento_egresado.cat_nivel_logro')->where('codigo', $codigo)->value('id');

        return collect($ids)->mapWithKeys(fn ($id) => [(int) $id => $nivel])->all();
    }

    private function idsDeObjetivos(): array
    {
        return ObjetivoEducacional::where('vigente', true)->orderBy('codigo')->pluck('id')->all();
    }

    private function i3(): \App\Modules\SeguimientoEgresado\Services\Indicadores\ResultadoIndicador
    {
        return app(CalculadoraIndicadores::class)->i3(new FiltroIndicadores);
    }

    private function i6(): int
    {
        return (int) app(CalculadoraIndicadores::class)->i6(new FiltroIndicadores)->valor;
    }

    private function datosDeInforme(string $accion, array $niveles, int $anioIngreso = 2019): array
    {
        return [
            'id_cohorte' => Cohorte::where('anio_ingreso', $anioIngreso)->value('id'),
            'periodo' => '2026-I',
            'niveles' => $niveles,
            'observacion' => 'Conclusiones del comité.',
            'acciones_derivadas' => 'Reforzar proyectos con impacto social.',
            'accion' => $accion,
        ];
    }

    private function datosDeCambio(array $cambios = []): array
    {
        return $cambios + [
            'tipo' => 'OE',
            'titulo' => 'Redacción del objetivo de educación continua',
            'dice' => 'Educarse de manera continua en sus competencias profesionales y de investigación.',
            'debe_decir' => 'Educarse de manera continua en sus competencias profesionales, de investigación y de gestión.',
            'descripcion' => 'Los empleadores pidieron incluir la gestión de proyectos.',
            'estado' => 'PROPUESTA',
        ];
    }

    public function test_la_evaluacion_institucional_muestra_los_tres_objetivos_oficiales_y_el_resumen(): void
    {
        $response = $this->get(route('seguimiento-egresado.institucional'));

        $response->assertOk()
            ->assertSee('Evaluación institucional')
            ->assertSee('Participar en proyectos multidisciplinarios')
            ->assertSee('Aplicar la Ciencia de la Computación con responsabilidad social y ética')
            ->assertSee('MC-2023-01')
            ->assertSee('3 de 3')
            ->assertDontSee('OE4');
        $this->assertSame(3, ObjetivoEducacional::where('vigente', true)->count());
    }

    public function test_la_secretaria_deja_el_informe_en_borrador_y_no_cuenta_para_i3(): void
    {
        $antes = $this->i3();

        $respuesta = $this->post(route('seguimiento-egresado.institucional.informe-logro'), $this->datosDeInforme('borrador', $this->nivelesDe($this->idsDeObjetivos(), 'MALO')));

        $respuesta->assertRedirect()->assertSessionHas('success');
        $this->assertSame(3, EvaluacionOe::where('periodo', '2026-I')->where('estado', 'BORRADOR')->count());
        $despues = $this->i3();
        $this->assertSame($antes->numerador, $despues->numerador);
        $this->assertSame($antes->denominador, $despues->denominador);
    }

    public function test_la_secretaria_no_puede_validar_un_informe(): void
    {
        $respuesta = $this->post(route('seguimiento-egresado.institucional.informe-logro'), $this->datosDeInforme('validar', $this->nivelesDe($this->idsDeObjetivos(), 'BUENO')));

        $respuesta->assertSessionHasErrors('general');
        $this->assertSame(0, EvaluacionOe::where('periodo', '2026-I')->count());
    }

    public function test_la_direccion_valida_el_informe_y_entra_en_i3(): void
    {
        $this->actingAs($this->director());
        [$oe1, $oe2, $oe3] = $this->idsDeObjetivos();
        $niveles = $this->nivelesDe([$oe1], 'BUENO') + $this->nivelesDe([$oe2], 'MUY_BUENO') + $this->nivelesDe([$oe3], 'REGULAR');

        $this->post(route('seguimiento-egresado.institucional.informe-logro'), $this->datosDeInforme('validar', $niveles))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(3, EvaluacionOe::where('periodo', '2026-I')->where('estado', 'VALIDADA')->count());
        // Promoción 2018: 3 de 3 (informe 2023-II); promoción 2019: 2 de 3 (informe 2026-I) → 5 de 6.
        $i3 = $this->i3();
        $this->assertSame(5, $i3->numerador);
        $this->assertSame(6, $i3->denominador);
    }

    public function test_el_informe_pide_el_nivel_de_todos_los_objetivos(): void
    {
        $this->actingAs($this->director());
        $ids = $this->idsDeObjetivos();
        array_pop($ids);

        $this->post(route('seguimiento-egresado.institucional.informe-logro'), $this->datosDeInforme('borrador', $this->nivelesDe($ids, 'BUENO')))
            ->assertSessionHasErrors('niveles');

        $this->assertSame(0, EvaluacionOe::where('periodo', '2026-I')->count());
    }

    public function test_el_periodo_del_informe_debe_ser_de_la_lista(): void
    {
        $datos = $this->datosDeInforme('borrador', $this->nivelesDe($this->idsDeObjetivos(), 'BUENO'));
        $datos['periodo'] = '2026-III';

        $this->post(route('seguimiento-egresado.institucional.informe-logro'), $datos)->assertSessionHasErrors('periodo');
    }

    public function test_un_informe_validado_solo_lo_modifica_la_direccion(): void
    {
        $this->actingAs($this->director());
        $ids = $this->idsDeObjetivos();
        $this->post(route('seguimiento-egresado.institucional.informe-logro'), $this->datosDeInforme('validar', $this->nivelesDe($ids, 'BUENO')));

        $this->actingAs($this->secretaria());
        $this->post(route('seguimiento-egresado.institucional.informe-logro'), $this->datosDeInforme('borrador', $this->nivelesDe($ids, 'MALO')))
            ->assertSessionHasErrors('general');

        $this->assertSame(3, EvaluacionOe::where('periodo', '2026-I')->where('estado', 'VALIDADA')->count());
        $this->assertSame(0, EvaluacionOe::where('periodo', '2026-I')->whereHas('nivelLogro', fn ($q) => $q->where('codigo', 'MALO'))->count());
    }

    public function test_volver_a_guardar_un_informe_lo_actualiza_sin_duplicarlo(): void
    {
        $ids = $this->idsDeObjetivos();
        $ruta = route('seguimiento-egresado.institucional.informe-logro');
        $this->post($ruta, $this->datosDeInforme('borrador', $this->nivelesDe($ids, 'MALO')));
        $this->post($ruta, $this->datosDeInforme('borrador', $this->nivelesDe($ids, 'BUENO')));

        $this->assertSame(3, EvaluacionOe::where('periodo', '2026-I')->count());
        $this->assertSame(3, EvaluacionOe::where('periodo', '2026-I')->whereHas('nivelLogro', fn ($q) => $q->where('codigo', 'BUENO'))->count());
    }

    public function test_el_informe_de_competencias_guarda_un_nivel_por_competencia(): void
    {
        $competencias = Competencia::where('vigente', true)->pluck('id')->all();

        $this->post(route('seguimiento-egresado.institucional.informe-competencias'), $this->datosDeInforme('borrador', $this->nivelesDe($competencias, 'BUENO'), 2018))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(count($competencias), EvaluacionCompetencia::where('periodo', '2026-I')->where('estado', 'BORRADOR')->count());
    }

    public function test_la_secretaria_registra_un_cambio_como_propuesta(): void
    {
        $this->post(route('seguimiento-egresado.institucional.cambio'), $this->datosDeCambio())
            ->assertRedirect()->assertSessionHas('success');

        $cambio = MejoraCurricular::where('titulo', 'Redacción del objetivo de educación continua')->first();
        $this->assertNotNull($cambio);
        $this->assertSame('PROPUESTA', $cambio->estado);
        $this->assertMatchesRegularExpression('/^MC-'.now()->year.'-\d{2}$/', $cambio->codigo);
        $this->assertSame(ObjetivoEducacional::value('id_plan_curricular'), $cambio->id_plan_curricular);
        $this->assertNull($cambio->fecha_implementacion);
    }

    public function test_la_secretaria_no_puede_aprobar_ni_implementar_un_cambio(): void
    {
        $this->post(route('seguimiento-egresado.institucional.cambio'), $this->datosDeCambio([
            'estado' => 'IMPLEMENTADA', 'documento_referencia' => 'Resolución N.° 031-2026', 'fecha_implementacion' => now('America/Lima')->toDateString(),
        ]))->assertSessionHasErrors('general');

        $this->assertSame(0, MejoraCurricular::where('titulo', 'Redacción del objetivo de educación continua')->count());
    }

    public function test_un_cambio_implementado_necesita_documento_y_fecha(): void
    {
        $this->actingAs($this->director());

        $this->post(route('seguimiento-egresado.institucional.cambio'), $this->datosDeCambio(['estado' => 'IMPLEMENTADA']))
            ->assertSessionHasErrors(['documento_referencia', 'fecha_implementacion']);

        $this->assertSame(0, MejoraCurricular::where('titulo', 'Redacción del objetivo de educación continua')->count());
    }

    public function test_un_cambio_implementado_por_la_direccion_suma_uno_a_i6(): void
    {
        $this->actingAs($this->director());
        $antes = $this->i6();

        $respuesta = $this->post(route('seguimiento-egresado.institucional.cambio'), $this->datosDeCambio([
            'estado' => 'IMPLEMENTADA', 'documento_referencia' => 'Resolución N.° 031-2026', 'fecha_implementacion' => now('America/Lima')->toDateString(),
        ]));

        $respuesta->assertRedirect()->assertSessionHas('success');
        $this->assertStringContainsString('suma 1 a I6', session('success'));
        $this->assertSame($antes + 1, $this->i6());
    }

    public function test_un_cambio_que_sigue_en_ejecucion_no_suma_a_i6(): void
    {
        $this->actingAs($this->director());
        $antes = $this->i6();

        $this->post(route('seguimiento-egresado.institucional.cambio'), $this->datosDeCambio(['tipo' => 'PERFIL', 'estado' => 'EN_EJECUCION']))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame($antes, $this->i6());
    }

    public function test_un_cambio_de_otro_tipo_no_exige_dice_y_debe_decir(): void
    {
        $this->post(route('seguimiento-egresado.institucional.cambio'), $this->datosDeCambio(['tipo' => 'OTRA', 'dice' => null, 'debe_decir' => null]))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(1, MejoraCurricular::where('tipo', 'OTRA')->where('titulo', 'Redacción del objetivo de educación continua')->count());
    }

    public function test_un_cambio_de_objetivos_o_perfil_exige_dice_y_debe_decir(): void
    {
        $this->post(route('seguimiento-egresado.institucional.cambio'), $this->datosDeCambio(['dice' => null, 'debe_decir' => null]))
            ->assertSessionHasErrors(['dice', 'debe_decir']);
    }

    public function test_guardar_plan_accion_persiste_en_base_de_datos(): void
    {
        $this->post(route('seguimiento-egresado.institucional.guardar-plan'), [
            'titulo' => 'Convenios estratégicos con el sector TI',
            'tipo' => 'VINCULACION',
            'estado' => 'EN_EJECUCION',
            'actividad' => 'Mesas de trabajo y convenios con gremios tecnológicos.',
            'responsable' => 'Comisión de Vinculación',
            'grado_cumplimiento' => 60,
        ])->assertRedirect()->assertSessionHas('success');

        $plan = PlanAccion::where('titulo', 'Convenios estratégicos con el sector TI')->first();
        $this->assertNotNull($plan);
        $this->assertSame('VINCULACION', $plan->tipo);
        $this->assertMatchesRegularExpression('/^PA-'.now()->year.'-\d{2}$/', $plan->codigo);
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
        $this->assertStringContainsString('Denominador oficial = objetivos educacionales vigentes de cada promoción evaluada', $content);
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

    private function formato(string $codigo): string
    {
        $respuesta = $this->get(route('seguimiento-egresado.reportes.formato', $codigo));
        $respuesta->assertOk();

        return $respuesta->streamedContent();
    }

    public function test_f8_marca_el_nivel_de_logro_de_cada_objetivo_oficial_de_la_promocion(): void
    {
        $contenido = $this->formato('F8');

        $this->assertStringContainsString('INFORME DE LOGRO DE LOS OBJETIVOS EDUCACIONALES', $contenido);
        $this->assertStringContainsString('"PROMOCION O COHORTE:","Promoción 2018"', $contenido);
        $this->assertStringContainsString('"AÑO Y SEMESTRE:",2023-II', $contenido);
        $this->assertStringContainsString('"OBJETIVOS EDUCACIONALES","MUY BUENO",BUENO,REGULAR,MALO,"MUY MALO"', $contenido);
        $this->assertStringContainsString('"OE1 · Participar en proyectos multidisciplinarios', $contenido);
        $this->assertStringContainsString(',X,,,,', $contenido, 'OE1 quedó en MUY BUENO');
        $this->assertStringContainsString('CONCLUSIONES:', $contenido);
        $this->assertStringContainsString('TOMA DE DECISIONES:', $contenido);
        $this->assertStringContainsString('V° B° DIRECTOR DE ESCUELA PROFESIONAL', $contenido);
        $this->assertStringNotContainsString('OE4', $contenido);
    }

    public function test_f9_separa_las_competencias_genericas_de_las_especificas(): void
    {
        $this->actingAs($this->director());
        $competencias = Competencia::where('vigente', true)->pluck('id')->all();
        $this->post(route('seguimiento-egresado.institucional.informe-competencias'), $this->datosDeInforme('validar', $this->nivelesDe($competencias, 'BUENO'), 2018))->assertRedirect();

        $contenido = $this->formato('F9');

        $this->assertStringContainsString('INFORME DEL NIVEL DE LOGRO DE LAS COMPETENCIAS', $contenido);
        $this->assertStringContainsString('COMPETENCIAS GENERICAS', $contenido);
        $this->assertStringContainsString('COMPETENCIAS ESPECIFICAS', $contenido);
        $this->assertStringContainsString('CG1 · Comunicación Efectiva', $contenido);
        $this->assertStringContainsString('Validado por la Dirección', $contenido);
    }

    public function test_f10_y_f11_muestran_dice_debe_decir_y_si_cuentan_para_i6(): void
    {
        $this->actingAs($this->director());
        $this->post(route('seguimiento-egresado.institucional.cambio'), $this->datosDeCambio([
            'estado' => 'IMPLEMENTADA', 'documento_referencia' => 'Resolución N.° 031-2026', 'fecha_implementacion' => now('America/Lima')->toDateString(),
        ]))->assertRedirect();

        $f11 = $this->formato('F11');
        $f10 = $this->formato('F10');

        $this->assertStringContainsString('INFORME SOBRE CAMBIOS (MEJORAS) DE LOS OBJETIVOS EDUCACIONALES', $f11);
        $this->assertStringContainsString('DICE,"DEBE DECIR"', $f11);
        $this->assertStringContainsString('Redacción del objetivo de educación continua (Objetivos educacionales)', $f11);
        $this->assertStringContainsString('Resolución N.° 031-2026', $f11);
        $this->assertStringContainsString('"CUENTA PARA I6"', $f11);
        $this->assertStringContainsString('INFORME SOBRE CAMBIOS (MEJORAS) DEL PERFIL DE EGRESO', $f10);
        $this->assertStringContainsString('MC-2023-01 · Actualización de la línea curricular de Cloud Computing y DevOps (Perfil de egreso)', $f10);
        $this->assertStringContainsString('Resolución de Decanato R-0210-2023-D-FACINF', $f10);
        // Un cambio de perfil no aparece en el informe de objetivos, ni uno de objetivos en el de perfil.
        $this->assertStringNotContainsString('MC-2023-01', $f11);
        $this->assertStringNotContainsString('Redacción del objetivo de educación continua', $f10);
    }

    public function test_f5_y_f7_listan_cada_plan_con_su_cumplimiento_si_o_no(): void
    {
        $ruta = route('seguimiento-egresado.institucional.guardar-plan');
        $this->post($ruta, ['titulo' => 'Premiación a egresados destacados', 'tipo' => 'VINCULACION', 'estado' => 'EVALUADA', 'actividad' => 'Ceremonia anual.', 'grado_cumplimiento' => 100]);
        $this->post($ruta, ['titulo' => 'Feria laboral', 'tipo' => 'INSERCION', 'estado' => 'EN_EJECUCION', 'actividad' => 'Feria con empresas aliadas.', 'grado_cumplimiento' => 40, 'fecha_limite' => '2027-03-31']);

        $f5 = $this->formato('F5');
        $f7 = $this->formato('F7');

        $this->assertStringContainsString('INFORME SEMESTRAL Y ANUAL SOBRE LA EVALUACION DE RESULTADOS DE PLANES DE ACCION', $f5);
        $this->assertStringContainsString('Premiación a egresados destacados",X,,', $f5, 'cumplido: marca SI');
        $this->assertStringNotContainsString('Feria laboral', $f5);
        $this->assertStringContainsString('INFORME ESTADISTICO SEMESTRAL Y ANUAL DE LA MEJORA DE LA INSERCION LABORAL DE LOS EGRESADOS', $f7);
        $this->assertStringContainsString('Feria laboral",,X,', $f7, 'no cumplido: marca NO');
        $this->assertStringContainsString('Plazo: 31/03/2027', $f7);
        $this->assertStringNotContainsString('Premiación a egresados destacados', $f7);
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
