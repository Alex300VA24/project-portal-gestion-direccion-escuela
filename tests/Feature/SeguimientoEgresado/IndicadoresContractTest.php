<?php

namespace Tests\Feature\SeguimientoEgresado;

use App\Modules\SeguimientoEgresado\Database\Seeders\DatosPruebaSeeder;
use App\Modules\SeguimientoEgresado\Services\IndicadoresService;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

class IndicadoresContractTest extends TestCase
{
    use UsaBaseDeDatos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatosPruebaSeeder::class);
    }

    public function test_indicador_i1_structure_and_values(): void
    {
        $service = new IndicadoresService;
        $i1 = $service->getIndicadorI1();

        $this->assertSame('I1', $i1['codigo']);
        $this->assertSame('%', $i1['unidad']);
        $this->assertNotEmpty($i1['data']);

        foreach ($i1['data'] as $item) {
            $this->assertArrayHasKey('numerador', $item);
            $this->assertArrayHasKey('denominador', $item);
            $this->assertArrayHasKey('resultado', $item);
            $this->assertArrayHasKey('estado', $item);
        }
    }

    public function test_indicador_i3_respects_total_oe_as_official_denominator(): void
    {
        $service = new IndicadoresService;
        $i3 = $service->getIndicadorI3();

        $this->assertSame('I3', $i3['codigo']);
        // El total oficial de OEs del programa debe ser 4 según el seeder
        $this->assertSame(4, $i3['total_oe_programa']);
    }

    public function test_indicador_i6_is_absolute_count_of_improvements(): void
    {
        $service = new IndicadoresService;
        $i6 = $service->getIndicadorI6();

        $this->assertSame('I6', $i6['codigo']);
        $this->assertSame('cantidad', $i6['unidad']);
    }
}
