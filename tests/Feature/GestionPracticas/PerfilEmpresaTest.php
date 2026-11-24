<?php

namespace Tests\Feature\GestionPracticas;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** La empresa mantiene su perfil y la contraseña con la que inicia sesión. */
final class PerfilEmpresaTest extends PracticasTestCase
{
    private const CORREO = 'practicas.empresa@portal.local';

    public function test_el_perfil_ofrece_el_cambio_de_contrasena(): void
    {
        $this->como('empresa')->get(route('gestion-practicas.empresa.perfil.edit'))
            ->assertOk()->assertSee('Cambiar contraseña')->assertSee('Contraseña actual');
    }

    public function test_la_empresa_cambia_su_contrasena_y_se_actualizan_ambas_cuentas(): void
    {
        DB::table('seguridad.usuario')->where('correo_institucional', self::CORREO)->update(['debe_cambiar_contrasena' => true]);

        $this->como('empresa')->get(route('gestion-practicas.empresa.perfil.edit'))->assertSee('Cambie la contraseña inicial');

        $this->put(route('gestion-practicas.empresa.perfil.password'), [
            'current_password' => 'Portal2026!', 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026',
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertTrue(Hash::check('NuevaClave2026', User::where('email', self::CORREO)->value('password')));

        $dominio = DB::table('seguridad.usuario')->where('correo_institucional', self::CORREO)->first();
        $this->assertTrue(Hash::check('NuevaClave2026', $dominio->contrasena_hash));
        $this->assertFalse((bool) $dominio->debe_cambiar_contrasena);

        $this->get(route('gestion-practicas.empresa.perfil.edit'))->assertDontSee('Cambie la contraseña inicial');
    }

    public function test_exige_la_contrasena_actual_correcta(): void
    {
        $this->como('empresa')->put(route('gestion-practicas.empresa.perfil.password'), [
            'current_password' => 'incorrecta', 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('Portal2026!', User::where('email', self::CORREO)->value('password')));
    }

    public function test_la_confirmacion_debe_coincidir_y_la_nueva_debe_ser_distinta(): void
    {
        $this->como('empresa')->put(route('gestion-practicas.empresa.perfil.password'), [
            'current_password' => 'Portal2026!', 'password' => 'NuevaClave2026', 'password_confirmation' => 'OtraClave2026',
        ])->assertSessionHasErrors('password');

        $this->put(route('gestion-practicas.empresa.perfil.password'), [
            'current_password' => 'Portal2026!', 'password' => 'Portal2026!', 'password_confirmation' => 'Portal2026!',
        ])->assertSessionHasErrors('password');

        $this->put(route('gestion-practicas.empresa.perfil.password'), [
            'current_password' => 'Portal2026!', 'password' => 'corta', 'password_confirmation' => 'corta',
        ])->assertSessionHasErrors('password');
    }

    public function test_solo_la_empresa_cambia_su_contrasena_desde_este_perfil(): void
    {
        $this->como('estudiante')->put(route('gestion-practicas.empresa.perfil.password'), [
            'current_password' => 'Portal2026!', 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026',
        ])->assertForbidden();
    }
}
