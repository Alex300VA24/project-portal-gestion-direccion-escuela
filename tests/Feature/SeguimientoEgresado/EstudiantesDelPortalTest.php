<?php

namespace Tests\Feature\SeguimientoEgresado;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SeguimientoEgresado\FabricaEmpresasIndicadores;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/** «Elegir de los estudiantes del portal»: segunda forma, opcional, de cargar alumnos a una promoción. */
class EstudiantesDelPortalTest extends TestCase
{
    use FabricaEmpresasIndicadores, UsaBaseDeDatos;

    private array $nucleo = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('core.estudiante')) {
            $this->markTestSkipped('Requiere el esquema compartido core.');
        }
    }

    /** Facultad, escuela, plan, modalidad y un periodo de ingreso por año: lo mínimo que exige core.estudiante. */
    private function periodo(int $anio): int
    {
        if (! isset($this->nucleo['escuela'])) {
            $facultad = DB::table('core.facultad')->insertGetId(['codigo' => 'T-FAC', 'nombre' => 'Facultad de pruebas'], 'id_facultad');
            $this->nucleo['escuela'] = DB::table('core.escuela_profesional')->insertGetId(['id_facultad' => $facultad, 'codigo' => 'T-EP', 'nombre' => 'Escuela de pruebas', 'estado' => 'ACTIVA'], 'id_escuela');
            $this->nucleo['plan'] = DB::table('core.plan_estudios')->insertGetId([
                'id_escuela' => $this->nucleo['escuela'], 'codigo' => 'T-PLAN', 'nombre' => 'Plan de pruebas', 'version' => '1',
                'anio_aprobacion' => 2017, 'fecha_vigencia_inicio' => '2017-01-01', 'es_predeterminado' => true, 'estado' => 'VIGENTE',
            ], 'id_plan');
            $this->nucleo['modalidad'] = DB::table('core.modalidad_ingreso')->insertGetId(['codigo' => 'T-MOD', 'nombre' => 'Modalidad de pruebas'], 'id_modalidad');
        }

        return $this->nucleo['periodo'][$anio] ??= DB::table('core.periodo_academico')->insertGetId([
            'codigo' => $anio.'-T', 'fecha_inicio' => $anio.'-03-01', 'fecha_fin' => $anio.'-07-31', 'estado' => 'CERRADO',
            'anio' => $anio, 'tipo' => 'REGULAR', 'numero_semanas' => 17,
        ], 'id_periodo');
    }

    /** @return array{id: int, matricula: string} */
    private function estudiante(int $anio, string $nombres, string $apellido, ?string $correo = null, ?string $documento = null): array
    {
        $n = ++$this->secuencia;
        $documento ??= '8'.str_pad((string) $n, 7, '0', STR_PAD_LEFT);
        $periodo = $this->periodo($anio);
        $persona = DB::table('core.persona')->insertGetId([
            'tipo_documento' => 'DNI', 'numero_documento' => $documento, 'nombres' => $nombres,
            'apellido_paterno' => $apellido, 'apellido_materno' => 'Lara', 'correo_personal' => $correo, 'creado_en' => now(),
        ], 'id_persona');
        $matricula = $anio.'9'.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
        $id = DB::table('core.estudiante')->insertGetId([
            'codigo_universitario' => $matricula, 'estado' => 'ACTIVO', 'id_persona' => $persona, 'id_escuela' => $this->nucleo['escuela'],
            'id_plan' => $this->nucleo['plan'], 'id_periodo_ingreso' => $periodo, 'id_modalidad_ingreso' => $this->nucleo['modalidad'],
        ], 'id_estudiante');

        return ['id' => (int) $id, 'matricula' => $matricula];
    }

    public function test_la_lista_trae_solo_a_quienes_ingresaron_ese_ano_y_faltan_en_el_padron(): void
    {
        $this->entrarComo();
        $promocion = $this->cohorte(10, 2019);
        $this->estudiante(2019, 'Lucía', 'Vega', 'lucia@correo.test');
        $this->estudiante(2018, 'Mateo', 'Rojas', 'mateo@correo.test');                       // otro año de ingreso
        $yaEstaPorMatricula = $this->estudiante(2019, 'Camila', 'Torres', 'camila@correo.test');
        $yaEstaPorDocumento = $this->estudiante(2019, 'Diego', 'Castillo', 'diego@correo.test', '81111111');
        $this->egresado($promocion, 'PROXIMO', ['codigo_matricula' => $yaEstaPorMatricula['matricula']]);
        $this->egresado($promocion, 'PROXIMO', ['numero_documento' => '81111111']);
        $this->estudiante(2019, 'Inés', 'Salas');                                            // sin correo

        $json = $this->getJson(route('seguimiento-egresado.padron.estudiantes-del-portal', ['promocion' => $promocion]))
            ->assertOk()->assertJsonPath('disponible', true)->assertJsonPath('total', 2)->json();

        $nombres = collect($json['estudiantes'])->pluck('nombre')->all();
        $this->assertSame(['Salas Lara, Inés', 'Vega Lara, Lucía'], $nombres);
        $this->assertSame([false, true], collect($json['estudiantes'])->pluck('puede_agregarse')->all(), 'quien no tiene correo no se puede agregar');
        $this->assertNotNull($yaEstaPorDocumento);
    }

    public function test_el_buscador_acota_por_nombre_matricula_o_documento(): void
    {
        $this->entrarComo();
        $promocion = $this->cohorte(10, 2019);
        $lucia = $this->estudiante(2019, 'Lucía', 'Vega', 'lucia@correo.test');
        $this->estudiante(2019, 'Mateo', 'Rojas', 'mateo@correo.test');

        $porNombre = $this->getJson(route('seguimiento-egresado.padron.estudiantes-del-portal', ['promocion' => $promocion, 'q' => 'vega']))->json('estudiantes');
        $porMatricula = $this->getJson(route('seguimiento-egresado.padron.estudiantes-del-portal', ['promocion' => $promocion, 'q' => $lucia['matricula']]))->json('estudiantes');
        $sinResultado = $this->getJson(route('seguimiento-egresado.padron.estudiantes-del-portal', ['promocion' => $promocion, 'q' => 'zzz']))->json('estudiantes');

        $this->assertCount(1, $porNombre);
        $this->assertCount(1, $porMatricula);
        $this->assertSame([], $sinResultado);
    }

    public function test_agregar_crea_a_los_alumnos_como_aptos_con_su_carga_en_el_historial(): void
    {
        $this->entrarComo();
        $promocion = $this->cohorte(10, 2019);
        $lucia = $this->estudiante(2019, 'Lucía', 'Vega', 'lucia@correo.test');
        $mateo = $this->estudiante(2019, 'Mateo', 'Rojas', 'mateo@correo.test');

        $this->post(route('seguimiento-egresado.padron.importar-estudiantes'), ['promocion' => $promocion, 'estudiantes' => [$lucia['id'], $mateo['id']]])
            ->assertRedirect()->assertSessionHas('success');

        $alumno = DB::table('seguimiento_egresado.egresado')->where('codigo_matricula', $lucia['matricula'])->first();
        $this->assertNotNull($alumno);
        $this->assertSame($promocion, (int) $alumno->id_cohorte);
        $this->assertSame('Vega', $alumno->apellido_paterno);
        $this->assertSame('lucia@correo.test', $alumno->correo_institucional, 'sin correo institucional, el enlace sale al correo personal');
        $this->assertSame('PROXIMO', DB::table('seguimiento_egresado.historial_estado_academico as h')
            ->join('seguimiento_egresado.cat_estado_academico as c', 'c.id', '=', 'h.id_estado_academico')
            ->where('h.id_egresado', $alumno->id)->whereNull('h.vigente_hasta')->value('c.codigo'));
        $this->assertSame(2, DB::table('seguimiento_egresado.egresado')->where('id_cohorte', $promocion)->count());

        $carga = DB::table('seguimiento_egresado.importacion_padron')->orderByDesc('id')->first();
        $this->assertSame('Estudiantes del portal', $carga->nombre_archivo);
        $this->assertSame('MANUAL', $carga->origen);
        $this->assertSame('VALIDADA', $carga->estado);
    }

    public function test_repetir_la_seleccion_no_duplica_y_lo_dice(): void
    {
        $this->entrarComo();
        $promocion = $this->cohorte(10, 2019);
        $lucia = $this->estudiante(2019, 'Lucía', 'Vega', 'lucia@correo.test');
        $datos = ['promocion' => $promocion, 'estudiantes' => [$lucia['id']]];

        $this->post(route('seguimiento-egresado.padron.importar-estudiantes'), $datos)->assertSessionHas('success');
        $this->post(route('seguimiento-egresado.padron.importar-estudiantes'), $datos)->assertSessionHas('error');

        $this->assertSame(1, DB::table('seguimiento_egresado.egresado')->where('id_cohorte', $promocion)->count());
    }

    public function test_quien_no_tiene_correo_se_omite_y_se_avisa(): void
    {
        $this->entrarComo();
        $promocion = $this->cohorte(10, 2019);
        $lucia = $this->estudiante(2019, 'Lucía', 'Vega', 'lucia@correo.test');
        $ines = $this->estudiante(2019, 'Inés', 'Salas');

        $this->post(route('seguimiento-egresado.padron.importar-estudiantes'), ['promocion' => $promocion, 'estudiantes' => [$lucia['id'], $ines['id']]])
            ->assertSessionHas('success', fn (string $m) => str_contains($m, 'Se omitió 1 estudiante'));

        $this->assertSame(1, DB::table('seguimiento_egresado.egresado')->where('id_cohorte', $promocion)->count());
    }

    public function test_sin_estudiantes_la_lista_sale_vacia_y_no_se_rompe_nada(): void
    {
        $this->entrarComo();
        $promocion = $this->cohorte(10, 2031);

        $this->getJson(route('seguimiento-egresado.padron.estudiantes-del-portal', ['promocion' => $promocion]))
            ->assertOk()->assertJsonPath('total', 0)->assertJsonPath('estudiantes', []);
        $this->post(route('seguimiento-egresado.padron.importar-estudiantes'), ['promocion' => $promocion, 'estudiantes' => [999999]])
            ->assertSessionHas('error');
    }

    public function test_la_promocion_ofrece_la_opcion_solo_a_la_secretaria_y_la_carga_por_archivo_sigue_ahi(): void
    {
        $promocion = $this->cohorte(10, 2019);

        $this->entrarComo();
        $this->get(route('seguimiento-egresado.padron.promocion', $promocion))->assertOk()
            ->assertSee('Elegir de los estudiantes del portal')->assertSee('Cargar alumnos')->assertSee('Descargar el formato modelo');

        $this->entrarComo('DIRECTOR');
        $this->get(route('seguimiento-egresado.padron.promocion', $promocion))->assertOk()
            ->assertDontSee('Elegir de los estudiantes del portal');
        $this->getJson(route('seguimiento-egresado.padron.estudiantes-del-portal', ['promocion' => $promocion]))->assertForbidden();
        $this->post(route('seguimiento-egresado.padron.importar-estudiantes'), ['promocion' => $promocion, 'estudiantes' => [1]])->assertForbidden();
    }
}
