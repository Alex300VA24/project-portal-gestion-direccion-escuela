<?php

namespace Tests\Unit\SeguimientoEgresado\Accesos;

use App\Modules\SeguimientoEgresado\Support\Accesos\Perfil;
use App\Modules\SeguimientoEgresado\Support\Accesos\RutaInicial;
use PHPUnit\Framework\TestCase;

class RutaInicialTest extends TestCase
{
    public function test_cada_perfil_tiene_su_pagina_de_entrada(): void
    {
        $this->assertSame('seguimiento-egresado.padron', RutaInicial::para(Perfil::Secretaria));
        $this->assertSame('seguimiento-egresado.indicadores', RutaInicial::para(Perfil::Director));
        $this->assertSame('seguimiento-egresado.egresado.inicio', RutaInicial::para(Perfil::Egresado));
    }
}
