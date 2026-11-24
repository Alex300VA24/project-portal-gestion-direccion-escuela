<?php

namespace Tests\Feature\SeguimientoEgresado;

use App\Modules\SeguimientoEgresado\Models\CampaniaSeguimiento;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Services\PorConvocar;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeguimientoEgresado\FabricaEmpresasIndicadores;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/** Las campañas dicen cuántos egresados faltan por invitar, no solo cuántos de los ya invitados respondieron. */
class PorConvocarTest extends TestCase
{
    use FabricaEmpresasIndicadores, UsaBaseDeDatos;

    private function conContacto(int $egresado): void
    {
        Egresado::findOrFail($egresado)->contactos()->create([
            'correo_personal' => 'p'.$egresado.'@correo.test', 'celular_whatsapp' => '900111222',
            'vigente_desde' => now(), 'origen_registro' => 'FORMULARIO',
        ]);
    }

    /**
     * Cuatro egresados con correo (uno ya invitado), uno sin contacto y un apto que todavía no egresó: solo tres se
     * pueden convocar de verdad, y de ellos uno ya tiene invitación.
     *
     * @return array{campania: int, invitado: int}
     */
    private function campaniaConGenteSinInvitar(array $campania = []): array
    {
        $promocion = $this->cohorte(10, 2019);
        $id = $this->campania(array_merge(['tipo' => 'EGRESADOS', 'nombre' => 'Seguimiento 2026-II'], $campania));
        DB::table('seguimiento_egresado.campania_cohorte')->insert(['id_campania' => $id, 'id_cohorte' => $promocion]);

        $invitado = $this->egresado($promocion);
        $this->conContacto($invitado);
        foreach ([1, 2] as $_) {
            $this->conContacto($this->egresado($promocion));
        }
        $this->egresado($promocion);                                   // egresó, pero no dejó su contacto
        $this->conContacto($this->egresado($promocion, 'PROXIMO'));    // tiene contacto, pero todavía no egresó
        $this->invitacionSeguimiento($id, $invitado);

        return ['campania' => $id, 'invitado' => $invitado];
    }

    public function test_cuenta_a_los_egresados_con_correo_que_aun_no_tienen_invitacion(): void
    {
        $c = $this->campaniaConGenteSinInvitar();

        $this->assertSame(2, app(PorConvocar::class)->de(CampaniaSeguimiento::with('cohortes')->findOrFail($c['campania'])));
    }

    public function test_no_aplica_a_campanias_de_empleadores_ni_a_las_que_ya_cerraron(): void
    {
        $empresas = $this->campaniaConGenteSinInvitar(['tipo' => 'EMPRESAS']);
        $cerrada = $this->campaniaConGenteSinInvitar(['estado' => 'CERRADA']);

        $this->assertNull(app(PorConvocar::class)->de(CampaniaSeguimiento::with('cohortes')->findOrFail($empresas['campania'])));
        $this->assertNull(app(PorConvocar::class)->de(CampaniaSeguimiento::with('cohortes')->findOrFail($cerrada['campania'])));
    }

    public function test_la_lista_y_el_detalle_muestran_cuantos_faltan_por_invitar(): void
    {
        $this->entrarComo();
        $c = $this->campaniaConGenteSinInvitar();

        $this->get(route('seguimiento-egresado.campanias'))->assertOk()
            ->assertSee('0 de 1')->assertSee('2 sin invitar');
        $this->get(route('seguimiento-egresado.campanias.detalle', $c['campania']))->assertOk()
            ->assertSee('Hay 2 egresados confirmados')->assertSee('todavía no recibieron su invitación');
    }

    public function test_una_campania_nueva_no_dice_0_de_0_sino_que_faltan_por_convocar(): void
    {
        $this->entrarComo();
        $promocion = $this->cohorte(10, 2019);
        $id = $this->campania(['tipo' => 'EGRESADOS', 'nombre' => 'Campaña nueva']);
        DB::table('seguimiento_egresado.campania_cohorte')->insert(['id_campania' => $id, 'id_cohorte' => $promocion]);
        foreach ([1, 2, 3] as $_) {
            $this->conContacto($this->egresado($promocion));
        }

        $this->get(route('seguimiento-egresado.campanias'))->assertOk()
            ->assertSee('Aún sin invitaciones · 3 por convocar')->assertDontSee('0 de 0');
        $this->get(route('seguimiento-egresado.campanias.detalle', $id))->assertOk()
            ->assertSee('Aún no se enviaron invitaciones')->assertSee('Hay 3 egresados confirmados');
    }

    public function test_sin_nadie_por_invitar_no_aparece_el_aviso(): void
    {
        $this->entrarComo();
        $promocion = $this->cohorte(10, 2019);
        $id = $this->campania(['tipo' => 'EGRESADOS', 'nombre' => 'Todos invitados']);
        DB::table('seguimiento_egresado.campania_cohorte')->insert(['id_campania' => $id, 'id_cohorte' => $promocion]);
        $egresado = $this->egresado($promocion);
        $this->conContacto($egresado);
        $this->invitacionSeguimiento($id, $egresado);

        $this->get(route('seguimiento-egresado.campanias'))->assertOk()->assertSee('0 de 1')->assertDontSee('sin invitar');
        $this->get(route('seguimiento-egresado.campanias.detalle', $id))->assertOk()->assertDontSee('todavía no recibieron');
    }

    public function test_el_inicio_tambien_lo_dice(): void
    {
        $this->entrarComo();
        $this->campaniaConGenteSinInvitar();

        $this->get(route('seguimiento-egresado.index'))->assertOk()->assertSee('2 sin invitar');
    }
}
