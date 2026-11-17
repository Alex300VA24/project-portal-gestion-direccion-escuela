<?php

namespace Tests\Feature\SeguimientoEgresado\Seeders;

use App\Modules\SeguimientoEgresado\Database\Seeders\SeguimientoEgresadoSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/** El seeder del módulo (`db:seed --class=...SeguimientoEgresadoSeeder`) deja listos los tres casos de demostración del egresado. */
class SeederGeneralTest extends TestCase
{
    use UsaBaseDeDatos;

    public function test_el_seeder_del_modulo_incluye_los_tres_egresados_de_demostracion(): void
    {
        $this->seed(SeguimientoEgresadoSeeder::class);

        $this->assertSame(3, DB::table('seguimiento_egresado.egresado')->whereIn('codigo_matricula', ['2019100231', '2019100187', '2019100094'])->count());
        $respuestasDeCamila = DB::table('seguimiento_egresado.medicion_laboral as m')->join('seguimiento_egresado.invitacion_egresado as i', 'i.id', '=', 'm.id_invitacion')
            ->join('seguimiento_egresado.egresado as e', 'e.id', '=', 'i.id_egresado')->where('e.codigo_matricula', '2019100094')->count();
        $this->assertSame(1, $respuestasDeCamila);
    }
}
