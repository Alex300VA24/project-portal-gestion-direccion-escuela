<?php

namespace Tests\Feature\CajaNegra;

use App\Models\User;
use App\Modules\Bienestar\Database\Seeders\BienestarDemoSeeder;
use App\Modules\Bienestar\Models\AlertaBitacora;
use App\Modules\Bienestar\Models\AlertaRiesgo;
use App\Modules\Bienestar\Models\CasoBienestar;
use App\Modules\Bienestar\Models\Cita;
use App\Modules\Bienestar\Models\Derivacion;
use App\Modules\Bienestar\Models\SolicitudApoyo;
use App\Modules\Bienestar\Models\TipoCaso;
use App\Modules\Bienestar\Models\TipoSolicitud;
use App\Modules\Bienestar\Models\TipoSolicitudRequisito;
use App\Modules\Bienestar\Services\ReporteEstadisticoService;
use App\Modules\Bienestar\Services\SecretariaFiltroService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Caja negra: particiones, valores límite y permisos del módulo Bienestar por HTTP. */
final class BienestarCajaNegraTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL migrado mediante module:migrate all.');
        }
        $this->seed(BienestarDemoSeeder::class);
        Storage::fake('local');
    }

    private function como(string $rol): self
    {
        $this->actingAs(User::where('email', "{$rol}@portal.local")->firstOrFail());

        return $this;
    }

    private function alerta(array $datos = []): AlertaRiesgo
    {
        return AlertaRiesgo::create($datos + [
            'id_estudiante' => DB::table('core.estudiante')->where('codigo_universitario', '1021400121')->value('id_estudiante'),
            'periodo' => '2026-I', 'curso' => 'QA '.uniqid(), 'regla' => 'reporte_docente', 'origen' => 'reporte_docente',
            'prioridad' => 'media', 'estado' => 'remitida_bienestar', 'fecha_deteccion' => now(),
        ]);
    }

    private function caso(): CasoBienestar
    {
        return CasoBienestar::create([
            'id_estudiante' => $this->alerta()->id_estudiante, 'id_tipo_caso' => TipoCaso::value('id_tipo_caso'),
            'descripcion' => 'Caso QA', 'estado' => 'En_Atencion', 'abierto_en' => now(),
        ]);
    }

    private function tipo(bool $activo = true, ?bool $obligatorio = true): TipoSolicitud
    {
        $tipo = TipoSolicitud::create(['nombre' => 'QA '.uniqid(), 'unidad_responsable' => 'QA', 'plazo_dias_habiles' => 5,
            'base_normativa_fuente' => 'QA', 'estado_verificacion' => 'verificado', 'activo' => $activo]);
        if ($obligatorio !== null) {
            TipoSolicitudRequisito::create(['id_tipo_solicitud' => $tipo->id_tipo_solicitud, 'nombre' => 'DNI', 'descripcion' => 'DNI',
                'es_obligatorio' => $obligatorio, 'formatos_permitidos' => 'pdf', 'tamano_maximo_mb' => 1, 'orden' => 1, 'activo' => true]);
        }

        return $tipo->load('requisitos');
    }

    private function enviar(TipoSolicitud $tipo, ?UploadedFile $archivo, string $motivo = 'Motivo suficientemente largo')
    {
        $datos = ['id_tipo_solicitud' => $tipo->id_tipo_solicitud, 'motivo' => $motivo];
        if ($archivo) {
            $datos['archivos'] = [$tipo->requisitos->first()->id_requisito => $archivo];
        }

        return $this->como('alumno')->post(route('bienestar.alumno.solicitudes.store'), $datos);
    }

    private function pdf(int $kb = 100): UploadedFile
    {
        return UploadedFile::fake()->create('dni.pdf', $kb, 'application/pdf');
    }

    private function solicitud(): SolicitudApoyo
    {
        $tipo = $this->tipo();
        $this->enviar($tipo, $this->pdf())->assertSessionHasNoErrors();

        return SolicitudApoyo::where('id_tipo_solicitud', $tipo->id_tipo_solicitud)->firstOrFail();
    }

    // ---------- Matriz de acceso ----------

    public static function accesos(): array
    {
        $rutas = [
            'alumno.solicitudes.index' => ['alumno'], 'alumno.solicitudes.create' => ['alumno'], 'alumno.citas.index' => ['alumno'],
            'profesor.index' => ['profesor'], 'profesor.create' => ['profesor'],
            'secretaria.alertas' => ['secretaria'], 'secretaria.solicitudes' => ['secretaria'], 'secretaria.indicadores' => ['secretaria'],
            'catalogo-solicitudes.index' => ['bienestar'], 'catalogo-solicitudes.create' => ['bienestar'],
            'panel.index' => ['bienestar'], 'panel.alertas.create' => ['bienestar'], 'panel.solicitudes.index' => ['bienestar'],
            'informacion.create' => ['bienestar'],
            'director.index' => ['bienestar', 'director'], 'director.imprimir' => ['bienestar', 'director'], 'director.exportar-csv' => ['bienestar', 'director'],
        ];
        $casos = [];
        foreach ($rutas as $ruta => $permitidos) {
            $casos[$ruta] = [$ruta, $permitidos];
        }

        return $casos;
    }

    #[DataProvider('accesos')]
    public function test_matriz_rol_por_pantalla(string $ruta, array $permitidos): void
    {
        $url = route("bienestar.{$ruta}");
        $this->get($url)->assertRedirect('/login');
        foreach (['alumno', 'profesor', 'secretaria', 'bienestar', 'director'] as $rol) {
            $this->como($rol)->followingRedirects()->get($url)->assertStatus(in_array($rol, $permitidos, true) ? 200 : 403);
        }
    }

    public function test_primer_ingreso_obliga_a_cambiar_contrasena_y_luego_libera(): void
    {
        DB::table('seguridad.usuario')->where('correo_institucional', 'alumno@portal.local')->update(['debe_cambiar_contrasena' => true]);
        User::where('email', 'alumno@portal.local')->firstOrFail()->update(['password' => 'Portal2026!']);
        $url = route('bienestar.alumno.solicitudes.index');

        $this->como('alumno')->get($url)->assertRedirect(route('profile.edit'));
        $this->getJson($url)->assertForbidden();
        $this->put('/password', ['current_password' => 'Portal2026!', 'password' => 'OtraClave2026!', 'password_confirmation' => 'OtraClave2026!'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->get($url)->assertOk();
    }

    // ---------- Robustez ante entradas inválidas (nunca 500) ----------

    public static function urlsInvalidas(): array
    {
        return [
            ['bienestar', '/bienestar/informacion/abc', 404],
            ['bienestar', '/bienestar/informacion/99999999999999999999', 404],
            ['bienestar', '/bienestar/panel/alertas/abc/atender', 404],
            ['bienestar', '/bienestar/panel/casos/abc/derivar', 404],
            ['secretaria', '/bienestar/secretaria/solicitudes/abc/revisar', 404],
            ['secretaria', '/bienestar/secretaria/documentos/abc/descargar', 404],
            ['alumno', '/bienestar/alumno/solicitudes/abc/subsanar', 404],
            ['bienestar', '/bienestar/catalogo-solicitudes/abc/editar', 404],
            ['bienestar', '/bienestar/panel?buscar[]=x', 200],
            ['secretaria', '/bienestar/secretaria/alertas?buscar[]=x&estado[]=y', 200],
            ['secretaria', '/bienestar/secretaria/solicitudes?estado[]=x', 200],
            ['bienestar', '/bienestar/panel?origen[]=x&prioridad[]=y&tab[]=z', 200],
            ['bienestar', '/bienestar/panel/solicitudes?estado[]=x&id_beneficio[]=1', 200],
            ['secretaria', '/bienestar/secretaria/alertas?buscar=%27%20OR%201=1--%25', 200],
            ['profesor', '/bienestar/profesor/buscar-estudiante?codigo[]=x', 404],
            ['profesor', '/bienestar/profesor/buscar-estudiante?codigo=0000', 404],
        ];
    }

    #[DataProvider('urlsInvalidas')]
    public function test_urls_y_filtros_invalidos_no_rompen(string $rol, string $url, int $estado): void
    {
        $this->como($rol)->get($url)->assertStatus($estado);
    }

    #[DataProvider('filtrosReporte')]
    public function test_reportes_toleran_filtros_invalidos(string $query): void
    {
        foreach (['', '/imprimir', '/exportar-csv'] as $ruta) {
            $codigo = $this->como('director')->get("/bienestar/director{$ruta}?{$query}")->getStatusCode();
            $this->assertContains($codigo, [200, 302], "{$ruta}?{$query} devolvió {$codigo}");
        }
    }

    public static function filtrosReporte(): array
    {
        return [['fecha_inicio=no-es-fecha'], ['fecha_fin=2026-13-45'], ['periodo=2026/I'], ['periodo[]=x'], ['fecha_inicio[]=x'],
            ['fecha_inicio=2026-12-01&fecha_fin=2026-01-01'], ['fecha_inicio=2026-01-01&fecha_fin=2026-12-31']];
    }

    public function test_totales_del_reporte_respetan_el_rango_de_fechas(): void
    {
        Derivacion::create(['id_caso' => $this->caso()->id_caso, 'entidad_externa' => 'X', 'motivo' => 'motivo', 'estado' => 'Pendiente', 'derivado_en' => now(), 'consentimiento_estudiante' => true]);
        Cita::create(['id_caso' => $this->caso()->id_caso, 'inicio' => now(), 'fin' => now()->addHour(), 'modalidad' => 'Virtual', 'estado' => 'Programada']);
        $r = app(ReporteEstadisticoService::class)->obtenerReporteCompleto('2026-I', '1999-01-01', '1999-12-31');

        $this->assertSame(0, $r['kpis']['total_derivaciones']);
        $this->assertSame([], $r['citasPorMes']);
    }

    // ---------- Solicitudes del alumno ----------

    public function test_valores_limite_de_motivo_y_archivo(): void
    {
        $tipo = $this->tipo();
        $this->enviar($tipo, $this->pdf(), str_repeat('a', 9))->assertSessionHasErrors('motivo');
        $this->enviar($tipo, $this->pdf(), str_repeat('a', 1001))->assertSessionHasErrors('motivo');
        $this->enviar($tipo, $this->pdf(1025))->assertSessionHasErrors();
        $this->enviar($tipo, UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'))->assertSessionHasErrors();
        $this->enviar($tipo, null)->assertSessionHasErrors();
        $this->assertSame(0, SolicitudApoyo::where('id_tipo_solicitud', $tipo->id_tipo_solicitud)->count());

        $this->enviar($tipo, $this->pdf(1024), str_repeat('a', 10))->assertSessionHasNoErrors();
        $this->enviar($tipo, $this->pdf(), str_repeat('a', 1000))->assertSessionHasNoErrors();
        $this->assertSame(2, SolicitudApoyo::where('id_tipo_solicitud', $tipo->id_tipo_solicitud)->count());
    }

    public function test_no_se_puede_solicitar_un_tramite_inactivo(): void
    {
        $tipo = $this->tipo(activo: false);
        $this->enviar($tipo, $this->pdf())->assertSessionHasErrors('id_tipo_solicitud');
    }

    public function test_tramite_sin_requisitos_o_solo_opcionales_se_puede_enviar_sin_archivos(): void
    {
        $this->enviar($this->tipo(obligatorio: null), null)->assertSessionHasNoErrors();
        $this->enviar($this->tipo(obligatorio: false), null)->assertSessionHasNoErrors();
    }

    public function test_alumno_no_accede_a_solicitudes_ajenas(): void
    {
        $s = $this->solicitud();
        $otro = DB::table('core.estudiante')->where('codigo_universitario', '1021400222')->value('id_estudiante');
        $s->update(['id_estudiante' => $otro, 'estado' => 'observada']);

        $this->como('alumno')->get(route('bienestar.alumno.solicitudes.subsanar', $s))->assertNotFound();
        $idReq = $s->documentosRequisitos->first()->id_requisito;
        $this->post(route('bienestar.alumno.solicitudes.guardar-subsanacion', $s), ['archivos' => [$idReq => $this->pdf()]])->assertNotFound();
    }

    // ---------- Secretaría ----------

    public function test_observar_exige_comentario(): void
    {
        $s = $this->solicitud();
        $idReq = $s->documentosRequisitos->first()->id_requisito;
        $this->como('secretaria')->post(route('bienestar.secretaria.solicitudes.guardar-revision', $s), [
            'accion' => 'observar', 'revisiones' => [['id_requisito' => $idReq, 'estado' => 'observado', 'comentario' => '']],
        ])->assertSessionHasErrors();
        $this->assertSame('enviada', $s->refresh()->estado);
    }

    public function test_no_se_remite_con_requisito_observado_ni_dos_veces(): void
    {
        $s = $this->solicitud();
        $idReq = $s->documentosRequisitos->first()->id_requisito;
        $url = route('bienestar.secretaria.solicitudes.guardar-revision', $s);
        $datos = fn ($estado) => ['accion' => 'remitir', 'confirma_folio_externo' => 1, 'revisiones' => [['id_requisito' => $idReq, 'estado' => $estado]]];

        $this->como('secretaria')->post($url, $datos('observado'))->assertSessionHasErrors();
        $this->assertSame('enviada', $s->refresh()->estado);
        $this->post($url, $datos('conforme'))->assertSessionHasNoErrors();
        $this->assertSame('remitida', $s->refresh()->estado);
        $this->post($url, $datos('conforme'))->assertSessionHasErrors();
    }

    public function test_descarga_documento_y_404_si_falta_el_fisico(): void
    {
        $doc = $this->solicitud()->documentosRequisitos()->with('archivo')->first();
        $url = route('bienestar.secretaria.documentos.descargar', $doc);
        $this->como('secretaria')->get($url)->assertOk()->assertDownload('dni.pdf');
        $this->como('alumno')->get($url)->assertForbidden();
        Storage::disk('local')->delete($doc->archivo->ruta_almacenamiento);
        $this->como('secretaria')->get($url)->assertNotFound();
    }

    public function test_indicador_de_pasadas_incluye_las_que_estan_en_atencion(): void
    {
        $servicio = app(SecretariaFiltroService::class);
        $antes = $servicio->obtenerIndicadoresOperativos()['alertas_pasadas_bienestar'];
        $this->alerta(['estado' => 'en_atencion']);
        $this->assertSame($antes + 1, $servicio->obtenerIndicadoresOperativos()['alertas_pasadas_bienestar']);
    }

    // ---------- Panel de Bienestar ----------

    #[DataProvider('botonesDictamen')]
    public function test_botones_reales_del_panel_dictaminan(string $estado): void
    {
        $s = $this->solicitud();
        $s->update(['estado' => 'remitida', 'folio_externo_confirmado' => true]);

        $html = $this->como('bienestar')->get(route('bienestar.panel.solicitudes.index'))->assertOk()->getContent();
        $this->assertStringContainsString('name="estado" value="'.$estado.'"', $html);

        $this->post(route('bienestar.panel.solicitudes.evaluar', $s), ['estado' => $estado, 'observacion' => 'Dictamen de prueba'])->assertSessionHasNoErrors();
        $this->assertSame($estado, $s->refresh()->estado);
        $this->assertSame($estado === 'aprobada' ? 1 : 0, CasoBienestar::where('id_solicitud_origen', $s->id_solicitud)->count());
        // Un segundo dictamen queda bloqueado
        $this->post(route('bienestar.panel.solicitudes.evaluar', $s), ['estado' => 'rechazada', 'observacion' => 'Segundo dictamen'])->assertForbidden();
    }

    public static function botonesDictamen(): array
    {
        return [['aprobada'], ['rechazada']];
    }

    public function test_dictamen_invalido_o_sin_remitir(): void
    {
        $s = $this->solicitud();
        $url = route('bienestar.panel.solicitudes.evaluar', $s);
        $this->como('bienestar')->post($url, ['estado' => 'Aprobada'])->assertForbidden();
        $s->update(['estado' => 'remitida', 'folio_externo_confirmado' => true]);
        $this->post($url, ['estado' => 'cualquiera'])->assertSessionHasErrors('estado');
    }

    public function test_panel_no_lista_solicitudes_que_secretaria_no_remitio(): void
    {
        $s = $this->solicitud();
        $this->como('bienestar')->get(route('bienestar.panel.solicitudes.index'))->assertOk()->assertDontSee($s->tipoSolicitud->nombre);
        $s->update(['estado' => 'remitida', 'folio_externo_confirmado' => true]);
        $this->get(route('bienestar.panel.solicitudes.index'))->assertSee($s->tipoSolicitud->nombre);
        $s->update(['estado' => 'Aprobada']); // dato histórico con mayúscula
        $this->get(route('bienestar.panel.solicitudes.index', ['estado' => 'aprobada']))->assertSee($s->tipoSolicitud->nombre);
    }

    #[DataProvider('estadosNoAtendibles')]
    public function test_bienestar_no_atiende_alertas_fuera_de_su_bandeja(string $estado): void
    {
        $a = $this->alerta(['estado' => $estado]);
        $this->como('bienestar')->post(route('bienestar.panel.guardar-atencion', $a), [
            'accion' => 'abrir_caso', 'id_tipo_caso' => TipoCaso::value('id_tipo_caso'),
        ]);
        $this->assertSame($estado, $a->refresh()->estado);
        $this->assertNull($a->id_caso);
    }

    public static function estadosNoAtendibles(): array
    {
        return [['en_revision'], ['descartada'], ['atendida'], ['resuelta_automaticamente']];
    }

    public function test_abrir_caso_dos_veces_no_duplica(): void
    {
        $a = $this->alerta();
        $datos = ['accion' => 'abrir_caso', 'id_tipo_caso' => TipoCaso::value('id_tipo_caso')];
        $url = route('bienestar.panel.guardar-atencion', $a);
        $this->como('bienestar')->post($url, $datos)->assertSessionHasNoErrors();
        $idCaso = $a->refresh()->id_caso;
        $this->assertNotNull($idCaso);
        $total = CasoBienestar::count();
        $this->post($url, $datos);
        $this->assertSame($total, CasoBienestar::count());
        $this->assertSame($idCaso, $a->refresh()->id_caso);
    }

    public function test_programar_cita_y_verla_como_alumno_sin_notas(): void
    {
        $a = $this->alerta();
        $this->como('bienestar')->post(route('bienestar.panel.guardar-atencion', $a), [
            'accion' => 'programar_cita', 'inicio_cita' => now()->addDay()->format('Y-m-d\TH:i'), 'modalidad' => 'Virtual',
        ])->assertSessionHasNoErrors();
        $this->assertSame('en_atencion', $a->refresh()->estado);
        $this->get(route('bienestar.panel.atender', $a))->assertOk();
        $this->como('alumno')->get(route('bienestar.alumno.citas.index'))->assertOk()->assertSee('Virtual')->assertDontSee('psicopedagógica programada');
    }

    public function test_cita_invalida(): void
    {
        $url = route('bienestar.panel.guardar-atencion', $this->alerta());
        $this->como('bienestar')->post($url, ['accion' => 'programar_cita', 'inicio_cita' => 'x', 'modalidad' => 'Virtual'])->assertSessionHasErrors('inicio_cita');
        $this->post($url, ['accion' => 'programar_cita', 'inicio_cita' => now()->addDay(), 'modalidad' => 'Otra'])->assertSessionHasErrors('modalidad');
        $this->post($url, ['accion' => 'programar_cita', 'inicio_cita' => now()->subDay()->toDateTimeString(), 'modalidad' => 'Virtual'])->assertSessionHasErrors('inicio_cita');
        $this->post($url, ['accion' => 'otra'])->assertSessionHasErrors('accion');
        $this->post($url, ['accion' => 'descartar', 'motivo_descarte' => 'abc'])->assertSessionHasErrors('motivo_descarte');
    }

    public function test_descarte_desde_el_panel_queda_en_bitacora(): void
    {
        $a = $this->alerta();
        $this->como('bienestar')->post(route('bienestar.panel.guardar-atencion', $a), ['accion' => 'descartar', 'motivo_descarte' => 'Ya fue atendido'])->assertSessionHasNoErrors();
        $this->assertSame('descartada', $a->refresh()->estado);
        $this->assertSame(1, AlertaBitacora::where('id_alerta', $a->id_alerta)->where('accion', 'descartado')->count());
    }

    public function test_derivacion_requiere_destino_y_consentimiento(): void
    {
        $caso = $this->caso();
        $url = route('bienestar.panel.guardar-derivacion');
        $base = ['id_caso' => $caso->id_caso, 'motivo' => 'Requiere apoyo'];
        $this->como('bienestar')->followingRedirects()->get(route('bienestar.panel.derivar', $caso))->assertOk();
        $this->post($url, $base + ['consentimiento_estudiante' => 1])->assertSessionHasErrors('entidad_externa');
        $this->post($url, $base + ['entidad_externa' => 'Hospital'])->assertSessionHasErrors('consentimiento_estudiante');
        $this->post($url, $base + ['entidad_externa' => 'Hospital', 'consentimiento_estudiante' => 1])->assertSessionHasNoErrors();
        $d = Derivacion::where('id_caso', $caso->id_caso)->firstOrFail();
        $this->get(route('bienestar.panel.imprimir-derivacion', $d))->assertOk()->assertSee('Hospital');
    }

    public function test_resultado_de_caso_sin_alerta_no_redirige_a_una_alerta_ajena(): void
    {
        $caso = $this->caso(); // caso nacido de una solicitud: no tiene alerta
        $d = Derivacion::create(['id_caso' => $caso->id_caso, 'entidad_externa' => 'X', 'motivo' => 'motivo', 'estado' => 'Pendiente', 'derivado_en' => now(), 'consentimiento_estudiante' => true]);
        $this->como('bienestar')->post(route('bienestar.panel.registrar-resultado', $d), ['resultado_especialista' => 'Informe ok', 'estado_derivacion' => 'Atendida'])
            ->assertRedirect(route('bienestar.panel.casos.show', $caso));
    }

    public function test_reportes_nuevos_usan_el_periodo_activo(): void
    {
        DB::table('core.periodo_academico')->update(['estado' => 'CERRADO']);
        DB::table('core.periodo_academico')->insert(['codigo' => '2099-I', 'fecha_inicio' => '2099-03-15', 'fecha_fin' => '2099-07-20', 'estado' => 'ACTIVO', 'anio' => 2099, 'tipo' => 'REGULAR', 'numero_semanas' => 16]);
        $datos = ['codigo_estudiante' => '1021400222', 'curso' => 'QA', 'motivo_reporte' => 'QA-periodo', 'descripcion_reporte' => 'descripcion larga', 'prioridad' => 'media'];

        $this->como('profesor')->post(route('bienestar.profesor.store'), $datos)->assertSessionHasNoErrors();
        $this->como('bienestar')->post(route('bienestar.panel.alertas.store-manual'), $datos)->assertSessionHasNoErrors();
        $this->assertSame(['2099-I', '2099-I'], AlertaRiesgo::where('motivo_reporte', 'QA-periodo')->pluck('periodo')->all());
    }

    public function test_reporte_docente_valida_estudiante_y_limites(): void
    {
        $this->como('profesor')->post(route('bienestar.profesor.store'), ['codigo_estudiante' => 'NOEXISTE', 'curso' => str_repeat('x', 151), 'motivo_reporte' => '', 'descripcion_reporte' => 'corta', 'prioridad' => 'extrema'])
            ->assertSessionHasErrors(['codigo_estudiante', 'curso', 'motivo_reporte', 'descripcion_reporte', 'prioridad']);
    }

    public function test_busqueda_de_estudiante_limita_a_diez_por_minuto(): void
    {
        $this->como('profesor');
        $url = route('bienestar.profesor.buscar-estudiante', ['codigo' => '1021400222']);
        for ($i = 0; $i < 10; $i++) {
            $this->getJson($url)->assertOk()->assertJsonPath('codigo', '1021400222');
        }
        $this->getJson($url)->assertStatus(429);
    }

    // ---------- Catálogo y artículos ----------

    public function test_catalogo_crear_editar_y_desactivar(): void
    {
        $datos = ['nombre' => 'QA cat', 'unidad_responsable' => 'U', 'plazo_dias_habiles' => 1, 'base_normativa_fuente' => 'B', 'estado_verificacion' => 'verificado'];
        $this->como('bienestar');
        $this->post(route('bienestar.catalogo-solicitudes.store'), ['plazo_dias_habiles' => 0] + $datos)->assertSessionHasErrors('plazo_dias_habiles');
        $this->post(route('bienestar.catalogo-solicitudes.store'), ['plazo_dias_habiles' => 91] + $datos)->assertSessionHasErrors('plazo_dias_habiles');
        // El formulario no marca la descripción del requisito como obligatoria
        $this->post(route('bienestar.catalogo-solicitudes.store'), $datos + ['requisitos' => [['nombre' => 'DNI', 'descripcion' => '', 'formatos_permitidos' => 'pdf', 'tamano_maximo_mb' => 5]]])->assertSessionHasNoErrors();
        $tipo = TipoSolicitud::where('nombre', 'QA cat')->firstOrFail();
        $this->assertCount(1, $tipo->requisitos);
        $this->get(route('bienestar.catalogo-solicitudes.edit', $tipo))->assertOk();
        $this->put(route('bienestar.catalogo-solicitudes.update', $tipo), ['nombre' => 'QA cat 2'] + $datos)->assertSessionHasNoErrors();
        $this->patch(route('bienestar.catalogo-solicitudes.toggle-activo', $tipo));
        $this->assertFalse($tipo->refresh()->activo);
    }

    public function test_catalogo_con_plantilla_y_formatos_invalidos(): void
    {
        $datos = ['nombre' => 'QA plantilla', 'unidad_responsable' => 'U', 'plazo_dias_habiles' => 1, 'base_normativa_fuente' => 'B', 'estado_verificacion' => 'verificado'];
        $this->como('bienestar')->post(route('bienestar.catalogo-solicitudes.store'), $datos + ['requisitos' => [['nombre' => 'FUT', 'descripcion' => 'd', 'plantilla' => $this->pdf()]]])->assertSessionHasNoErrors();
        $req = TipoSolicitud::where('nombre', 'QA plantilla')->firstOrFail()->requisitos->first();
        $this->assertSame('bienestar', DB::table('comun.archivo as a')->join('seguridad.modulo_sistema as m', 'm.id_modulo', '=', 'a.id_modulo')->where('id_archivo', $req->id_archivo_plantilla)->value('m.codigo'));

        $this->post(route('bienestar.catalogo-solicitudes.store'), ['nombre' => 'QA mal'] + $datos + ['requisitos' => [['nombre' => 'X', 'descripcion' => 'd', 'formatos_permitidos' => 'pdf|required;php']]])
            ->assertSessionHasErrors('requisitos.0.formatos_permitidos');
    }

    public function test_articulo_publicado_sanitiza_xss_y_borrador_es_404(): void
    {
        $datos = ['titulo' => 'QA art', 'resumen' => 'r', 'detalle' => '<section><p>hola QA</p><img src=x onerror=alert(1)></section>', 'fuente_normativa' => 'f', 'fecha_ultima_revision' => '2026-01-01', 'estado' => 'publicado'];
        $this->como('bienestar')->post(route('bienestar.informacion.store'), $datos)->assertSessionHasNoErrors();
        $id = DB::table('bienestar.articulo_informativo')->where('titulo', 'QA art')->value('id_articulo');
        auth()->logout();
        $this->get("/bienestar/informacion/{$id}")->assertOk()->assertSee('hola QA')->assertDontSee('onerror=alert', false);
        DB::table('bienestar.articulo_informativo')->where('id_articulo', $id)->update(['estado' => 'borrador']);
        $this->get("/bienestar/informacion/{$id}")->assertNotFound();
    }

    public function test_403_usa_la_pagina_de_bienestar_con_boton_de_tema(): void
    {
        $this->como('alumno')->get('/bienestar/panel')->assertForbidden()->assertSee('Bienestar UNT')->assertSee('portalToggleTheme', false);
    }

    #[DataProvider('pantallasConTema')]
    public function test_pantallas_incluyen_un_solo_boton_de_tema(string $rol, string $url): void
    {
        $html = $this->como($rol)->followingRedirects()->get($url)->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'onclick="window.portalToggleTheme()"'));
        $this->assertStringContainsString("d.theme = leer('portal-theme', 'light')", $html);
    }

    public static function pantallasConTema(): array
    {
        return [['alumno', '/bienestar'], ['alumno', '/bienestar/alumno/solicitudes'], ['profesor', '/bienestar/profesor/reportar'],
            ['secretaria', '/bienestar/secretaria/alertas'], ['bienestar', '/bienestar/panel'], ['bienestar', '/bienestar/panel/alertas/crear'],
            ['bienestar', '/bienestar/informacion/crear'], ['director', '/bienestar/director']];
    }

    public function test_director_no_puede_mutar(): void
    {
        $this->como('director');
        foreach (['post', 'put', 'patch', 'delete'] as $m) {
            $this->{$m}('/bienestar/director/lo-que-sea')->assertForbidden();
        }
    }

    public function test_rechazo_exige_motivo_y_el_estudiante_lo_ve(): void
    {
        $s = $this->solicitud();
        $s->update(['estado' => 'remitida', 'folio_externo_confirmado' => true]);
        $url = route('bienestar.panel.solicitudes.evaluar', $s);
        $this->como('bienestar')->post($url, ['estado' => 'rechazada'])->assertSessionHasErrors('observacion');
        $this->post($url, ['estado' => 'rechazada', 'observacion' => 'No cumple la base normativa'])->assertSessionHasNoErrors();
        $this->como('alumno')->get(route('bienestar.alumno.solicitudes.show', $s))->assertOk()->assertSee('No cumple la base normativa');
    }

    public function test_notificaciones_llegan_sin_datos_sensibles_y_son_privadas(): void
    {
        $s = $this->solicitud();
        $aviso = \App\Modules\Bienestar\Models\Notificacion::latest('id_notificacion')->firstOrFail();
        $this->assertStringNotContainsString($s->motivo, $aviso->titulo.$aviso->mensaje);
        $this->como('profesor')->get(route('bienestar.notificaciones.abrir', $aviso))->assertNotFound();
        $this->como('secretaria')->get(route('bienestar.notificaciones.index'))->assertOk()->assertSee($aviso->titulo);
        $this->get(route('bienestar.notificaciones.abrir', $aviso))->assertRedirect();
        $this->assertNotNull($aviso->refresh()->leida_en);
    }

    public function test_solicitud_ajena_es_404_para_otro_estudiante(): void
    {
        $s = $this->solicitud();
        $otro = DB::table('core.estudiante')->where('id_estudiante', '!=', $s->id_estudiante)->value('id_estudiante');
        $s->update(['id_estudiante' => $otro]);
        $this->como('alumno')->get(route('bienestar.alumno.solicitudes.show', $s))->assertNotFound();
    }

    public function test_bienestar_no_ve_alertas_ni_expedientes_que_secretaria_no_paso(): void
    {
        $this->como('bienestar')->get(route('bienestar.panel.atender', $this->alerta(['estado' => 'en_revision'])))->assertNotFound();
        $s = $this->solicitud();
        $this->como('bienestar')->get(route('bienestar.panel.solicitudes.show', $s))->assertNotFound();
    }

    public function test_bienestar_descarga_documentos_solo_tras_la_remision(): void
    {
        $s = $this->solicitud();
        $doc = $s->documentosRequisitos()->firstOrFail();
        $url = route('bienestar.panel.documentos.descargar', $doc);
        $this->como('bienestar')->get($url)->assertNotFound();
        $s->update(['estado' => 'remitida', 'folio_externo_confirmado' => true]);
        $this->get($url)->assertOk();
        $this->como('secretaria')->get($url)->assertForbidden();
    }

    public function test_caso_cita_y_cierre_con_historial(): void
    {
        $caso = $this->caso();
        $this->como('bienestar')->get(route('bienestar.panel.casos.index'))->assertOk();
        $this->get(route('bienestar.panel.casos.show', $caso))->assertOk()->assertSee('m-cerrar');
        $this->post(route('bienestar.panel.casos.citas', $caso), ['inicio_cita' => now()->addDays(2)->format('Y-m-d\TH:i'), 'modalidad' => 'Virtual'])->assertSessionHasNoErrors();
        $cita = $caso->citas()->firstOrFail();
        $this->post(route('bienestar.panel.citas.registrar', $cita), ['estado' => 'Realizada'])->assertSessionHasErrors('notas');
        $this->post(route('bienestar.panel.citas.registrar', $cita), ['estado' => 'Realizada', 'notas' => 'Todavía no ocurre'])->assertSessionHasErrors('estado');
        $cita->update(['inicio' => now()->subDay(), 'fin' => now()->subDay()->addHour()]);
        $this->post(route('bienestar.panel.citas.registrar', $cita), ['estado' => 'Realizada', 'notas' => 'Se orientó al estudiante'])->assertSessionHasNoErrors();
        $this->post(route('bienestar.panel.casos.cerrar', $caso), ['observacion' => 'Objetivos del acompañamiento cumplidos'])->assertSessionHasNoErrors();
        $this->assertSame('Cerrado', $caso->refresh()->estado);
        $this->assertGreaterThan(0, $caso->historial()->where('estado_nuevo', 'Cerrado')->count());
        $this->post(route('bienestar.panel.casos.citas', $caso), ['inicio_cita' => now()->addDays(3)->format('Y-m-d\TH:i'), 'modalidad' => 'Virtual']);
        $this->assertSame(1, $caso->citas()->count());
        foreach (['alumno', 'profesor', 'secretaria', 'director'] as $rol) {
            $this->como($rol)->get(route('bienestar.panel.casos.show', $caso))->assertForbidden();
        }
    }

    public function test_requisitos_se_administran_por_modal_y_la_plantilla_se_descarga(): void
    {
        $tipo = $this->tipo();
        $datos = ['nombre' => 'Formato QA', 'descripcion' => 'Subir firmado', 'formatos' => ['pdf'], 'tamano_maximo_mb' => 2, 'es_obligatorio' => 1];
        $this->como('bienestar')->post(route('bienestar.catalogo-solicitudes.requisitos.store', $tipo), ['formatos' => ['exe']] + $datos)->assertSessionHasErrors('formatos.0');
        $this->post(route('bienestar.catalogo-solicitudes.requisitos.store', $tipo), $datos + ['plantilla' => $this->pdf()])->assertSessionHasNoErrors();
        $r = $tipo->requisitos()->where('nombre', 'Formato QA')->firstOrFail();
        $this->put(route('bienestar.catalogo-solicitudes.requisitos.update', $r), ['nombre' => 'Formato QA 2'] + $datos)->assertSessionHasNoErrors();
        $this->assertNotNull($r->refresh()->id_archivo_plantilla);
        $html = $this->como('alumno')->followingRedirects()->get(route('bienestar.alumno.solicitudes.create'))->assertOk()->getContent();
        $this->assertStringNotContainsString('ruta_almacenamiento', $html);
        $this->get(route('bienestar.requisitos.plantilla', $r))->assertOk();
        $this->post(route('bienestar.catalogo-solicitudes.requisitos.store', $tipo), $datos)->assertForbidden();
        $this->como('bienestar')->patch(route('bienestar.catalogo-solicitudes.requisitos.toggle', $r));
        $this->assertFalse($r->refresh()->activo);
    }

    public function test_secretaria_recibe_sin_haber_revisado(): void
    {
        $s = $this->solicitud();
        $this->como('secretaria')->post(route('bienestar.secretaria.solicitudes.guardar-revision', $s), ['accion' => 'recibir', 'revisiones' => [['id_requisito' => 1]]])->assertSessionHasNoErrors();
        $this->assertSame('recibida', $s->refresh()->estado);
    }

    public function test_seeder_no_pisa_lo_trabajado(): void
    {
        $s = SolicitudApoyo::where('estado', 'enviada')->firstOrFail();
        $s->update(['estado' => 'remitida', 'folio_externo_confirmado' => true]);
        $alertas = AlertaRiesgo::count();
        $this->seed(\App\Modules\Bienestar\Database\Seeders\BienestarDemoSeeder::class);
        $this->assertSame('remitida', $s->refresh()->estado);
        $this->assertSame($alertas, AlertaRiesgo::count());
    }

    public function test_reporte_filtra_casos_por_periodo_y_exporta_todas_las_series(): void
    {
        $r = app(\App\Modules\Bienestar\Services\ReporteEstadisticoService::class);
        $this->assertSame(0, array_sum($r->obtenerReporteCompleto('1999-I')['casosPorEstado'] ?? []));
        $csv = $this->como('director')->get(route('bienestar.director.exportar-csv'))->assertOk()->streamedContent();
        foreach (['ALERTAS POR PRIORIDAD', 'CITAS POR MES', 'TENDENCIA DE CASOS'] as $t) {
            $this->assertStringContainsString($t, $csv);
        }
    }

    public function test_ningun_formulario_usa_dialogos_del_navegador_y_hay_migas(): void
    {
        foreach (glob(resource_path('views/modules/bienestar/{*,*/*,*/*/*}.blade.php'), GLOB_BRACE) as $f) {
            $this->assertDoesNotMatchRegularExpression('/\b(confirm|alert)\(/', file_get_contents($f), $f);
            $this->assertStringNotContainsString('Volver', str_contains($f, '/errors/') ? '' : preg_replace('/Volver[^<]*estudiante/', '', file_get_contents($f)), $f);
        }
        $this->como('bienestar')->followingRedirects()->get(route('bienestar.panel.alertas.create'))->assertOk()->assertSee('Panel de alertas');
    }

    private function avisos(string $email): array
    {
        $id = DB::table('seguridad.usuario')->where('correo_institucional', $email)->value('id_usuario');

        return \App\Modules\Bienestar\Models\Notificacion::where('id_usuario_destino', $id)->orderByDesc('id_notificacion')->pluck('mensaje')->all();
    }

    public function test_cada_cambio_de_estado_avisa_al_estudiante_y_queda_en_el_historial(): void
    {
        $s = $this->solicitud();
        $n = $s->id_solicitud;
        $this->assertStringContainsString('Recibimos tu solicitud', $this->avisos('alumno@portal.local')[0]);
        $this->como('secretaria')->post(route('bienestar.secretaria.solicitudes.guardar-revision', $s), ['accion' => 'recibir'])->assertSessionHasNoErrors();
        $this->assertSame("Tu solicitud #{$n} pasó a revisión.", $this->avisos('alumno@portal.local')[0]);
        foreach (['observada', 'recibida', 'remitida', 'aprobada'] as $estado) {
            $antes = count($this->avisos('alumno@portal.local'));
            $s->refresh()->update(['estado' => $estado]);
            $this->assertCount($antes + 1, $this->avisos('alumno@portal.local'));
        }
        $this->assertSame(['aprobada', 'remitida', 'recibida', 'observada', 'recibida', 'enviada'], $s->historial()->pluck('estado_nuevo')->all());
        $this->assertSame('enviada', $s->historial()->where('estado_nuevo', 'recibida')->get()->last()->estado_anterior);
        // Guardar sin cambiar el estado no genera avisos
        $antes = count($this->avisos('alumno@portal.local'));
        $s->update(['observacion_dictamen' => 'nota']);
        $this->assertCount($antes, $this->avisos('alumno@portal.local'));
        $this->como('alumno')->get(route('bienestar.alumno.solicitudes.show', $s))->assertOk()->assertSee('Historial de estados');
    }

    public function test_avisos_en_vivo_son_propios_y_requieren_sesion(): void
    {
        $this->solicitud();
        $this->como('alumno')->getJson(route('bienestar.notificaciones.recientes'))->assertOk()->assertJsonPath('avisos.0.mensaje', fn ($m) => str_contains($m, 'Recibimos'));
        $this->como('director')->getJson(route('bienestar.notificaciones.recientes'))->assertOk()->assertJsonMissing(['titulo' => 'Solicitud: enviada']);
        auth()->logout();
        $this->getJson(route('bienestar.notificaciones.recientes'))->assertUnauthorized();
    }

    public function test_docente_recibe_el_estado_de_su_reporte_sin_el_motivo(): void
    {
        $docente = DB::table('core.docente as d')->join('seguridad.usuario as u', 'u.id_persona', '=', 'd.id_persona')->where('u.correo_institucional', 'profesor@portal.local')->value('id_docente');
        $a = $this->alerta(['estado' => 'en_revision', 'origen' => 'reporte_docente', 'regla' => 'reporte_docente', 'prioridad' => 'media', 'id_docente' => $docente]);
        $this->como('secretaria')->post(route('bienestar.secretaria.alertas.filtrar', $a), ['accion' => 'descartar', 'motivo' => 'Motivo reservado'])->assertSessionHasNoErrors();
        $this->assertSame("Tu reporte #{$a->id_alerta} fue descartado.", $this->avisos('profesor@portal.local')[0]);
    }

    public function test_no_se_muestran_montos_en_ninguna_pantalla(): void
    {
        $tipo = $this->tipo();
        foreach ([['alumno', route('bienestar.alumno.solicitudes.index')], ['bienestar', route('bienestar.catalogo-solicitudes.index')], ['bienestar', route('bienestar.catalogo-solicitudes.edit', $tipo)], ['secretaria', route('bienestar.secretaria.solicitudes.revisar', $this->solicitud())]] as [$rol, $url]) {
            $html = $this->como($rol)->get($url)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/costo|S\/ ?\d/i', strip_tags($html), $url);
        }
        $this->assertSame(0, \App\Modules\Bienestar\Models\TipoSolicitud::where('base_normativa_fuente', 'like', '%S/%')->count());
    }

    public function test_altas_breves_son_ventanas_sobre_su_listado(): void
    {
        foreach ([['alumno', 'alumno.solicitudes.create', 'm-solicitud'], ['profesor', 'profesor.create', 'm-reporte'], ['bienestar', 'panel.alertas.create', 'm-alerta'], ['bienestar', 'catalogo-solicitudes.create', 'm-tramite']] as [$rol, $ruta, $modal]) {
            $r = $this->como($rol)->get(route('bienestar.'.$ruta))->assertRedirectContains('abrir='.$modal);
            $this->get($r->headers->get('Location'))->assertOk()->assertSee('id="'.$modal.'"', false);
        }
    }
}
