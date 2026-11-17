<?php

namespace Tests\Feature\SeguimientoEgresado\Accesos;

use App\Modules\SeguimientoEgresado\Models\UsuarioInterno;
use App\Modules\SeguimientoEgresado\Services\Accesos\SesionAcceso;
use App\Modules\SeguimientoEgresado\Support\Accesos\Perfil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/** La identidad del personal sale de la cuenta del portal y de sus roles (Spatie); en el módulo queda un usuario interno enlazado. */
class IdentidadDelPortalTest extends TestCase
{
    use ComoPersonal, UsaBaseDeDatos;

    private function peticion($usuario): Request
    {
        $peticion = Request::create('/');
        $peticion->setLaravelSession($this->app->make('session.store'));
        $peticion->setUserResolver(fn () => $usuario);

        return $peticion;
    }

    public function test_el_rol_secretaria_del_portal_es_el_perfil_secretaria(): void
    {
        $usuario = $this->secretaria();

        $identidad = SesionAcceso::desde($this->peticion($usuario))->interna();

        $this->assertSame(Perfil::Secretaria, $identidad->perfil);
        $this->assertTrue($identidad->puede('padron.gestionar'));
        $this->assertFalse($identidad->puede('institucional.registrar'));
    }

    public function test_el_rol_director_escuela_del_portal_es_el_perfil_director(): void
    {
        $identidad = SesionAcceso::desde($this->peticion($this->director()))->interna();

        $this->assertSame(Perfil::Director, $identidad->perfil);
        $this->assertTrue($identidad->puede('institucional.registrar'));
        $this->assertFalse($identidad->puede('padron.gestionar'));
    }

    public function test_un_usuario_con_otro_rol_o_sin_sesion_no_tiene_identidad_interna(): void
    {
        $this->assertNull(SesionAcceso::desde($this->peticion($this->profesor()))->interna());
        $this->assertNull(SesionAcceso::desde($this->peticion(null))->interna());
    }

    public function test_el_primer_uso_crea_el_usuario_interno_enlazado_y_no_se_duplica(): void
    {
        $usuario = $this->secretaria();

        $primera = SesionAcceso::desde($this->peticion($usuario))->interna();
        $segunda = SesionAcceso::desde($this->peticion($usuario))->interna();

        $this->assertSame($primera->usuarioId, $segunda->usuarioId);
        $fila = DB::table('seguimiento_egresado.usuario_interno')->where('id_usuario_portal', $usuario->id)->get();
        $this->assertCount(1, $fila);
        $this->assertSame($usuario->email, $fila->first()->correo_institucional);
        $this->assertSame(
            DB::table('seguimiento_egresado.rol_interno')->where('codigo', 'SECRETARIA')->value('id'),
            $fila->first()->id_rol,
        );
    }

    public function test_si_tiene_ambos_roles_prevalece_secretaria(): void
    {
        $usuario = $this->secretaria();
        Role::findOrCreate('director_escuela', 'web');
        $usuario->assignRole('director_escuela');

        $this->assertSame(Perfil::Secretaria, SesionAcceso::desde($this->peticion($usuario))->interna()->perfil);
    }

    public function test_la_autoria_de_lo_que_se_registra_es_la_del_usuario_con_sesion(): void
    {
        $usuario = $this->director();
        $this->actingAs($usuario);

        $id = UsuarioInterno::idActual();

        $this->assertSame((int) $id, (int) DB::table('seguimiento_egresado.usuario_interno')->where('id_usuario_portal', $usuario->id)->value('id'));
    }

    public function test_sin_sesion_del_portal_la_autoria_cae_en_el_primer_usuario_activo_del_modulo(): void
    {
        $this->assertSame(
            (int) DB::table('seguimiento_egresado.usuario_interno')->where('activo', true)->orderBy('id')->value('id'),
            UsuarioInterno::idActual(),
        );
    }

    public function test_conviven_la_identidad_del_personal_y_la_del_egresado(): void
    {
        $usuario = $this->secretaria();
        $peticion = $this->peticion($usuario);
        $peticion->session()->put('seguimiento-egresado.identidad', [
            'perfil' => 'EGRESADO', 'egresado_id' => 42, 'invitacion_id' => 7, 'vence_en' => now()->addDay()->toIso8601String(),
        ]);

        $sesion = SesionAcceso::desde($peticion);

        $this->assertSame([Perfil::Secretaria, Perfil::Egresado], array_map(fn ($i) => $i->perfil, $sesion->todas()));
        $this->assertSame(Perfil::Secretaria, $sesion->actual()->perfil, 'la principal es la del personal');
        $this->assertSame(42, $sesion->egresado()->egresadoId);
    }
}
