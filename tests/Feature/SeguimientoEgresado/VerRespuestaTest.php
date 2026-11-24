<?php

namespace Tests\Feature\SeguimientoEgresado;

use Illuminate\Support\Facades\DB;
use Tests\Support\SeguimientoEgresado\FabricaEmpresasIndicadores;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/** «Ver respuesta»: una ventana sobre la lista, con la respuesta completa; los egresados solo para Secretaría. */
class VerRespuestaTest extends TestCase
{
    use FabricaEmpresasIndicadores, UsaBaseDeDatos;

    /** @return array{campania: int, respondida: int, pendiente: int} */
    private function campaniaConUnaRespuesta(): array
    {
        $cohorte = $this->cohorte(10);
        $campania = $this->campania(['nombre' => 'Seguimiento 2026-II', 'tipo' => 'EGRESADOS']);
        $respondida = $this->invitacionSeguimiento($campania, $this->egresado($cohorte, 'EGRESADO', ['nombres' => 'Lucía', 'apellido_paterno' => 'Vega', 'apellido_materno' => 'Salazar']));
        $this->responder($respondida, cambios: ['nombre_empleador_cliente' => 'Andes Software Labs S.A.C.', 'contacto_jefe' => '+51 944 118 205', 'autoriza_contacto_empresa' => true]);
        $pendiente = $this->invitacionSeguimiento($campania, $this->egresado($cohorte));

        return ['campania' => $campania, 'respondida' => $respondida, 'pendiente' => $pendiente];
    }

    public function test_la_secretaria_ve_la_respuesta_completa_de_un_egresado(): void
    {
        $this->entrarComo();
        $c = $this->campaniaConUnaRespuesta();

        $this->get(route('seguimiento-egresado.campanias.respuesta', $c['respondida']))->assertOk()
            ->assertSee('Vega Salazar, Lucía')
            ->assertSee('Situación laboral')
            ->assertSee('Andes Software Labs S.A.C.')
            ->assertSee('+51 944 118 205')
            ->assertSee('Satisfacción con la formación')
            ->assertDontSee('<html', false);
    }

    public function test_quien_no_respondio_no_tiene_respuesta_que_ver(): void
    {
        $this->entrarComo();
        $c = $this->campaniaConUnaRespuesta();

        $this->get(route('seguimiento-egresado.campanias.respuesta', $c['pendiente']))->assertNotFound();
        $this->get(route('seguimiento-egresado.campanias.respuesta', 999999))->assertNotFound();
    }

    public function test_la_direccion_no_ve_las_respuestas_individuales_de_los_egresados(): void
    {
        $c = $this->campaniaConUnaRespuesta();
        $this->entrarComo('DIRECTOR');

        $this->get(route('seguimiento-egresado.campanias.respuesta', $c['respondida']))->assertForbidden();
        $this->get(route('seguimiento-egresado.campanias.detalle', $c['campania']))->assertOk()
            ->assertDontSee('Ver respuesta')->assertSee('Respondió');
    }

    public function test_sin_sesion_se_lleva_al_acceso_del_portal(): void
    {
        $c = $this->campaniaConUnaRespuesta();

        $this->get(route('seguimiento-egresado.campanias.respuesta', $c['respondida']))->assertRedirect();
    }

    public function test_el_detalle_de_la_campania_ofrece_ver_respuesta_solo_en_las_filas_respondidas(): void
    {
        $this->entrarComo();
        $c = $this->campaniaConUnaRespuesta();

        $html = $this->get(route('seguimiento-egresado.campanias.detalle', $c['campania']))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-respuesta='));
        $this->assertStringContainsString(route('seguimiento-egresado.campanias.respuesta', $c['respondida'], false), $html);
    }

    public function test_el_personal_ve_la_respuesta_de_una_empresa_sobre_un_egresado(): void
    {
        $this->entrarComo('DIRECTOR');
        $campania = $this->campania(['nombre' => 'Empleadores 2026', 'tipo' => 'EMPRESAS']);
        $egresado = $this->egresado($this->cohorte(10), 'EGRESADO', ['nombres' => 'Lucía', 'apellido_paterno' => 'Vega', 'apellido_materno' => 'Salazar']);
        $invitacion = $this->invitacionEmpresa($campania, 'DESEMPENO', $egresado)['id'];
        $this->respuestaEmpresa($invitacion, 'SATISFECHO', ['cargo_informante' => 'Jefa de TI', 'organizacion_informada' => 'Andes Software Labs S.A.C.', 'comentario' => 'Muy buen desempeño.']);

        $this->get(route('seguimiento-egresado.empresas.respuesta', $invitacion))->assertOk()
            ->assertSee('Andes Software Labs S.A.C.')
            ->assertSee('Opinión sobre un egresado')
            ->assertSee('Vega Salazar, Lucía')
            ->assertSee('Jefa de TI')
            ->assertSee('Muy buen desempeño.');
    }

    public function test_la_encuesta_general_de_empresas_muestra_habilidades_y_no_menciona_egresados(): void
    {
        $this->entrarComo();
        $campania = $this->campania(['tipo' => 'EMPRESAS']);
        $invitacion = $this->invitacionEmpresa($campania, 'MERCADO')['id'];
        $respuesta = $this->respuestaEmpresa($invitacion, null, ['organizacion_informada' => 'Cámara de Comercio']);
        $habilidad = (int) DB::table('seguimiento_egresado.cat_habilidad')->orderBy('id')->value('id');
        DB::table('seguimiento_egresado.respuesta_empresa_habilidad')->insert(['id_respuesta' => $respuesta, 'id_habilidad' => $habilidad, 'tipo' => 'DEMANDA']);

        $html = $this->get(route('seguimiento-egresado.empresas.respuesta', $invitacion))->assertOk()
            ->assertSee('Encuesta general de demanda de habilidades')
            ->assertSee('Que más demanda')
            ->assertDontSee('Egresado evaluado')->getContent();
        $this->assertStringContainsString(DB::table('seguimiento_egresado.cat_habilidad')->where('id', $habilidad)->value('nombre'), html_entity_decode($html));
    }

    public function test_una_empresa_que_no_respondio_no_tiene_respuesta(): void
    {
        $this->entrarComo();
        $invitacion = $this->invitacionEmpresa($this->campania(['tipo' => 'EMPRESAS']), 'MERCADO')['id'];

        $this->get(route('seguimiento-egresado.empresas.respuesta', $invitacion))->assertNotFound();
    }
}
