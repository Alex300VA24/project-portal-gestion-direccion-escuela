<?php

namespace Tests\Feature\InvestigacionFormativa;

use App\Modules\InvestigacionFormativa\Exceptions\EstadoInvalido;
use App\Modules\InvestigacionFormativa\Exceptions\RecursoBloqueado;
use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;
use App\Modules\InvestigacionFormativa\Services\ConfiguracionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\InvestigacionFormativa\Concerns\CreaDatosInvestigacion;
use Tests\TestCase;

final class ConfiguracionSeccionTest extends TestCase
{
    use CreaDatosInvestigacion;
    use DatabaseTransactions;

    private array $datos;

    private ConfiguracionService $configuracion;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL.');
        }

        $this->datos = $this->crearDatosInvestigacion();
        $this->crearCompetenciasSeccion();
        $this->configuracion = app(ConfiguracionService::class);
    }

    public function test_director_solo_ve_los_cursos_de_sus_escuelas(): void
    {
        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.cursos.index'))
            ->assertOk()
            ->assertSee('IF-501')
            ->assertDontSee('IF-502');

        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.cursos.index', ['escuela' => 921]))
            ->assertForbidden();
    }

    public function test_director_filtra_por_busqueda_y_periodo(): void
    {
        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.cursos.index', ['buscar' => 'IF-501']))
            ->assertOk()
            ->assertSee('IF-501');

        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.cursos.index', ['buscar' => 'no-existe']))
            ->assertOk()
            ->assertSee('No hay secciones que coincidan');

        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.cursos.index', ['periodo' => '2026-II']))
            ->assertOk()
            ->assertSee('IF-501');

        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.cursos.index', ['periodo' => '2025-I']))
            ->assertOk()
            ->assertSee('No hay secciones que coincidan');
    }

    public function test_director_habilita_seccion_y_la_recarga_muestra_habilitada(): void
    {
        $versionInicial = $this->configuracion->estado(9901)->version;

        $this->assertSame(1, $versionInicial);

        $this->actingAs($this->datos['users']['director'])
            ->from(route('investigacion-formativa.director.cursos.index'))
            ->patch(route('investigacion-formativa.director.secciones.habilitacion', 9901), [
                'habilitada' => 1,
                'version' => $versionInicial,
            ])
            ->assertRedirect(route('investigacion-formativa.director.cursos.index'));

        $estado = $this->configuracion->estado(9901);

        $this->assertTrue($estado->habilitada);
        $this->assertSame($versionInicial + 1, $estado->version);

        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.cursos.index'))
            ->assertOk()
            ->assertSee('Habilitada')
            ->assertSee('Deshabilitar');
    }

    public function test_habilitar_en_el_mismo_estado_no_incrementa_la_version(): void
    {
        $this->habilitarSeccion(9901, true, 1);

        $this->actingAs($this->datos['users']['director'])
            ->patch(route('investigacion-formativa.director.secciones.habilitacion', 9901), [
                'habilitada' => 1,
                'version' => 2,
            ])
            ->assertRedirect(route('investigacion-formativa.director.cursos.index'));

        $estado = $this->configuracion->estado(9901);

        $this->assertTrue($estado->habilitada);
        $this->assertSame(2, $estado->version);
    }

    public function test_version_desactualizada_al_habilitar_devuelve_conflicto(): void
    {
        $this->actingAs($this->datos['users']['director'])
            ->from(route('investigacion-formativa.director.cursos.index'))
            ->patch(route('investigacion-formativa.director.secciones.habilitacion', 9901), [
                'habilitada' => 1,
                'version' => 99,
            ])
            ->assertStatus(409)
            ->assertSee('Recarga la página');

        $this->assertFalse($this->configuracion->estado(9901)->habilitada);
    }

    public function test_director_no_puede_habilitar_una_seccion_de_otra_escuela(): void
    {
        $this->actingAs($this->datos['users']['director'])
            ->patch(route('investigacion-formativa.director.secciones.habilitacion', 9902), [
                'habilitada' => 1,
                'version' => 1,
            ])
            ->assertForbidden();
    }

    public function test_docente_guarda_competencias_y_recarga_la_seleccion(): void
    {
        $version = $this->habilitarParaConfiguracion();

        $this->actingAs($this->datos['users']['docente_a'])
            ->put(route('investigacion-formativa.docente.competencias.update', 9901), [
                'competencia_ids' => [9861, 9862],
                'version' => $version,
            ])
            ->assertRedirect(route('investigacion-formativa.docente.competencias.edit', 9901))
            ->assertSessionHasNoErrors();

        $competencias = collect($this->configuracion->competencias(9901))->keyBy('id_competencia');

        $this->assertTrue($competencias[9861]->seleccionada);
        $this->assertTrue($competencias[9862]->seleccionada);
        $this->assertSame($version + 1, $this->configuracion->estado(9901)->version);

        $this->actingAs($this->datos['users']['docente_a'])
            ->get(route('investigacion-formativa.docente.competencias.edit', 9901))
            ->assertOk()
            ->assertSee('Diseña el problema de investigación.')
            ->assertSee('value="9861"', false);
    }

    public function test_competencias_vacias_son_rechazadas(): void
    {
        $this->actingAs($this->datos['users']['docente_a'])
            ->put(route('investigacion-formativa.docente.competencias.update', 9901), [
                'competencia_ids' => [],
                'version' => 1,
            ])
            ->assertSessionHasErrors(['competencia_ids']);

        $this->assertSame(1, $this->configuracion->estado(9901)->version);
    }

    public function test_competencia_de_otra_seccion_es_rechazada(): void
    {
        $version = $this->habilitarParaConfiguracion();

        $this->actingAs($this->datos['users']['docente_a'])
            ->put(route('investigacion-formativa.docente.competencias.update', 9901), [
                'competencia_ids' => [9863],
                'version' => $version,
            ])
            ->assertRedirect(route('investigacion-formativa.docente.competencias.edit', 9901))
            ->assertSessionHasErrors(['competencias']);

        $this->assertDatabaseHas('investigacion_formativa.seccion_configuracion', [
            'id_seccion' => 9901,
            'version' => $version,
        ]);
        $this->assertFalse(
            DB::table('investigacion_formativa.competencia_seccion')
                ->where('id_competencia', 9863)
                ->value('seleccionada'),
        );
    }

    public function test_docente_guarda_rubrica_con_cuatro_niveles_y_recarga(): void
    {
        $version = $this->habilitarParaConfiguracion();

        $this->actingAs($this->datos['users']['docente_a'])
            ->put(route('investigacion-formativa.docente.rubrica.update', 9901), $this->rubricaPayload($version))
            ->assertRedirect(route('investigacion-formativa.docente.rubrica.edit', 9901))
            ->assertSessionHasNoErrors();

        $rubrica = $this->configuracion->rubrica(9901);

        $this->assertNotNull($rubrica);
        $this->assertSame('Rúbrica de pruebas', $rubrica->nombre);
        $this->assertCount(2, $rubrica->criterios);

        foreach ($rubrica->criterios as $criterio) {
            $this->assertCount(4, $criterio->niveles);
            $this->assertSame(4.0, $criterio->puntaje_maximo);
        }

        $this->assertSame($version + 1, $this->configuracion->estado(9901)->version);

        $this->actingAs($this->datos['users']['docente_a'])
            ->get(route('investigacion-formativa.docente.rubrica.edit', 9901))
            ->assertOk()
            ->assertSee('Rúbrica de pruebas')
            ->assertSee('Planteamiento del problema');
    }

    public function test_rubrica_rechaza_niveles_duplicados(): void
    {
        $payload = $this->rubricaPayload(1);
        $payload['criterios'][0]['niveles'][1]['nivel'] = 1;

        $this->actingAs($this->datos['users']['docente_a'])
            ->put(route('investigacion-formativa.docente.rubrica.update', 9901), $payload)
            ->assertSessionHasErrors(['criterios.0.niveles']);

        $this->assertNull($this->configuracion->rubrica(9901));
    }

    public function test_rubrica_rechaza_puntajes_negativos_o_con_demasiados_decimales(): void
    {
        $payload = $this->rubricaPayload(1);
        $payload['criterios'][0]['niveles'][0]['puntaje'] = '-1';

        $this->actingAs($this->datos['users']['docente_a'])
            ->put(route('investigacion-formativa.docente.rubrica.update', 9901), $payload)
            ->assertSessionHasErrors(['criterios.0.niveles.0.puntaje']);

        $payload = $this->rubricaPayload(1);
        $payload['criterios'][0]['niveles'][0]['puntaje'] = '1.234';

        $this->actingAs($this->datos['users']['docente_a'])
            ->put(route('investigacion-formativa.docente.rubrica.update', 9901), $payload)
            ->assertSessionHasErrors(['criterios.0.niveles.0.puntaje']);

        $this->assertNull($this->configuracion->rubrica(9901));
    }

    public function test_rubrica_rechaza_criterio_sin_puntaje_mayor_a_cero(): void
    {
        $payload = $this->rubricaPayload(1);

        foreach ($payload['criterios'][0]['niveles'] as $posicion => $nivel) {
            $payload['criterios'][0]['niveles'][$posicion]['puntaje'] = '0';
        }

        $this->actingAs($this->datos['users']['docente_a'])
            ->put(route('investigacion-formativa.docente.rubrica.update', 9901), $payload)
            ->assertSessionHasErrors(['criterios.0.niveles']);

        $this->assertNull($this->configuracion->rubrica(9901));
    }

    public function test_rubrica_se_congela_cuando_existen_entregas(): void
    {
        $version = $this->habilitarParaConfiguracion();
        $this->crearEntregaParaSeccion(9901);

        $this->actingAs($this->datos['users']['docente_a'])
            ->put(route('investigacion-formativa.docente.rubrica.update', 9901), $this->rubricaPayload($version))
            ->assertStatus(409)
            ->assertSee('Recarga la página');

        $this->assertNull($this->configuracion->rubrica(9901));
        $this->assertSame($version, $this->configuracion->estado(9901)->version);
    }

    public function test_cronograma_guarda_tres_fechas_en_hora_lima_y_no_duplica_entregables(): void
    {
        $version = $this->habilitarParaConfiguracion();

        $this->actingAs($this->datos['users']['docente_a'])
            ->put(route('investigacion-formativa.docente.competencias.update', 9901), [
                'competencia_ids' => [9861],
                'version' => $version,
            ])
            ->assertRedirect(route('investigacion-formativa.docente.competencias.edit', 9901))
            ->assertSessionHasNoErrors();

        $version = $this->guardarRubrica();

        $this->actingAs($this->datos['users']['docente_a'])
            ->put(route('investigacion-formativa.docente.cronograma.update', 9901), [
                'fechas' => ['2026-09-10T23:00', '2026-10-10T23:00', '2026-11-10T23:00'],
                'version' => $version,
            ])
            ->assertRedirect(route('investigacion-formativa.docente.cronograma.edit', 9901))
            ->assertSessionHasNoErrors();

        $primera = DB::table('investigacion_formativa.entregable')
            ->where('id_seccion', 9901)
            ->where('orden', 1)
            ->first();

        $this->assertNotNull($primera);
        $this->assertSame(
            '2026-09-11 04:00',
            CarbonImmutable::parse($primera->fecha_limite)->utc()->format('Y-m-d H:i'),
        );

        $estado = $this->configuracion->estado(9901);

        $this->assertTrue($estado->tiene_cronograma);
        $this->assertTrue($estado->configurada);
        $this->assertNotNull($estado->configurado_en);
        $this->assertSame($version + 1, $estado->version);

        $this->actingAs($this->datos['users']['docente_a'])
            ->get(route('investigacion-formativa.docente.cronograma.edit', 9901))
            ->assertOk()
            ->assertSee('value="2026-09-10T23:00"', false);

        $this->actingAs($this->datos['users']['docente_a'])
            ->put(route('investigacion-formativa.docente.cronograma.update', 9901), [
                'fechas' => ['2026-09-12T20:00', '2026-10-12T20:00', '2026-11-12T20:00'],
                'version' => $estado->version,
            ])
            ->assertRedirect(route('investigacion-formativa.docente.cronograma.edit', 9901));

        $this->assertSame(
            3,
            DB::table('investigacion_formativa.entregable')->where('id_seccion', 9901)->count(),
        );
    }

    public function test_cronograma_sin_rubrica_es_rechazado(): void
    {
        $version = $this->habilitarParaConfiguracion();

        $this->actingAs($this->datos['users']['docente_a'])
            ->put(route('investigacion-formativa.docente.cronograma.update', 9901), [
                'fechas' => ['2026-09-10T23:00', '2026-10-10T23:00', '2026-11-10T23:00'],
                'version' => $version,
            ])
            ->assertRedirect(route('investigacion-formativa.docente.cronograma.edit', 9901))
            ->assertSessionHasErrors(['cronograma']);

        $this->assertSame(
            0,
            DB::table('investigacion_formativa.entregable')->where('id_seccion', 9901)->count(),
        );
    }

    public function test_cronograma_rechaza_fechas_fuera_de_orden_o_invalidas(): void
    {
        $version = $this->guardarRubrica();

        $this->actingAs($this->datos['users']['docente_a'])
            ->put(route('investigacion-formativa.docente.cronograma.update', 9901), [
                'fechas' => ['2026-10-10T23:00', '2026-09-10T23:00', '2026-11-10T23:00'],
                'version' => $version,
            ])
            ->assertSessionHasErrors(['fechas']);

        $this->actingAs($this->datos['users']['docente_a'])
            ->put(route('investigacion-formativa.docente.cronograma.update', 9901), [
                'fechas' => ['2026-09-10 23:00', '2026-10-10T23:00', '2026-11-10T23:00'],
                'version' => $version,
            ])
            ->assertSessionHasErrors(['fechas.0']);

        $this->assertSame(
            0,
            DB::table('investigacion_formativa.entregable')->where('id_seccion', 9901)->count(),
        );
    }

    public function test_completitud_y_version_compartida_entre_los_pasos(): void
    {
        $inicial = $this->configuracion->estado(9901)->version;

        $this->assertFalse($this->configuracion->estaConfigurada(9901));
        $this->assertContains('Rúbrica completa', $this->configuracion->estado(9901)->faltantes);

        $this->habilitarSeccion(9901, true, $inicial);
        $this->assertSame($inicial + 1, $this->configuracion->estado(9901)->version);

        $version = $this->configuracion->estado(9901)->version;
        $this->actingAs($this->datos['users']['docente_a'])
            ->put(route('investigacion-formativa.docente.competencias.update', 9901), [
                'competencia_ids' => [9861],
                'version' => $version,
            ])
            ->assertRedirect(route('investigacion-formativa.docente.competencias.edit', 9901));

        $estado = $this->configuracion->estado(9901);
        $this->assertTrue($estado->tiene_competencias);
        $this->assertFalse($estado->configurada);
        $this->assertSame($version + 1, $estado->version);

        $version = $this->guardarRubrica();

        $estado = $this->configuracion->estado(9901);
        $this->assertTrue($estado->tiene_rubrica);
        $this->assertFalse($estado->configurada);
        $this->assertSame($version, $estado->version);

        $this->actingAs($this->datos['users']['docente_a'])
            ->put(route('investigacion-formativa.docente.cronograma.update', 9901), [
                'fechas' => ['2026-09-10T23:00', '2026-10-10T23:00', '2026-11-10T23:00'],
                'version' => $version,
            ])
            ->assertRedirect(route('investigacion-formativa.docente.cronograma.edit', 9901));

        $estado = $this->configuracion->estado(9901);
        $this->assertTrue($estado->configurada);
        $this->assertSame($version + 1, $estado->version);
        $this->assertSame($inicial + 4, $estado->version);
    }

    public function test_docente_ajeno_y_director_no_acceden_a_la_configuracion(): void
    {
        foreach (['competencias', 'rubrica', 'cronograma'] as $paso) {
            $this->actingAs($this->datos['users']['docente_a'])
                ->get(route("investigacion-formativa.docente.{$paso}.edit", 9901))
                ->assertOk();

            $this->actingAs($this->datos['users']['docente_b'])
                ->get(route("investigacion-formativa.docente.{$paso}.edit", 9901))
                ->assertForbidden();

            $this->actingAs($this->datos['users']['director'])
                ->get(route("investigacion-formativa.docente.{$paso}.edit", 9901))
                ->assertForbidden();
        }

        $this->actingAs($this->datos['users']['docente_a'])
            ->get(route('investigacion-formativa.docente.competencias.edit', 9902))
            ->assertForbidden();
    }

    public function test_guardar_la_rubrica_dos_veces_no_duplica_criterios(): void
    {
        $this->guardarRubrica();
        $version = $this->configuracion->estado(9901)->version;

        $this->actingAs($this->datos['users']['docente_a'])
            ->put(route('investigacion-formativa.docente.rubrica.update', 9901), $this->rubricaPayload($version))
            ->assertRedirect(route('investigacion-formativa.docente.rubrica.edit', 9901));

        $rubrica = $this->configuracion->rubrica(9901);

        $this->assertNotNull($rubrica);
        $this->assertCount(2, $rubrica->criterios);
        $this->assertSame(
            8,
            DB::table('investigacion_formativa.criterio_nivel')
                ->whereIn('id_criterio', array_column($rubrica->criterios, 'id_criterio'))
                ->count(),
        );
    }

    public function test_una_seccion_deshabilitada_rechaza_cambios_del_docente(): void
    {
        $actor = app(AccesoInvestigacionService::class)
            ->resolver($this->datos['users']['docente_a']);

        try {
            $this->configuracion->guardarCompetencias($actor, 9901, [9861], 1);
            $this->fail('La sección deshabilitada aceptó un cambio de configuración.');
        } catch (EstadoInvalido $exception) {
            $this->assertStringContainsString('deshabilitada', $exception->getMessage());
        }

        $this->assertDatabaseMissing('investigacion_formativa.seccion_configuracion', [
            'id_seccion' => 9901,
        ]);
    }

    public function test_las_competencias_se_congelan_despues_de_una_entrega(): void
    {
        $this->configurarSeccionCompleta(9901);
        $this->crearEntregaEnSeccionConfigurada(9901);
        $actor = app(AccesoInvestigacionService::class)
            ->resolver($this->datos['users']['docente_a']);

        try {
            $this->configuracion->guardarCompetencias($actor, 9901, [9861], 1);
            $this->fail('La sección con entrega aceptó un cambio de competencias.');
        } catch (RecursoBloqueado $exception) {
            $this->assertStringContainsString('entregas', $exception->getMessage());
        }

        $this->assertSame(1, $this->configuracion->estado(9901)->version);
    }

    public function test_el_cronograma_no_se_acorta_despues_de_una_entrega(): void
    {
        $this->configurarSeccionCompleta(9901);
        $this->crearEntregaEnSeccionConfigurada(9901);
        $actor = app(AccesoInvestigacionService::class)
            ->resolver($this->datos['users']['docente_a']);
        $fechas = array_map(
            static fn (int $dias): string => CarbonImmutable::now('America/Lima')
                ->addDays($dias)
                ->format('Y-m-d\TH:i'),
            [10, 20, 25],
        );

        try {
            $this->configuracion->guardarCronograma($actor, 9901, $fechas, 1);
            $this->fail('La sección con entrega aceptó un cronograma más corto.');
        } catch (RecursoBloqueado $exception) {
            $this->assertStringContainsString('extender', $exception->getMessage());
        }

        $this->assertSame(1, $this->configuracion->estado(9901)->version);
    }

    public function test_el_cronograma_solo_se_extiende_despues_de_una_entrega(): void
    {
        $this->configurarSeccionCompleta(9901);
        $this->crearEntregaEnSeccionConfigurada(9901);
        $actor = app(AccesoInvestigacionService::class)
            ->resolver($this->datos['users']['docente_a']);
        $fechas = array_map(
            static fn (int $dias): string => CarbonImmutable::now('America/Lima')
                ->addDays($dias)
                ->format('Y-m-d\TH:i'),
            [40, 70, 100],
        );

        $this->configuracion->guardarCronograma($actor, 9901, $fechas, 1);

        $this->assertSame(2, $this->configuracion->estado(9901)->version);
    }

    public function test_el_momento_de_configuracion_original_se_conserva(): void
    {
        $this->configurarSeccionCompleta(9901);
        $original = DB::table('investigacion_formativa.seccion_configuracion')
            ->where('id_seccion', 9901)
            ->value('configurado_en');

        DB::table('investigacion_formativa.competencia_seccion')
            ->where('id_seccion', 9901)
            ->update(['seleccionada' => false]);

        $actor = app(AccesoInvestigacionService::class)
            ->resolver($this->datos['users']['director']);
        $this->configuracion->cambiarHabilitacion($actor, 9901, false, 1);

        $actual = DB::table('investigacion_formativa.seccion_configuracion')
            ->where('id_seccion', 9901)
            ->value('configurado_en');

        $this->assertSame($original, $actual);
    }

    public function test_la_rubrica_http_rechaza_puntajes_decrecientes(): void
    {
        $this->configurarSeccionCompleta(9901);
        $payload = $this->rubricaPayload(1);
        $payload['criterios'][0]['niveles'] = array_map(
            static fn (int $indice): array => [
                'nivel' => $indice + 1,
                'descripcion' => "Nivel {$indice}",
                'puntaje' => (string) (4 - $indice),
            ],
            range(0, 3),
        );

        $this->actingAs($this->datos['users']['docente_a'])
            ->putJson(route('investigacion-formativa.docente.rubrica.update', 9901), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['criterios.0.niveles']);
    }

    public function test_el_servicio_rechaza_puntajes_decrecientes(): void
    {
        $this->configurarSeccionCompleta(9901);
        $actor = app(AccesoInvestigacionService::class)
            ->resolver($this->datos['users']['docente_a']);
        $payload = $this->rubricaPayload(1);
        $payload['criterios'][0]['niveles'] = array_map(
            static fn (int $indice): array => [
                'nivel' => $indice + 1,
                'descripcion' => "Nivel {$indice}",
                'puntaje' => (string) (4 - $indice),
            ],
            range(0, 3),
        );

        try {
            $this->configuracion->guardarRubrica($actor, 9901, $payload, 1);
            $this->fail('El servicio aceptó puntajes decrecientes.');
        } catch (EstadoInvalido $exception) {
            $this->assertStringContainsString('no decrecientes', $exception->getMessage());
        }

        $this->assertSame('Rúbrica de integración', $this->configuracion->rubrica(9901)?->nombre);
    }

    public function test_el_momento_de_configuracion_conserva_el_instante_utc(): void
    {
        DB::statement("SET LOCAL TIME ZONE 'America/Lima'");
        $this->configurarSeccionCompleta(9901);
        DB::table('investigacion_formativa.seccion_configuracion')
            ->where('id_seccion', 9901)
            ->update(['configurado_en' => null]);
        $actor = app(AccesoInvestigacionService::class)
            ->resolver($this->datos['users']['docente_a']);
        $antes = CarbonImmutable::now('UTC')->getTimestamp();

        $this->configuracion->guardarCompetencias($actor, 9901, [9861], 1);

        $guardado = DB::table('investigacion_formativa.seccion_configuracion')
            ->where('id_seccion', 9901)
            ->value('configurado_en');
        $desfase = abs(CarbonImmutable::parse($guardado)->getTimestamp() - $antes);

        $this->assertLessThanOrEqual(3, $desfase);
    }

    private function crearEntregaEnSeccionConfigurada(int $idSeccion): void
    {
        $this->crearTipoProducto();

        DB::table('investigacion_formativa.grupo')->insert([
            'id_grupo' => 9985,
            'id_seccion' => $idSeccion,
            'nombre' => 'Grupo con entrega configurada',
        ]);

        DB::table('investigacion_formativa.proyecto')->insert([
            'id_proyecto' => 9986,
            'id_grupo' => 9985,
            'id_linea' => 9801,
            'id_tipo_producto' => 9981,
            'titulo' => 'Proyecto con entrega configurada',
            'estado' => 'Registrado',
            'version' => 1,
        ]);

        $idArchivo = DB::table('comun.archivo')->insertGetId([
            'id_modulo' => DB::table('seguridad.modulo_sistema')
                ->where('codigo', 'investigacion_formativa')
                ->value('id_modulo'),
            'nombre_original' => 'avance.pdf',
            'ruta_almacenamiento' => 'investigacion-formativa/test/configurada.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 10,
            'hash_sha256' => str_repeat('c', 64),
            'subido_por' => 8802,
        ], 'id_archivo');

        DB::table('investigacion_formativa.entrega')->insert([
            'id_proyecto' => 9986,
            'id_entregable' => DB::table('investigacion_formativa.entregable')
                ->where('id_seccion', $idSeccion)
                ->where('orden', 1)
                ->value('id_entregable'),
            'version' => 1,
            'id_archivo' => $idArchivo,
        ]);
    }

    private function habilitarSeccion(int $idSeccion, bool $habilitada, int $version): void
    {
        $this->actingAs($this->datos['users']['director'])
            ->patch(route('investigacion-formativa.director.secciones.habilitacion', $idSeccion), [
                'habilitada' => $habilitada ? 1 : 0,
                'version' => $version,
            ])
            ->assertRedirect(route('investigacion-formativa.director.cursos.index'));
    }

    private function habilitarParaConfiguracion(int $idSeccion = 9901): int
    {
        $estado = $this->configuracion->estado($idSeccion);

        if (! $estado->habilitada) {
            $this->habilitarSeccion($idSeccion, true, $estado->version);
        }

        return $this->configuracion->estado($idSeccion)->version;
    }

    private function guardarRubrica(int $idSeccion = 9901): int
    {
        $version = $this->habilitarParaConfiguracion($idSeccion);

        $this->actingAs($this->datos['users']['docente_a'])
            ->put(route('investigacion-formativa.docente.rubrica.update', $idSeccion), $this->rubricaPayload($version))
            ->assertRedirect(route('investigacion-formativa.docente.rubrica.edit', $idSeccion))
            ->assertSessionHasNoErrors();

        return $version + 1;
    }

    /** @return array<string, mixed> */
    private function rubricaPayload(int $version): array
    {
        return [
            'nombre' => 'Rúbrica de pruebas',
            'descripcion' => 'Rúbrica usada en las pruebas del módulo.',
            'criterios' => [
                [
                    'descripcion' => 'Planteamiento del problema',
                    'niveles' => $this->niveles('Inicial', 'En proceso', 'Logrado', 'Destacado'),
                ],
                [
                    'descripcion' => 'Marco teórico',
                    'niveles' => $this->niveles('Nulo', 'Básico', 'Suficiente', 'Excelente'),
                ],
            ],
            'version' => $version,
        ];
    }

    /** @return list<array{nivel: int, descripcion: string, puntaje: string}> */
    private function niveles(string ...$descripciones): array
    {
        $niveles = [];

        foreach (array_values($descripciones) as $indice => $descripcion) {
            $niveles[] = [
                'nivel' => $indice + 1,
                'descripcion' => $descripcion,
                'puntaje' => number_format($indice + 1, 2, '.', ''),
            ];
        }

        return $niveles;
    }
}
