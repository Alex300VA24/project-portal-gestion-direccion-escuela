<?php

namespace Tests\Feature\SeguimientoEgresado\Empresas;

use App\Modules\SeguimientoEgresado\Database\Seeders\DatosPruebaSeeder;
use App\Modules\SeguimientoEgresado\Database\Seeders\EmpresasIndicadoresDemoSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/** La semilla [DEMO] es solo para pruebas: nunca se carga en producción ni con los datos de prueba base. */
class SeederDemoTest extends TestCase
{
    use UsaBaseDeDatos;

    public function test_los_datos_de_prueba_base_no_cargan_datos_demo(): void
    {
        $this->seed(DatosPruebaSeeder::class);

        $this->assertFalse(DB::table('seguimiento_egresado.campania_seguimiento')->where('nombre', 'like', '[DEMO]%')->exists());
    }

    public function test_en_produccion_la_semilla_demo_no_inserta_nada_ni_con_force(): void
    {
        $this->seed(DatosPruebaSeeder::class);
        $antes = DB::table('seguimiento_egresado.campania_seguimiento')->count();
        $this->app['env'] = 'production';

        $this->artisan('db:seed', ['--class' => EmpresasIndicadoresDemoSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertSame($antes, DB::table('seguimiento_egresado.campania_seguimiento')->count());
        $this->assertFalse(DB::table('seguimiento_egresado.campania_seguimiento')->where('nombre', 'like', '[DEMO]%')->exists());
    }

    public function test_fuera_de_produccion_la_semilla_demo_si_carga(): void
    {
        $this->seed(DatosPruebaSeeder::class);

        $this->seed(EmpresasIndicadoresDemoSeeder::class);

        $this->assertTrue(DB::table('seguimiento_egresado.campania_seguimiento')->where('nombre', 'like', '[DEMO]%')->exists());
    }
}
