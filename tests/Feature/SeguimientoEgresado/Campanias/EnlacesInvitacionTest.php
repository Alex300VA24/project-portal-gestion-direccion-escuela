<?php

namespace Tests\Feature\SeguimientoEgresado\Campanias;

use App\Modules\SeguimientoEgresado\Database\Seeders\DatosPruebaSeeder;
use App\Modules\SeguimientoEgresado\Models\CampaniaSeguimiento;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Models\InvitacionEgresado;
use App\Modules\SeguimientoEgresado\Models\UsuarioInterno;
use App\Modules\SeguimientoEgresado\Services\Accesos\EnlaceInvitacion;
use App\Modules\SeguimientoEgresado\Services\PadronImportService;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/** Al emitir invitaciones, cada egresado recibe un enlace usable: solo se guarda su SHA-256 y el enlace se muestra una vez. */
class EnlacesInvitacionTest extends TestCase
{
    use ComoPersonal, UsaBaseDeDatos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->secretaria());
        $this->seed(DatosPruebaSeeder::class);
    }

    private function campaniaDeLa2019(): CampaniaSeguimiento
    {
        $campania = CampaniaSeguimiento::create([
            'nombre' => 'Encuesta Cohorte 2019', 'tipo' => 'EGRESADOS', 'finalidad' => 'SEGUIMIENTO', 'periodo' => '2026-II',
            'fecha_inicio' => '2026-10-05', 'fecha_fin' => '2026-12-31', 'estado' => 'ABIERTA', 'id_usuario_creacion' => UsuarioInterno::idActual(),
        ]);
        $campania->cohortes()->attach(Cohorte::where('anio_ingreso', 2019)->value('id'));

        return $campania;
    }

    public function test_cada_invitacion_emitida_trae_un_enlace_cuyo_token_coincide_con_el_hash_guardado(): void
    {
        $campania = $this->campaniaDeLa2019();

        $respuesta = $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $campania->id));

        $enlaces = session('enlaces_emitidos');
        $invitaciones = InvitacionEgresado::where('id_campania', $campania->id)->get();
        $this->assertGreaterThan(0, $invitaciones->count());
        $this->assertCount($invitaciones->count(), $enlaces, 'un enlace por invitación emitida');
        $respuesta->assertRedirect();

        $hashes = $invitaciones->pluck('token_hash')->all();
        foreach ($enlaces as $enlace) {
            $this->assertMatchesRegularExpression('#/seguimiento-egresado/acceso/[A-Za-z0-9]{48}$#', $enlace['url']);
            $token = basename($enlace['url']);
            $this->assertContains(EnlaceInvitacion::hashDe($token), $hashes, 'el enlace sirve para entrar con esa invitación');
            $this->assertNotContains($token, $hashes, 'el token en claro no se guarda en la BD');
            $this->assertNotEmpty($enlace['egresado']);
            $this->assertNotEmpty($enlace['matricula']);
        }
        $this->assertCount(count($enlaces), array_unique(array_column($enlaces, 'url')), 'los enlaces no se repiten');
    }

    public function test_la_pagina_muestra_los_enlaces_una_sola_vez(): void
    {
        $campania = $this->campaniaDeLa2019();

        $this->followingRedirects()->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $campania->id))
            ->assertOk()->assertSee('Enlaces personales de las invitaciones')->assertSee('/seguimiento-egresado/acceso/', false);

        $this->get(route('seguimiento-egresado.campanias', ['campania_id' => $campania->id]))->assertOk()->assertDontSee('Enlaces personales de las invitaciones');
    }

    public function test_reemitir_no_duplica_invitaciones_ni_vuelve_a_mostrar_enlaces(): void
    {
        $campania = $this->campaniaDeLa2019();
        $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $campania->id));
        $total = InvitacionEgresado::where('id_campania', $campania->id)->count();

        $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $campania->id));

        $this->assertSame($total, InvitacionEgresado::where('id_campania', $campania->id)->count());
        $this->assertSame([], session('enlaces_emitidos'), 'ya estaban convocados: no hay enlaces nuevos');
    }

    public function test_la_campania_no_convoca_a_alumnos_aptos_hasta_confirmar_su_egreso(): void
    {
        $campania = $this->campaniaDeLa2019();
        $cohorte = Cohorte::where('anio_ingreso', 2019)->first();
        $ruta = tempnam(sys_get_temp_dir(), 'apto_');
        file_put_contents($ruta, 'matricula,tipo_doc,numero_documento,nombres,apellido_paterno,cohorte,correo_institucional
1021900777,DNI,70007777,Alumno Apto,Quispe,2019,apto.campania@correo.test
');
        (new PadronImportService)->importarArchivo($ruta);
        @unlink($ruta);
        $apto = Egresado::where('codigo_matricula', '1021900777')->firstOrFail();
        $this->assertSame($cohorte->id, $apto->id_cohorte);

        $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $campania->id));
        $this->assertFalse(InvitacionEgresado::where('id_campania', $campania->id)->where('id_egresado', $apto->id)->exists(), 'un alumno apto todavía no es egresado');

        $this->postJson(route('seguimiento-egresado.padron.confirmar-egreso', $apto->id), ['fecha_egreso' => '2025-07-18'])->assertOk();
        $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $campania->id));

        $this->assertTrue(InvitacionEgresado::where('id_campania', $campania->id)->where('id_egresado', $apto->id)->exists());
    }
}
