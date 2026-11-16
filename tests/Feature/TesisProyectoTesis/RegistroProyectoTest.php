<?php

namespace Tests\Feature\TesisProyectoTesis;

use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Support\Estados;
use Illuminate\Support\Facades\DB;

final class RegistroProyectoTest extends TesisTestCase
{
    private function datos(array $extra = []): array
    {
        return array_replace([
            'titulo' => 'Sistema web con asistente conversacional para la orientación académica de ingresantes',
            'resumen' => 'Propone un asistente conversacional para orientar a los ingresantes de la escuela.',
            'linea' => 'LI-SW',
            'enfoque' => 'Cuantitativo',
            'tipo_investigacion' => 'Aplicada',
            'diseno' => 'Preexperimental',
            'lugar_ejecucion' => 'Escuela de Ingeniería Informática',
            'institucion' => 'Universidad Nacional de Trujillo',
            'duracion_meses' => 8,
            'financiamiento' => 'Autofinanciado',
            'coautor_codigo' => '1027004588',
            'actividades' => [
                ['descripcion' => 'Revisión bibliográfica', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-30', 'horas' => 8],
                ['descripcion' => '', 'fecha_inicio' => '', 'fecha_fin' => '', 'horas' => ''],
            ],
            'partidas' => [['codigo' => '2.3.1 5', 'descripcion' => 'Materiales de escritorio', 'monto' => '120.50']],
            'recursos' => [['tipo' => 'Servicios', 'descripcion' => 'Hosting', 'cantidad' => 1]],
            'colaboradores' => [['nombres' => 'Juan Pérez', 'institucion' => 'UNT', 'rol' => 'Asesoría estadística']],
            'documento' => $this->pdf('F1.pdf'),
        ], $extra);
    }

    public function test_alumno_sin_proyecto_ve_invitacion_a_registrar(): void
    {
        $this->como('alumno@portal.local')->get(route('tesis-proyecto-tesis.alumno.panel'))
            ->assertOk()->assertSee('Aún no registras tu proyecto de tesis');

        $this->como('alumno@portal.local')->get(route('tesis-proyecto-tesis.alumno.proyecto.registrar'))
            ->assertOk()->assertSee('Registrar proyecto de tesis')->assertSee('Ingeniería de software');

        $this->como('alumno@portal.local')->get('/tesis-proyecto-tesis')->assertRedirect(route('tesis-proyecto-tesis.alumno.panel'));
    }

    public function test_alumno_registra_proyecto_con_coautor(): void
    {
        $this->como('alumno@portal.local')
            ->post(route('tesis-proyecto-tesis.alumno.proyecto.guardar'), $this->datos())
            ->assertRedirect(route('tesis-proyecto-tesis.alumno.panel'))
            ->assertSessionHasNoErrors();

        $p = Proyecto::query()->latest('id_proyecto')->firstOrFail();
        $this->assertSame(Estados::REGISTRADO, $p->estado);
        $this->assertSame(Estados::ETAPA_PROYECTO, $p->etapa);
        $this->assertMatchesRegularExpression('/^PT-\d{4}-\d{3}$/', $p->codigo);
        $this->assertNotNull($p->id_archivo_documento);
        $this->assertSame(2, $p->integrantesActivos()->count());
        $this->assertSame(1, $p->actividades()->count(), 'Las filas vacías no se guardan.');
        $this->assertEquals(120.5, $p->partidas()->first()->monto());
        $this->assertSame(1, $p->colaboradores()->count());
        $this->assertDatabaseHas('tesis.historial_estado', ['id_proyecto' => $p->id_proyecto, 'estado_anterior' => null, 'estado_nuevo' => 'Registrado']);
        $this->assertSame(1, DB::table('comun.notificacion')->where('id_usuario_destino', $this->idUsuario('alumno2@portal.local'))->count());
        $this->assertSame(1, DB::table('comun.notificacion')->where('id_usuario_destino', $this->idUsuario('director@portal.local'))->count());

        $this->como('alumno2@portal.local')->get(route('tesis-proyecto-tesis.alumno.panel'))->assertOk()->assertSee($p->codigo)->assertSee('Lucía Ramírez Torres');
        $this->como('alumno@portal.local')->get(route('tesis-proyecto-tesis.alumno.proyecto'))->assertOk()->assertSee('Revisión bibliográfica')->assertSee('120.50');
    }

    public function test_registro_individual_sin_coautor(): void
    {
        $this->como('alumno@portal.local')
            ->post(route('tesis-proyecto-tesis.alumno.proyecto.guardar'), $this->datos(['coautor_codigo' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Proyecto::query()->latest('id_proyecto')->first()->integrantesActivos()->count());
    }

    public function test_codigo_correlativo_por_anio(): void
    {
        $this->como('alumno@portal.local')->post(route('tesis-proyecto-tesis.alumno.proyecto.guardar'), $this->datos(['coautor_codigo' => '']));
        $this->como('alumno2@portal.local')->post(route('tesis-proyecto-tesis.alumno.proyecto.guardar'), $this->datos(['coautor_codigo' => '', 'documento' => $this->pdf()]));

        $codigos = Proyecto::query()->orderBy('id_proyecto')->pluck('codigo')->all();
        $n = array_map(fn ($c) => (int) substr($c, -3), array_slice($codigos, -2));
        $this->assertSame($n[0] + 1, $n[1]);
    }

    public function test_validacion_de_campos_obligatorios_y_formato(): void
    {
        $this->como('alumno@portal.local')
            ->post(route('tesis-proyecto-tesis.alumno.proyecto.guardar'), $this->datos([
                'titulo' => str_repeat('palabra ', 21),
                'linea' => 'NO-EXISTE',
                'enfoque' => 'Otro',
                'duracion_meses' => 0,
                'actividades' => [['descripcion' => 'Etapa', 'fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-01']],
                'documento' => \Illuminate\Http\UploadedFile::fake()->create('f1.docx', 10, 'application/msword'),
            ]))
            ->assertSessionHasErrors(['titulo', 'linea', 'enfoque', 'duracion_meses', 'actividades.0.fecha_fin', 'documento']);

        $this->como('alumno@portal.local')
            ->post(route('tesis-proyecto-tesis.alumno.proyecto.guardar'), $this->datos(['actividades' => [], 'documento' => null]))
            ->assertSessionHasErrors(['actividades', 'documento']);

        $this->assertSame(0, Proyecto::count());
    }

    public function test_coautor_inexistente_o_uno_mismo_es_rechazado(): void
    {
        $this->como('alumno@portal.local')->post(route('tesis-proyecto-tesis.alumno.proyecto.guardar'), $this->datos(['coautor_codigo' => '9999999']))
            ->assertSessionHasErrors('coautor_codigo');
        $this->como('alumno@portal.local')->post(route('tesis-proyecto-tesis.alumno.proyecto.guardar'), $this->datos(['coautor_codigo' => '1027004521']))
            ->assertSessionHasErrors('coautor_codigo');

        $this->assertSame(0, Proyecto::count());
    }

    public function test_estudiante_con_proyecto_activo_no_puede_registrar_otro_ni_ser_coautor(): void
    {
        $this->proyecto(['alumno2@portal.local']);

        $this->como('alumno@portal.local')->post(route('tesis-proyecto-tesis.alumno.proyecto.guardar'), $this->datos())
            ->assertSessionHasErrors(['coautor_codigo' => 'El coautor ya tiene un proyecto de tesis activo.']);

        $this->como('alumno2@portal.local')->post(route('tesis-proyecto-tesis.alumno.proyecto.guardar'), $this->datos(['coautor_codigo' => '']))
            ->assertSessionHasErrors('titulo');

        $this->como('alumno2@portal.local')->get(route('tesis-proyecto-tesis.alumno.proyecto.registrar'))
            ->assertRedirect(route('tesis-proyecto-tesis.alumno.panel'));

        $this->assertSame(1, Proyecto::count());
    }

    public function test_proyecto_finalizado_permite_registrar_uno_nuevo(): void
    {
        $this->proyecto(['alumno@portal.local'], Estados::TESIS_APROBADA, Estados::ETAPA_TESIS);

        $this->como('alumno@portal.local')->post(route('tesis-proyecto-tesis.alumno.proyecto.guardar'), $this->datos(['coautor_codigo' => '']))
            ->assertSessionHasNoErrors();
    }

    public function test_busqueda_de_coautor_devuelve_solo_datos_minimos(): void
    {
        $this->como('alumno@portal.local')->getJson(route('tesis-proyecto-tesis.alumno.coautor', ['codigo' => '1027004588']))
            ->assertOk()
            ->assertExactJson(['encontrado' => true, 'nombre' => 'Diego Salazar Vega', 'codigo' => '1027004588', 'disponible' => true, 'mensaje' => 'Disponible para integrar el proyecto.']);

        $this->como('alumno@portal.local')->getJson(route('tesis-proyecto-tesis.alumno.coautor', ['codigo' => '1027004521']))->assertNotFound();
        $this->como('alumno@portal.local')->getJson(route('tesis-proyecto-tesis.alumno.coautor', ['codigo' => "1' OR 1=1"]))->assertStatus(422);
    }

    public function test_solo_el_rol_alumno_accede_a_su_carril(): void
    {
        foreach (['director@portal.local', 'secretaria@portal.local', 'asesor@portal.local'] as $correo) {
            $this->como($correo)->get(route('tesis-proyecto-tesis.alumno.panel'))->assertForbidden();
            $this->como($correo)->post(route('tesis-proyecto-tesis.alumno.proyecto.guardar'), $this->datos())->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->get(route('tesis-proyecto-tesis.alumno.panel'))->assertRedirect('/login');
        $this->assertSame(0, Proyecto::count());
    }

    public function test_alumno_solo_ve_su_propio_proyecto(): void
    {
        $ajeno = $this->proyecto(['alumno2@portal.local']);

        $this->como('alumno@portal.local')->get(route('tesis-proyecto-tesis.alumno.panel'))
            ->assertOk()->assertDontSee($ajeno->codigo);
        $this->como('alumno@portal.local')->get(route('tesis-proyecto-tesis.archivos.descargar', ['proyecto', $ajeno->uuid]))
            ->assertNotFound(); // sin documento
        $this->assertFalse($this->user('alumno@portal.local')->can('ver', $ajeno));
    }

    public function test_panel_muestra_pasos_historial_y_estado(): void
    {
        $p = $this->proyecto(['alumno@portal.local'], Estados::CON_ASESOR, asesor: 'asesor@portal.local');

        $this->como('alumno@portal.local')->get(route('tesis-proyecto-tesis.alumno.panel'))
            ->assertOk()
            ->assertSee($p->codigo)
            ->assertSee('Avance del proceso')
            ->assertSee('Con asesor')
            ->assertSee('Mg. Carlos Medina Ruiz');
    }
}
