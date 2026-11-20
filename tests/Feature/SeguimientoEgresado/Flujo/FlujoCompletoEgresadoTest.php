<?php

namespace Tests\Feature\SeguimientoEgresado\Flujo;

use App\Modules\SeguimientoEgresado\Database\Seeders\DatosPruebaSeeder;
use App\Modules\SeguimientoEgresado\Models\CampaniaSeguimiento;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\UsuarioInterno;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\FabricaSeguimiento;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/**
 * Recorrido completo con las migraciones y los seeders del proyecto (SQLite por defecto): la Secretaría emite las
 * invitaciones, el egresado abre su enlace, responde la encuesta y la respuesta se guarda. La lista del historial
 * usa vistas de PostgreSQL y se comprueba en FlujoCompletoPostgresTest; aquí se abre el detalle de la respuesta.
 */
class FlujoCompletoEgresadoTest extends TestCase
{
    use ComoPersonal, FabricaSeguimiento, UsaBaseDeDatos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->secretaria());
        $this->seed(DatosPruebaSeeder::class);
    }

    public function test_desde_la_emision_hasta_la_respuesta_guardada(): void
    {
        $campania = CampaniaSeguimiento::create([
            'nombre' => 'Seguimiento de prueba', 'tipo' => 'EGRESADOS', 'finalidad' => 'SEGUIMIENTO', 'periodo' => '2026-II',
            'fecha_inicio' => now()->subDays(5)->toDateString(), 'fecha_fin' => now()->addDays(30)->toDateString(),
            'estado' => 'ABIERTA', 'id_usuario_creacion' => UsuarioInterno::idActual(),
        ]);
        $campania->cohortes()->attach(Cohorte::where('anio_ingreso', 2019)->value('id'));

        // 1. Emisión: un enlace por egresado.
        $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $campania->id))->assertRedirect();
        $enlace = session('enlaces_emitidos')[0];
        $egresado = (int) DB::table('seguimiento_egresado.egresado')->where('codigo_matricula', $enlace['matricula'])->value('id');

        // 2. El egresado abre su enlace y entra a su área (el inicio y la lista del historial leen una vista de PostgreSQL).
        $this->get($enlace['url'])->assertRedirect(route('seguimiento-egresado.egresado.inicio'));
        // La página trae la validación en vivo de los campos con formato (celular, correo y contacto del jefe).
        $this->get(route('seguimiento-egresado.egresado.encuesta'))->assertOk()->assertSee('data-valida="jefe"', false)->assertSee('data-valida="celular"', false);

        // 3. Responde y queda guardado.
        $datos = $this->datosDeEncuesta(['brechas' => ['CLOUD'], 'fortalezas' => [], 'detalle_brecha_otra' => null])
            + ['contacto' => ['correo_personal' => 'respuesta@correo.test', 'celular_whatsapp' => '+51 900 123 456']];
        $this->post(route('seguimiento-egresado.egresado.encuesta.responder'), $datos)->assertRedirect(route('seguimiento-egresado.egresado.historial'));

        $invitacion = DB::table('seguimiento_egresado.invitacion_egresado')->where('id_egresado', $egresado)->where('id_campania', $campania->id)->first();
        $this->assertSame('COMPLETADA', $invitacion->estado);
        $medicion = DB::table('seguimiento_egresado.medicion_laboral')->where('id_invitacion', $invitacion->id)->first();
        $this->assertNotNull($medicion);
        $this->assertSame(1, DB::table('seguimiento_egresado.satisfaccion_egresado')->where('id_medicion', $medicion->id)->count());
        $this->assertSame(1, DB::table('seguimiento_egresado.formacion_posterior')->where('id_medicion_origen', $medicion->id)->count());
        $this->assertSame('respuesta@correo.test', DB::table('seguimiento_egresado.contacto_egresado')->where('id_egresado', $egresado)->whereNull('vigente_hasta')->value('correo_personal'));

        // 4. El detalle de la respuesta está disponible y no se puede responder otra vez.
        $this->get(route('seguimiento-egresado.egresado.historial.detalle', $medicion->id))->assertOk()->assertSee('Empresa Ficticia S.A.C.');
        $this->get(route('seguimiento-egresado.egresado.encuesta'))->assertRedirect(route('seguimiento-egresado.egresado.historial'));
        $this->post(route('seguimiento-egresado.egresado.encuesta.responder'), $datos)->assertRedirect();
        $this->assertSame(1, DB::table('seguimiento_egresado.medicion_laboral')->where('id_invitacion', $invitacion->id)->count());
    }
}
