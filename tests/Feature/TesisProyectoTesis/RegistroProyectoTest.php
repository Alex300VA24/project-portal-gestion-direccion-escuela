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
            'diseno' => 'Pre-experimental',
            'lugar_ejecucion' => 'Escuela de Ingeniería Informática',
            'institucion' => 'Universidad Nacional de Trujillo',
            'financiamiento' => 'Autofinanciado',
            'coautor_codigo' => '1027004588',
            'actividades' => [
                ['descripcion' => 'Revisión bibliográfica', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-30', 'horas' => 8],
                ['descripcion' => 'Desarrollo del prototipo', 'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2027-02-28', 'horas' => 12],
                ['descripcion' => '', 'fecha_inicio' => '', 'fecha_fin' => '', 'horas' => ''],
            ],
            'recursos' => [
                ['tipo' => 'Bienes', 'descripcion' => 'Papel bond A4', 'codigo' => '2.3.1.5.1 · Materiales y útiles de oficina', 'unidad' => 'Millar', 'cantidad' => '2', 'costo' => '28.25'],
                ['tipo' => 'Bienes', 'descripcion' => 'Laptop propia', 'codigo' => '– · Sin código (bien propio a costo 0)', 'unidad' => 'Unidad', 'cantidad' => '1', 'costo' => '0'],
                ['tipo' => 'Servicios', 'descripcion' => 'Internet', 'codigo' => '2.3.2.2.2', 'unidad' => 'Mes', 'cantidad' => '6', 'costo' => '10.00'],
                ['tipo' => 'Bienes', 'descripcion' => '', 'codigo' => '', 'unidad' => '', 'cantidad' => '', 'costo' => ''],
            ],
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
        $this->assertSame(2, $p->actividades()->count(), 'Las filas vacías no se guardan.');
        $this->assertSame(6, (int) $p->duracion_meses, 'Duración calculada del cronograma (01/09/2026 al 28/02/2027).');
        $this->assertSame('Pre-experimental', $p->diseno);
        $this->assertSame(3, $p->recursos()->count(), 'Una fila = un recurso; las vacías no se guardan.');
        $partidas = $p->partidas()->orderBy('id_partida')->get();
        $this->assertSame(['2.3.1.5.1', null, '2.3.2.2.2'], $partidas->pluck('codigo_clasificador')->all(), 'Se guarda solo el código; «–» queda sin código.');
        $this->assertSame(['Millar', 'Unidad', 'Mes'], $partidas->pluck('unidad')->all());
        $this->assertTrue($partidas->every(fn ($x) => $x->id_recurso !== null), 'Cada partida está ligada a su recurso.');
        $this->assertEqualsWithDelta(116.5, $partidas->sum(fn ($x) => $x->monto()), 0.001);
        $this->assertSame(1, $p->colaboradores()->count());
        $this->assertDatabaseHas('tesis.historial_estado', ['id_proyecto' => $p->id_proyecto, 'estado_anterior' => null, 'estado_nuevo' => 'Registrado']);
        $this->assertSame(1, DB::table('comun.notificacion')->where('id_usuario_destino', $this->idUsuario('alumno2@portal.local'))->count());
        $this->assertSame(1, DB::table('comun.notificacion')->where('id_usuario_destino', $this->idUsuario('director@portal.local'))->count());

        $this->como('alumno2@portal.local')->get(route('tesis-proyecto-tesis.alumno.panel'))->assertOk()->assertSee($p->codigo)->assertSee('Lucía Ramírez Torres');
        $this->como('alumno@portal.local')->get(route('tesis-proyecto-tesis.alumno.proyecto'))->assertOk()->assertSee('Revisión bibliográfica')->assertSee('S/ 56.50')->assertSee('S/ 60.00')->assertSee('S/ 116.50')->assertSee('Materiales y útiles de oficina')->assertSee('6 meses');
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
                'diseno' => 'Otro',
                'actividades' => [['descripcion' => 'Etapa', 'fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-01', 'horas' => '']],
                'recursos' => [['tipo' => 'Local', 'descripcion' => 'Aula', 'codigo' => 'abc', 'unidad' => '', 'cantidad' => '0', 'costo' => '-1']],
                'documento' => \Illuminate\Http\UploadedFile::fake()->create('f1.docx', 10, 'application/msword'),
            ]))
            ->assertSessionHasErrors(['titulo', 'linea', 'enfoque', 'diseno_otro', 'actividades.0.fecha_fin', 'actividades.0.horas', 'documento',
                'recursos.0.tipo', 'recursos.0.codigo', 'recursos.0.unidad', 'recursos.0.cantidad', 'recursos.0.costo']);

        // El cronograma no puede superar el máximo de meses.
        $this->como('alumno@portal.local')
            ->post(route('tesis-proyecto-tesis.alumno.proyecto.guardar'), $this->datos(['actividades' => [
                ['descripcion' => 'Todo', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2029-06-30', 'horas' => 5],
            ]]))
            ->assertSessionHasErrors(['actividades' => 'El cronograma dura 42 meses; el máximo es 36. Revisa las fechas de inicio y término.']);

        $this->como('alumno@portal.local')
            ->post(route('tesis-proyecto-tesis.alumno.proyecto.guardar'), $this->datos(['actividades' => [], 'documento' => null]))
            ->assertSessionHasErrors(['actividades', 'documento']);

        $this->assertSame(0, Proyecto::count());

        // «Otro» diseño se guarda con su descripción.
        $this->como('alumno@portal.local')
            ->post(route('tesis-proyecto-tesis.alumno.proyecto.guardar'), $this->datos(['coautor_codigo' => '', 'diseno' => 'Otro', 'diseno_otro' => 'Investigación-acción']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Otro: Investigación-acción', Proyecto::query()->latest('id_proyecto')->first()->diseno);
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
