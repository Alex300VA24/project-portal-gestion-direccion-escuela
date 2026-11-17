<?php

namespace Tests\Feature\SeguimientoEgresado;

use App\Modules\SeguimientoEgresado\Database\Seeders\DatosPruebaSeeder;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\ContactoEgresado;
use App\Modules\SeguimientoEgresado\Models\InvitacionEgresado;
use App\Modules\SeguimientoEgresado\Services\Invitaciones\ContactoInicialService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/** Página principal del padrón: cada promoción avanza por cuatro pasos y se puede retomar donde quedó. */
class PromocionFlujoTest extends TestCase
{
    use ComoPersonal, UsaBaseDeDatos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatosPruebaSeeder::class);
        Mail::fake();
        InvitacionEgresado::where('proposito', 'CONTACTO_INICIAL')->delete();
        ContactoEgresado::query()->delete();
    }

    private function nuevaPromocion(int $anio = 2030): Cohorte
    {
        return Cohorte::create([
            'codigo_promocion' => "PROM-{$anio}", 'anio_ingreso' => $anio, 'semestre_ingreso' => 'I', 'total_ingresantes' => 20,
            'duracion_esperada_anios' => 5, 'id_plan_curricular' => Cohorte::firstOrFail()->id_plan_curricular,
        ]);
    }

    public function test_la_lista_muestra_el_paso_que_toca_en_cada_promocion(): void
    {
        $vacia = $this->nuevaPromocion();
        $conAlumnos = Cohorte::whereHas('egresados')->firstOrFail();

        $this->actingAs($this->secretaria())->get(route('seguimiento-egresado.padron'))
            ->assertOk()->assertSee('+ Nueva promoción')->assertSee('Consultar alumnos y grados')
            ->assertSee('Promoción '.$vacia->anio_ingreso)->assertSee('Cargar alumnos →')->assertSee('Enviar enlaces →')
            ->assertSee(route('seguimiento-egresado.padron.promocion', $vacia->id).'#paso-2', false)
            ->assertSee(route('seguimiento-egresado.padron.promocion', $conAlumnos->id).'#paso-3', false)
            ->assertDontSee('Ir al contacto inicial');
    }

    public function test_una_promocion_nueva_deja_los_pasos_siguientes_bloqueados(): void
    {
        $vacia = $this->nuevaPromocion();

        $pagina = $this->actingAs($this->secretaria())->get(route('seguimiento-egresado.padron.promocion', $vacia->id))->assertOk();
        $pagina->assertSee('Te toca')->assertSee('Primero carga alumnos')->assertSee('Primero envía los enlaces');
        $pagina->assertDontSee('Enviar enlaces</span>', false);
    }

    public function test_al_enviar_los_enlaces_se_abre_el_seguimiento_y_al_completar_todos_queda_terminada(): void
    {
        $promocion = Cohorte::whereHas('egresados')->firstOrFail();
        $servicio = app(ContactoInicialService::class);

        $this->assertSame(3, $servicio->avancePorPromocion($promocion->id)->first()['paso'], 'con alumnos sin enlace toca enviarlos');

        $this->actingAs($this->secretaria())->post(route('seguimiento-egresado.padron.contacto-inicial.cohorte'), ['cohorte' => $promocion->id, 'fecha_limite' => now()->addDays(10)->toDateString()])
            ->assertRedirect(route('seguimiento-egresado.padron.promocion', $promocion->id).'#paso-4');

        $avance = $servicio->avancePorPromocion($promocion->id)->first();
        $this->assertSame(4, $avance['paso']);
        $this->assertFalse($avance['terminada']);
        $this->actingAs($this->secretaria())->get(route('seguimiento-egresado.padron.promocion', $promocion->id))
            ->assertOk()->assertSee('PENDIENTE')->assertSee('Aún no debería egresar')->assertSee('Fecha límite');

        foreach ($promocion->egresados as $alumno) {
            ContactoEgresado::create(['id_egresado' => $alumno->id, 'correo_personal' => "p{$alumno->id}@correo.test", 'celular_whatsapp' => '+51900111222', 'vigente_desde' => now(), 'origen_registro' => 'FORMULARIO']);
        }
        $this->assertTrue($servicio->avancePorPromocion($promocion->id)->first()['terminada']);
        $this->actingAs($this->secretaria())->get(route('seguimiento-egresado.padron.promocion', $promocion->id))
            ->assertOk()->assertSee('Todos completaron')->assertDontSee('Aún no debería egresar');
    }

    public function test_cargar_mas_alumnos_devuelve_el_paso_de_enviar_enlaces(): void
    {
        $promocion = Cohorte::whereHas('egresados')->firstOrFail();
        $servicio = app(ContactoInicialService::class);
        foreach ($promocion->egresados as $alumno) {
            ContactoEgresado::create(['id_egresado' => $alumno->id, 'correo_personal' => "q{$alumno->id}@correo.test", 'celular_whatsapp' => '+51900111222', 'vigente_desde' => now(), 'origen_registro' => 'FORMULARIO']);
        }
        $this->assertSame(4, $servicio->avancePorPromocion($promocion->id)->first()['paso']);

        $primero = $promocion->egresados()->first()->replicate();
        $primero->codigo_matricula = '9999000001';
        $primero->numero_documento = '99990001';
        $primero->save();

        $avance = $servicio->avancePorPromocion($promocion->id)->first();
        $this->assertSame(3, $avance['paso'], 'el alumno nuevo no tiene enlace');
        $this->assertSame(1, $avance['sin_enlace']);
    }

    public function test_el_director_consulta_pero_no_genera_enlaces(): void
    {
        $promocion = Cohorte::whereHas('egresados')->firstOrFail();

        $this->actingAs($this->director())->get(route('seguimiento-egresado.padron.promocion', $promocion->id))->assertOk();
        $this->actingAs($this->director())->post(route('seguimiento-egresado.padron.contacto-inicial.cohorte'), ['cohorte' => $promocion->id, 'fecha_limite' => now()->addDay()->toDateString()])->assertForbidden();
    }

    public function test_la_consulta_de_alumnos_ya_no_trae_las_pestanas_de_promociones_ni_de_carga(): void
    {
        $this->actingAs($this->secretaria())->get(route('seguimiento-egresado.padron.consulta'))
            ->assertOk()->assertSee('Consultar alumnos y grados')->assertSee('Historial de cargas')
            ->assertDontSee('Cargar alumnos')->assertDontSee('Registrar promoción')->assertDontSee('Ir al contacto inicial');
    }
}
