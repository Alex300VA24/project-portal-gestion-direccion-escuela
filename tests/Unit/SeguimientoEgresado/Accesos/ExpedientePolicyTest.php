<?php

namespace Tests\Unit\SeguimientoEgresado\Accesos;

use App\Modules\SeguimientoEgresado\Policies\ExpedientePolicy;
use App\Modules\SeguimientoEgresado\Support\Accesos\Identidad;
use App\Modules\SeguimientoEgresado\Support\Accesos\Perfil;
use PHPUnit\Framework\TestCase;

class ExpedientePolicyTest extends TestCase
{
    private ExpedientePolicy $politica;

    protected function setUp(): void
    {
        $this->politica = new ExpedientePolicy;
    }

    private function egresado(int $egresadoId = 10, int $invitacionId = 5): Identidad
    {
        return new Identidad(Perfil::Egresado, egresadoId: $egresadoId, invitacionId: $invitacionId);
    }

    public function test_el_egresado_ve_y_modifica_solo_su_propio_expediente(): void
    {
        $this->assertTrue($this->politica->ver($this->egresado(10), 10));
        $this->assertTrue($this->politica->modificar($this->egresado(10), 10));
        $this->assertFalse($this->politica->ver($this->egresado(10), 11));
        $this->assertFalse($this->politica->modificar($this->egresado(10), 11));
    }

    public function test_el_egresado_ve_solo_su_propio_seguimiento(): void
    {
        $this->assertTrue($this->politica->verSeguimiento($this->egresado(10), 10));
        $this->assertFalse($this->politica->verSeguimiento($this->egresado(10), 99));
    }

    public function test_un_egresado_sin_expediente_en_la_identidad_no_accede_a_nada(): void
    {
        $sinExpediente = new Identidad(Perfil::Egresado);

        $this->assertFalse($this->politica->ver($sinExpediente, 0));
        $this->assertFalse($this->politica->verSeguimiento($sinExpediente, 0));
    }

    public function test_secretaria_gestiona_cualquier_expediente_y_consulta_su_seguimiento(): void
    {
        $secretaria = new Identidad(Perfil::Secretaria, usuarioId: 1);

        $this->assertTrue($this->politica->ver($secretaria, 77));
        $this->assertTrue($this->politica->modificar($secretaria, 77));
        $this->assertTrue($this->politica->verSeguimiento($secretaria, 77));
    }

    public function test_el_director_consulta_expedientes_pero_no_los_modifica_ni_ve_seguimientos_individuales(): void
    {
        $director = new Identidad(Perfil::Director, usuarioId: 2);

        $this->assertTrue($this->politica->ver($director, 77));
        $this->assertFalse($this->politica->modificar($director, 77));
        $this->assertFalse($this->politica->verSeguimiento($director, 77));
    }

    public function test_el_egresado_solo_responde_en_su_propio_nombre(): void
    {
        $identidad = $this->egresado(10, 5);

        $this->assertTrue($this->politica->responderEncuesta($identidad, 10));
        $this->assertFalse($this->politica->responderEncuesta($identidad, 11), 'otro egresado');
    }

    public function test_los_perfiles_internos_no_responden_encuestas(): void
    {
        $this->assertFalse($this->politica->responderEncuesta(new Identidad(Perfil::Secretaria, usuarioId: 1, invitacionId: 5, egresadoId: 10), 10));
        $this->assertFalse($this->politica->responderEncuesta(new Identidad(Perfil::Director, usuarioId: 2, invitacionId: 5, egresadoId: 10), 10));
    }
}
