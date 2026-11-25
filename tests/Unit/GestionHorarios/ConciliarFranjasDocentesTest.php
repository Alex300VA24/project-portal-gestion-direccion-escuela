<?php

namespace Tests\Unit\GestionHorarios;

use App\Modules\GestionHorarios\Engine\ConciliarFranjasDocentes;
use PHPUnit\Framework\TestCase;

final class ConciliarFranjasDocentesTest extends TestCase
{
    public function test_exige_igualdad_completa_sin_importar_el_orden(): void
    {
        $a = ['dia' => 'LUNES', 'inicio' => 9, 'fin' => 11];
        $b = ['dia' => 'MARTES', 'inicio' => 8, 'fin' => 10];
        $validador = new ConciliarFranjasDocentes;

        self::assertTrue($validador->coinciden([$a, $b], [$b, $a]));
        self::assertTrue($validador->coinciden([], []));
        self::assertFalse($validador->coinciden([$a, $b], [$a]));
        self::assertFalse($validador->coinciden([$a], [$a, $b]));
        self::assertFalse($validador->coinciden([$a], [$a, $a]));
        self::assertFalse($validador->coinciden([$a], [['dia' => 'LUNES', 'inicio' => 9, 'fin' => 9]]));
    }
}
