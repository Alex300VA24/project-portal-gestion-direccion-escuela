<?php

namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Services\PropuestaSecciones;
use PHPUnit\Framework\TestCase;

final class PropuestaSeccionesTest extends TestCase
{
    public function test_conserva_capacidades_activas(): void
    {
        $resultado = PropuestaSecciones::calcular(89, 40, [
            (object) ['estado' => 'ACTIVA', 'capacidad' => 40],
            (object) ['estado' => 'ACTIVA', 'capacidad' => 40],
            (object) ['estado' => 'CERRADA', 'capacidad' => 30],
        ]);
        self::assertSame(80, $resultado['plazas']);
        self::assertSame(9, $resultado['faltantes']);
        self::assertSame(1, $resultado['adicionales']);
    }

    public function test_demanda_ausente_no_equivale_a_cero(): void
    {
        self::assertNull(PropuestaSecciones::calcular(null, 40, [])['adicionales']);
        self::assertSame(0, PropuestaSecciones::calcular(0, 40, [])['adicionales']);
    }

    public function test_capacidad_desconocida_impide_estimacion(): void
    {
        self::assertNull(PropuestaSecciones::calcular(89, 40, [(object) ['estado' => 'ACTIVA', 'capacidad' => null]])['adicionales']);
        self::assertSame(3, PropuestaSecciones::calcular(89, 40, [])['adicionales']);
    }

    public function test_capacidad_objetivo_invalida(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PropuestaSecciones::calcular(89, 0, []);
    }
}
