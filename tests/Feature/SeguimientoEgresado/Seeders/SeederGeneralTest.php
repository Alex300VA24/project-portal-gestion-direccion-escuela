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

    public function test_los_cuatro_correos_reales_se_reparten_entre_dos_aptos_y_dos_egresados(): void
    {
        $this->seed(SeguimientoEgresadoSeeder::class);

        $aptos = DB::table('seguimiento_egresado.egresado as e')
            ->join('seguimiento_egresado.historial_estado_academico as h', 'h.id_egresado', '=', 'e.id')
            ->join('seguimiento_egresado.cat_estado_academico as c', 'c.id', '=', 'h.id_estado_academico')
            ->where('c.codigo', 'PROXIMO')->whereIn('e.codigo_matricula', ['2019100231', '2019100264'])
            ->pluck('e.correo_institucional')->sort()->values()->all();
        $this->assertSame(['acortegana@unitru.edu.pe', 'agamboaa@unitru.edu.pe'], $aptos);

        $personales = DB::table('seguimiento_egresado.contacto_egresado as c')
            ->join('seguimiento_egresado.egresado as e', 'e.id', '=', 'c.id_egresado')
            ->whereIn('e.codigo_matricula', ['2019100142', '2019100158'])->whereNull('c.vigente_hasta')
            ->pluck('c.correo_personal')->sort()->values()->all();
        $this->assertSame(['alvaroenrique080199@gmail.com', 'arimanuelg544@gmail.com'], $personales);
    }
}
