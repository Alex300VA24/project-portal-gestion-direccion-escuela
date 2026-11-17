<?php

namespace Tests\Feature\SeguimientoEgresado\Seeders;

use App\Modules\SeguimientoEgresado\Database\Seeders\DatosPruebaSeeder;
use App\Modules\SeguimientoEgresado\Database\Seeders\EgresadosDemoSeeder;
use App\Modules\SeguimientoEgresado\Mail\InvitacionEgresadoMail;
use App\Modules\SeguimientoEgresado\Services\Accesos\EnlaceInvitacion;
use App\Modules\SeguimientoEgresado\Services\Accesos\InvitacionDb;
use App\Modules\SeguimientoEgresado\Services\Egresado\HistorialService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/** Los tres casos de demostración que carga el seeder del módulo: contacto pendiente, encuesta pendiente y todo completo. */
class EgresadosDemoSeederTest extends TestCase
{
    use ComoPersonal, UsaBaseDeDatos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatosPruebaSeeder::class);
        $this->seed(EgresadosDemoSeeder::class);
    }

    private function egresado(string $matricula): object
    {
        return DB::table('seguimiento_egresado.egresado')->where('codigo_matricula', $matricula)->first();
    }

    private function invitacionDe(string $token): object
    {
        return DB::table('seguimiento_egresado.invitacion_egresado')->where('token_hash', EnlaceInvitacion::hashDe($token))->first();
    }

    public function test_se_cargan_tres_egresados_con_nombres_inventados(): void
    {
        $nombres = DB::table('seguimiento_egresado.egresado')->whereIn('codigo_matricula', ['2019100231', '2019100187', '2019100094'])
            ->orderBy('codigo_matricula')->get()->map(fn ($e) => "{$e->nombres} {$e->apellido_paterno} {$e->apellido_materno}")->all();

        $this->assertSame(['Camila Sánchez Ulloa', 'Renzo Paredes Villanueva', 'Valeria Quispe Mendoza'], $nombres);
    }

    public function test_caso_1_sin_contacto_y_con_enlace_de_contacto_inicial(): void
    {
        $valeria = $this->egresado('2019100231');
        $invitacion = $this->invitacionDe(EgresadosDemoSeeder::TOKEN_CONTACTO);

        $this->assertSame(0, DB::table('seguimiento_egresado.contacto_egresado')->where('id_egresado', $valeria->id)->count());
        $this->assertSame($valeria->id, $invitacion->id_egresado);
        $this->assertSame('CONTACTO_INICIAL', $invitacion->proposito);
        $this->assertSame('PENDIENTE', $invitacion->estado);
        $this->assertNull($invitacion->id_campania);
        $this->assertNull($invitacion->enviado_en, 'enlace entregado a mano: no consta como enviado por correo');
    }

    public function test_invitar_a_la_cohorte_demo_2020_genera_un_enlace_para_cada_uno_de_los_tres_sin_contacto(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp', 'seguimiento-egresado.correo.enviar' => true]);
        $cohorte = DB::table('seguimiento_egresado.cohorte')->where('codigo_promocion', 'PROM-2020-I')->value('id');
        $this->assertSame(3, DB::table('seguimiento_egresado.egresado')->where('id_cohorte', $cohorte)->count());

        $this->actingAs($this->secretaria())
            ->post(route('seguimiento-egresado.padron.contacto-inicial.cohorte'), ['cohorte' => $cohorte])->assertRedirect();

        $enlaces = collect(session('enlaces_contacto'));
        $this->assertEqualsCanonicalizing(
            ['Daniela Vásquez Lozano', 'Sebastián Alva Rodríguez', 'Marisol Chávez Terrones'],
            $enlaces->pluck('egresado')->all(),
        );
        Mail::assertSent(InvitacionEgresadoMail::class, 3);
    }

    public function test_caso_2_con_contacto_y_encuesta_pendiente(): void
    {
        $renzo = $this->egresado('2019100187');
        $invitacion = $this->invitacionDe(EgresadosDemoSeeder::TOKEN_ENCUESTA);

        $this->assertSame(1, DB::table('seguimiento_egresado.contacto_egresado')->where('id_egresado', $renzo->id)->whereNull('vigente_hasta')->count());
        $this->assertSame('SEGUIMIENTO', $invitacion->proposito);
        $this->assertSame('PENDIENTE', $invitacion->estado);
        $this->assertSame('ABIERTA', DB::table('seguimiento_egresado.campania_seguimiento')->find($invitacion->id_campania)->estado);
        $this->assertSame(0, DB::table('seguimiento_egresado.medicion_laboral')->where('id_invitacion', $invitacion->id)->count());
    }

    public function test_caso_3_todo_completo_y_visible_en_el_historial(): void
    {
        $camila = $this->egresado('2019100094');
        $invitacion = $this->invitacionDe(EgresadosDemoSeeder::TOKEN_HISTORIAL);

        $this->assertSame('COMPLETADA', $invitacion->estado);
        $medicion = DB::table('seguimiento_egresado.medicion_laboral')->where('id_invitacion', $invitacion->id)->first();
        $this->assertNotNull($medicion);
        $this->assertSame(1, DB::table('seguimiento_egresado.satisfaccion_egresado')->where('id_medicion', $medicion->id)->count());
        $this->assertGreaterThanOrEqual(2, DB::table('seguimiento_egresado.medicion_habilidad')->where('id_medicion', $medicion->id)->count());
        $this->assertSame(1, DB::table('seguimiento_egresado.formacion_posterior')->where('id_medicion_origen', $medicion->id)->count());

        $contactos = DB::table('seguimiento_egresado.contacto_egresado')->where('id_egresado', $camila->id)->get();
        $this->assertCount(2, $contactos, 'el contacto tiene una versión anterior y la vigente');
        $this->assertSame(1, $contactos->whereNull('vigente_hasta')->count());

        // La vista de «Mi historial» lee de tablas y vistas de PostgreSQL; aquí se comprueba lo que no depende de ellas.
        $this->assertCount(2, (new HistorialService)->contactos($camila->id));
        $this->assertCount(1, (new HistorialService)->formaciones($camila->id));
    }

    public function test_los_tokens_de_demostracion_sirven_para_abrir_el_enlace(): void
    {
        $enlaces = new EnlaceInvitacion(new InvitacionDb);

        foreach ([EgresadosDemoSeeder::TOKEN_CONTACTO, EgresadosDemoSeeder::TOKEN_ENCUESTA, EgresadosDemoSeeder::TOKEN_HISTORIAL] as $token) {
            $this->assertTrue($enlaces->abrir($token)->esPermitido(), $token);
        }
        $this->assertNull(DB::table('seguimiento_egresado.invitacion_egresado')->where('token_hash', EgresadosDemoSeeder::TOKEN_ENCUESTA)->first(), 'el token en claro no se guarda');
    }

    public function test_ejecutarlo_otra_vez_no_duplica_datos(): void
    {
        $antes = [DB::table('seguimiento_egresado.egresado')->count(), DB::table('seguimiento_egresado.invitacion_egresado')->count(), DB::table('seguimiento_egresado.contacto_egresado')->count(), DB::table('seguimiento_egresado.medicion_laboral')->count(), DB::table('seguimiento_egresado.campania_seguimiento')->count()];

        $this->seed(EgresadosDemoSeeder::class);

        $this->assertSame($antes, [DB::table('seguimiento_egresado.egresado')->count(), DB::table('seguimiento_egresado.invitacion_egresado')->count(), DB::table('seguimiento_egresado.contacto_egresado')->count(), DB::table('seguimiento_egresado.medicion_laboral')->count(), DB::table('seguimiento_egresado.campania_seguimiento')->count()]);
    }

    public function test_emitir_invitaciones_de_la_campania_actual_muestra_los_enlaces_de_los_egresados_sin_invitar(): void
    {
        $this->actingAs($this->secretaria());
        $campania = DB::table('seguimiento_egresado.campania_seguimiento')->where('nombre', 'Seguimiento a egresados 2026-II')->value('id');

        $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $campania));

        $enlaces = collect(session('enlaces_emitidos'));
        // Los tres egresados sin invitar. Valeria sigue como alumna apta (PROXIMO): no se la convoca hasta confirmar su egreso;
        // Renzo y Camila ya estaban invitados.
        $this->assertEqualsCanonicalizing(
            ['2019100142', '2019100158', '2019100203'],
            $enlaces->pluck('matricula')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['Joaquín Rojas Castañeda', 'Fiorella Mendoza Aguilar', 'Luis Alberto Cabrera Neyra'],
            $enlaces->pluck('egresado')->all(),
        );
    }
}
