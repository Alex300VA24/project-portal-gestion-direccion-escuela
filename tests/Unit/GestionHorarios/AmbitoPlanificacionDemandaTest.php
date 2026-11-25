<?php

namespace Tests\Unit\GestionHorarios;

use App\Modules\GestionHorarios\Services\AmbitoPlanificacionDemanda;
use PHPUnit\Framework\TestCase;

final class AmbitoPlanificacionDemandaTest extends TestCase
{
    public function test_autoriza_solo_ambientes_del_ambito_acreditado(): void
    {
        $base = ['escuela_id' => 1, 'periodo_id' => 2, 'plan_id' => 3, 'ambientes' => [9, 7, 9]];
        self::assertSame([7, 9], AmbitoPlanificacionDemanda::validar($base, 1, 2, 3, [7, 9, 11]));
        foreach (['escuela_id', 'periodo_id', 'plan_id'] as $campo) {
            self::assertNull(AmbitoPlanificacionDemanda::validar(array_replace($base, [$campo => 99]), 1, 2, 3, [7, 9]));
        }
        foreach ([[7, 99], ['7'], [0], ['a' => 7], null] as $ids) {
            self::assertNull(AmbitoPlanificacionDemanda::validar(array_replace($base, ['ambientes' => $ids]), 1, 2, 3, [7, 9]));
        }
        self::assertNull(AmbitoPlanificacionDemanda::validar(null, 1, 2, 3, [7, 9]));
        self::assertSame([], AmbitoPlanificacionDemanda::validar(array_replace($base, ['ambientes' => []]), 1, 2, 3, [7, 9]));
    }
}
