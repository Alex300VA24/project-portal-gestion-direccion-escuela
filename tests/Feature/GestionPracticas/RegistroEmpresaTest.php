<?php

namespace Tests\Feature\GestionPracticas;

use Illuminate\Support\Facades\DB;

/** El administrador registra empresas con datos válidos. */
final class RegistroEmpresaTest extends PracticasTestCase
{
    /** @param array<string, string> $cambios */
    private function datos(array $cambios = []): array
    {
        return array_merge([
            'ruc' => '20987654321', 'razon_social' => 'Soluciones Andinas S.A.C.', 'contacto_dni' => '71234500',
            'contacto_nombres' => 'Marta', 'contacto_apellido_paterno' => 'Quispe',
            'correo_acceso' => 'rrhh@andinas.test', 'password' => 'Segura2026!',
        ], $cambios);
    }

    public function test_acepta_un_ruc_de_empresa_que_comienza_con_20(): void
    {
        $this->como('admin')->post(route('gestion-practicas.empresas.store'), $this->datos())
            ->assertSessionHasNoErrors()->assertRedirect(route('gestion-practicas.empresas.index'));

        $this->assertTrue(DB::table('comun.empresa')->where('ruc', '20987654321')->exists());
    }

    public function test_rechaza_un_ruc_que_no_es_de_empresa(): void
    {
        foreach (['10123456789', '80369852147', '15987654321'] as $ruc) {
            $this->como('admin')->post(route('gestion-practicas.empresas.store'), $this->datos(['ruc' => $ruc]))
                ->assertSessionHasErrors('ruc');

            $this->assertFalse(DB::table('comun.empresa')->where('ruc', $ruc)->exists(), "Se registró el RUC {$ruc}");
        }
    }

    public function test_rechaza_un_ruc_con_una_cantidad_de_digitos_incorrecta_o_con_letras(): void
    {
        foreach (['2098765432', '209876543210', '20987A54321', ''] as $ruc) {
            $this->como('admin')->post(route('gestion-practicas.empresas.store'), $this->datos(['ruc' => $ruc]))
                ->assertSessionHasErrors('ruc');
        }
    }

    public function test_un_solo_mensaje_por_ruc_invalido(): void
    {
        $respuesta = $this->como('admin')->post(route('gestion-practicas.empresas.store'), $this->datos(['ruc' => '1369852147']));

        $mensajes = $respuesta->baseResponse->getSession()->get('errors')['default']['messages']['ruc'];

        $this->assertCount(1, $mensajes);
    }

    public function test_no_registra_dos_veces_el_mismo_ruc(): void
    {
        $this->como('admin')->post(route('gestion-practicas.empresas.store'), $this->datos())->assertSessionHasNoErrors();

        $this->post(route('gestion-practicas.empresas.store'), $this->datos(['correo_acceso' => 'otra@andinas.test', 'contacto_dni' => '71234501']))
            ->assertSessionHasErrors('ruc');
    }
}
