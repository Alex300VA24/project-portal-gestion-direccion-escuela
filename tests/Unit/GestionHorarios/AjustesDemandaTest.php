<?php

namespace Tests\Unit\GestionHorarios;

use App\Modules\GestionHorarios\Demand\AjustesDemanda;
use PHPUnit\Framework\TestCase;

final class AjustesDemandaTest extends TestCase
{
    public function test_aumento_conserva_preferencias_conocidas(): void
    {
        $fila = (object) ['total'=>60,'manana'=>25,'tarde'=>15];
        self::assertSame(['total'=>75,'manana'=>25,'tarde'=>15], (array) AjustesDemanda::aplicar($fila,75));
    }

    public function test_reduccion_no_inventa_preferencias(): void
    {
        $fila = (object) ['total'=>60,'manana'=>25,'tarde'=>15];
        self::assertSame(['total'=>30,'manana'=>0,'tarde'=>0], (array) AjustesDemanda::aplicar($fila,30));
    }
}
