<?php

namespace Tests\Feature\SeguimientoEgresado;

use App\Modules\SeguimientoEgresado\Database\Seeders\DatosPruebaSeeder;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Services\IndicadoresService;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/** Los datos de prueba y las vistas de indicadores sobre el esquema real, y el padrón servido desde la base. */
class PostgresIntegrationTest extends TestCase
{
    use ComoPersonal, UsaBaseDeDatos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->secretaria());
        $this->seed(DatosPruebaSeeder::class);
    }

    public function test_los_indicadores_se_calculan_con_las_vistas_de_la_base(): void
    {
        $this->assertGreaterThan(0, Egresado::count());
        $this->assertGreaterThan(0, Cohorte::count());

        $servicio = new IndicadoresService;
        $i1 = $servicio->getIndicadorI1();
        $this->assertSame('I1', $i1['codigo']);
        $this->assertNotEmpty($i1['data']);

        $this->assertSame('I2', $servicio->getIndicadorI2()['codigo']);
        $this->assertSame('I3', $servicio->getIndicadorI3()['codigo']);
        $this->assertSame('I6', $servicio->getIndicadorI6()['codigo']);
    }

    public function test_el_padron_se_muestra_con_los_datos_de_la_base(): void
    {
        $this->get(route('seguimiento-egresado.padron'))
            ->assertOk()
            ->assertSee('Padrón Oficial y Cohortes Académicas')
            ->assertSee('Cohortes y Denominador I1');
    }
}
