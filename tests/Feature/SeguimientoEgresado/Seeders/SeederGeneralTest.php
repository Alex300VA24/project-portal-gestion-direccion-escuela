<?php

namespace Tests\Feature\SeguimientoEgresado\Seeders;

use App\Modules\SeguimientoEgresado\Database\Seeders\SeguimientoEgresadoSeeder;
use App\Modules\SeguimientoEgresado\Database\Seeders\PadronSinteticoSeeder;
use App\Modules\SeguimientoEgresado\Services\Indicadores\CalculadoraIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores;
use App\Modules\SeguimientoEgresado\Services\IndicadoresService;
use App\Modules\SeguimientoEgresado\Services\PadronImportService;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/** El seeder del módulo (`db:seed --class=...SeguimientoEgresadoSeeder`) deja listos los tres casos de demostración del egresado. */
class SeederGeneralTest extends TestCase
{
    use ComoPersonal, UsaBaseDeDatos;

    public function test_el_seeder_del_modulo_incluye_los_tres_egresados_de_demostracion(): void
    {
        $this->seed(SeguimientoEgresadoSeeder::class);

        $this->assertSame(3, DB::table('seguimiento_egresado.egresado')->whereIn('codigo_matricula', ['2019100231', '2019100187', '2019100094'])->count());
        $respuestasDeCamila = DB::table('seguimiento_egresado.medicion_laboral as m')->join('seguimiento_egresado.invitacion_egresado as i', 'i.id', '=', 'm.id_invitacion')
            ->join('seguimiento_egresado.egresado as e', 'e.id', '=', 'i.id_egresado')->where('e.codigo_matricula', '2019100094')->count();
        $this->assertSame(1, $respuestasDeCamila);
    }

    public function test_los_dos_correos_institucionales_reales_no_estan_precargados_y_el_csv_de_demostracion_los_carga(): void
    {
        $this->actingAs($this->secretaria());
        $this->seed(SeguimientoEgresadoSeeder::class);
        $reales = ['acortegana@unitru.edu.pe', 'agamboaa@unitru.edu.pe'];

        $this->assertSame(0, DB::table('seguimiento_egresado.egresado')->whereIn('correo_institucional', $reales)->count(), 'se cargan en vivo desde el CSV');

        // El CSV de la demostración entra completo en la Promoción 2019, que ya existe.
        $promocion = (int) DB::table('seguimiento_egresado.cohorte')->where('anio_ingreso', 2019)->value('id');
        $resultado = (new PadronImportService)->importarArchivo(base_path('docs/seguimiento-egresado/datos/alumnos-aptos-demo.csv'), null, true, $promocion);

        $this->assertSame(6, DB::table('seguimiento_egresado.egresado')->where('id_cohorte', $promocion)->whereBetween('codigo_matricula', ['2019100001', '2019100006'])->count(), json_encode($resultado));
        $this->assertSame(2, DB::table('seguimiento_egresado.egresado')->whereIn('correo_institucional', $reales)->count());
    }

    public function test_los_dos_correos_personales_reales_son_de_egresados_sin_invitacion_en_la_campania_2026(): void
    {
        $this->seed(SeguimientoEgresadoSeeder::class);

        $personales = DB::table('seguimiento_egresado.contacto_egresado as c')
            ->join('seguimiento_egresado.egresado as e', 'e.id', '=', 'c.id_egresado')
            ->whereIn('e.codigo_matricula', ['2019100142', '2019100158'])->whereNull('c.vigente_hasta')
            ->pluck('c.correo_personal')->sort()->values()->all();
        $this->assertSame(['alvaroenrique080199@gmail.com', 'arimanuelg544@gmail.com'], $personales);

        // La campaña está creada y esos dos todavía no tienen su enlace: al emitir, el correo sale hacia ellos.
        $campania = (int) DB::table('seguimiento_egresado.campania_seguimiento')->where('nombre', 'Seguimiento a egresados 2026-II')->value('id');
        $this->assertGreaterThan(0, $campania);
        $conInvitacion = DB::table('seguimiento_egresado.invitacion_egresado as i')->join('seguimiento_egresado.egresado as e', 'e.id', '=', 'i.id_egresado')
            ->where('i.id_campania', $campania)->whereIn('e.codigo_matricula', ['2019100142', '2019100158'])->count();
        $this->assertSame(0, $conInvitacion);
    }

    public function test_el_padron_sintetico_da_tasas_de_egreso_creibles_y_no_se_duplica(): void
    {
        $this->seed(SeguimientoEgresadoSeeder::class);
        $conteo = DB::table('seguimiento_egresado.egresado')->count();

        foreach (app(CalculadoraIndicadores::class)->i1PorCohorte(new FiltroIndicadores) as $fila) {
            if (! $fila['en_curso']) {
                $this->assertGreaterThanOrEqual(55.0, $fila['valor'], $fila['etiqueta']);
                $this->assertLessThanOrEqual(90.0, $fila['valor'], $fila['etiqueta']);
            }
        }

        $this->seed(PadronSinteticoSeeder::class);
        $this->assertSame($conteo, DB::table('seguimiento_egresado.egresado')->count(), 'repetir el seeder no duplica');
    }

    public function test_los_unicos_correos_personales_reales_de_la_semilla_son_los_dos_de_la_demostracion(): void
    {
        $this->seed(SeguimientoEgresadoSeeder::class);

        $fueraDeTest = DB::table('seguimiento_egresado.contacto_egresado')->where('correo_personal', 'not like', '%@correo.test')->pluck('correo_personal')->sort()->values()->all();

        $this->assertSame(['alvaroenrique080199@gmail.com', 'arimanuelg544@gmail.com'], $fueraDeTest);
    }

    public function test_ningun_egresado_sintetico_tiene_un_correo_real_ni_choca_con_el_csv(): void
    {
        $this->seed(SeguimientoEgresadoSeeder::class);

        $sinteticos = DB::table('seguimiento_egresado.egresado')->whereRaw("codigo_matricula ~ '^[0-9]{4}15[0-9]{4}$'");
        $this->assertGreaterThan(50, (clone $sinteticos)->count());
        $this->assertSame(0, (clone $sinteticos)->where('correo_institucional', 'not like', '%@correo.test')->count());
        $contactos = DB::table('seguimiento_egresado.contacto_egresado as c')->joinSub((clone $sinteticos)->select('id'), 's', 's.id', '=', 'c.id_egresado');
        $this->assertSame(0, (clone $contactos)->where('c.correo_personal', 'not like', '%@correo.test')->count());
        // Sin contacto en la Promoción 2019: su campaña de demostración solo convoca a quienes ya lo dejaron.
        $this->assertSame(0, (clone $contactos)->join('seguimiento_egresado.egresado as e', 'e.id', '=', 'c.id_egresado')->where('e.codigo_matricula', 'like', '2019%')->count());
    }
}
