<?php

namespace Tests\Feature\SeguimientoEgresado;

use App\Modules\SeguimientoEgresado\Mail\InvitacionEgresadoMail as CorreoDeInvitacion;
use App\Modules\SeguimientoEgresado\Services\Indicadores\CalculadoraIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\FabricaSeguimiento;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/**
 * Recorrido de la demostración sobre una base vacía (sin datos de prueba): alumno apto → contacto inicial → egreso →
 * campaña → encuesta → empleador → indicadores. Todo se guarda de verdad en PostgreSQL.
 */
class RecorridoCompletoTest extends TestCase
{
    use ComoPersonal, FabricaSeguimiento, UsaBaseDeDatos;

    private const CSV = "matricula,tipo_doc,numero_documento,nombres,apellido_paterno,apellido_materno,cohorte,correo_institucional\n"
        ."2019100001,DNI,70000001,Lucía Fernanda,Vega,Salazar,2019,lucia.vega@correo.test\n"
        ."2019100002,DNI,70000002,Mateo Andrés,Rojas,Paredes,2019,mateo.rojas@correo.test\n"
        ."2019100003,DNI,70000003,Camila Rosa,Torres,Llamo,2019,camila.torres@correo.test\n";

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'smtp', 'seguimiento-egresado.correo.enviar' => true, 'seguimiento-egresado.correo.redirigir_a' => null]);
        Mail::fake();
    }

    private function consulta(string $sql, array $bindings = []): array
    {
        return DB::select($sql, $bindings);
    }

    public function test_el_recorrido_completo_desde_el_alumno_apto_hasta_los_indicadores(): void
    {
        $this->actingAs($this->secretaria());

        // 0. Plan y cohorte oficiales
        $this->postJson(route('seguimiento-egresado.padron.guardar-plan'), ['codigo_version' => 'Plan 2017', 'vigencia_desde' => '2017-03-01', 'estado' => 'VIGENTE'])->assertOk();
        $plan = (int) DB::table('seguimiento_egresado.plan_curricular')->value('id');
        $this->postJson(route('seguimiento-egresado.padron.guardar-cohorte'), ['anio_ingreso' => 2019, 'total_ingresantes' => 3, 'id_plan_curricular' => $plan, 'codigo_promocion' => 'PROM-2019'])->assertOk();
        $cohorte = (int) DB::table('seguimiento_egresado.cohorte')->value('id');

        // 1. Alumnos aptos: importación de un archivo, sin egresados todavía y sin datos inventados
        $this->post(route('seguimiento-egresado.padron.importar'), ['promocion' => $cohorte, 'archivo_padron' => UploadedFile::fake()->createWithContent('aptos.csv', self::CSV)])->assertRedirect();
        $this->assertSame(3, DB::table('seguimiento_egresado.egresado')->count());
        $this->assertSame(0, DB::table('seguimiento_egresado.contacto_egresado')->count());
        $this->assertSame(0, DB::table('seguimiento_egresado.egresado')->whereNotNull('fecha_egreso')->count());
        $this->assertSame(['PROXIMO'], DB::table('seguimiento_egresado.historial_estado_academico as h')->join('seguimiento_egresado.cat_estado_academico as c', 'c.id', '=', 'h.id_estado_academico')->distinct()->pluck('c.codigo')->all());

        // 2. Contacto inicial: un enlace por alumno, enviado a su correo institucional
        $this->post(route('seguimiento-egresado.padron.contacto-inicial.cohorte'), ['cohorte' => $cohorte, 'fecha_limite' => now()->addDays(30)->toDateString()])->assertRedirect();
        $enlaces = session('enlaces_contacto');
        $this->assertCount(3, $enlaces);
        Mail::assertSent(CorreoDeInvitacion::class, 3);
        Mail::assertSent(CorreoDeInvitacion::class, fn ($m) => $m->hasTo('lucia.vega@correo.test') && $m->proposito === 'CONTACTO_INICIAL');
        $this->assertSame(3, DB::table('seguimiento_egresado.invitacion_egresado')->where('proposito', 'CONTACTO_INICIAL')->whereNotNull('enviado_en')->count());

        // 3. La alumna abre su enlace y registra su contacto en su expediente
        $lucia = collect($enlaces)->firstWhere('matricula', '2019100001');
        $this->get($lucia['url'])->assertRedirect(route('seguimiento-egresado.egresado.inicio'));
        $this->post(route('seguimiento-egresado.egresado.contacto.guardar'), ['correo_personal' => 'lucia.personal@correo.test', 'celular_whatsapp' => '+51 900 100 001'])
            ->assertRedirect(route('seguimiento-egresado.egresado.inicio'));
        $luciaId = (int) DB::table('seguimiento_egresado.egresado')->where('codigo_matricula', '2019100001')->value('id');
        $this->assertSame('lucia.personal@correo.test', DB::table('seguimiento_egresado.contacto_egresado')->where('id_egresado', $luciaId)->value('correo_personal'));
        $this->get(route('seguimiento-egresado.padron.promocion', ['promocion' => $cohorte, 'estado' => 'todos']))->assertOk()->assertSee('Contacto completado');

        // 4. Egreso: Secretaría confirma la fecha real; el expediente y el contacto se conservan
        // Quien no dejó su contacto aparece en el seguimiento como «Aún no debería egresar».
        $this->get(route('seguimiento-egresado.padron.promocion', ['promocion' => $cohorte, 'estado' => 'todos']))->assertSee('Aún no debería egresar');
        foreach (DB::table('seguimiento_egresado.egresado')->where('id', '!=', $luciaId)->pluck('id') as $id) {
            DB::table('seguimiento_egresado.contacto_egresado')->insert(['id_egresado' => $id, 'correo_personal' => "contacto{$id}@correo.test", 'celular_whatsapp' => '+51 900 000 00'.$id, 'vigente_desde' => now(), 'origen_registro' => 'FORMULARIO']);
        }
        foreach (DB::table('seguimiento_egresado.egresado')->pluck('id') as $id) {
            $this->postJson(route('seguimiento-egresado.padron.confirmar-egreso', $id), ['fecha_egreso' => '2025-07-18'])->assertOk();
        }
        $this->assertSame('2025-I', DB::table('seguimiento_egresado.egresado')->where('id', $luciaId)->value('periodo_egreso'));
        $this->assertSame(1, DB::table('seguimiento_egresado.contacto_egresado')->where('id_egresado', $luciaId)->count());
        $this->assertSame(2, DB::table('seguimiento_egresado.historial_estado_academico')->where('id_egresado', $luciaId)->count(), 'apto + egresado');

        // 5. Campaña de seguimiento por promoción (se abre sola por sus fechas) y envío de enlaces; otra campaña para empleadores
        $this->post(route('seguimiento-egresado.campanias.store'), [
            'nombre' => 'Seguimiento 2026-II', 'tipo' => 'EGRESADOS', 'horizonte_anios' => 1, 'periodo' => '2026-II',
            'fecha_inicio' => now()->subDay()->toDateString(), 'fecha_fin' => now()->addDays(30)->toDateString(), 'cohortes' => [$cohorte],
        ])->assertRedirect();
        $campania = (int) DB::table('seguimiento_egresado.campania_seguimiento')->where('tipo', 'EGRESADOS')->value('id');
        $this->assertSame('ABIERTA', DB::table('seguimiento_egresado.campania_seguimiento')->where('id', $campania)->value('estado'));
        $this->post(route('seguimiento-egresado.campanias.store'), [
            'nombre' => 'Empleadores 2026-II', 'tipo' => 'EMPRESAS', 'horizonte_anios' => 1, 'periodo' => '2026-II',
            'fecha_inicio' => now()->subDay()->toDateString(), 'fecha_fin' => now()->addDays(30)->toDateString(), 'cohortes' => [$cohorte],
        ])->assertRedirect();
        $campaniaEmpresas = (int) DB::table('seguimiento_egresado.campania_seguimiento')->where('tipo', 'EMPRESAS')->value('id');
        Mail::fake();
        $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $campania))->assertRedirect();
        $this->assertCount(3, session('enlaces_emitidos'));
        // Todos dejaron su contacto antes de egresar, así que cada correo va a su correo personal.
        Mail::assertSent(CorreoDeInvitacion::class, 3);
        Mail::assertSent(CorreoDeInvitacion::class, fn ($m) => $m->hasTo('lucia.personal@correo.test') && $m->proposito === 'SEGUIMIENTO');
        foreach (DB::table('seguimiento_egresado.egresado')->where('id', '!=', $luciaId)->pluck('id') as $id) {
            Mail::assertSent(CorreoDeInvitacion::class, fn ($m) => $m->hasTo("contacto{$id}@correo.test"));
        }

        // 6. La egresada responde la encuesta: trabaja y autoriza contactar a su empleador
        $enlaceLucia = collect(session('enlaces_emitidos'))->firstWhere('matricula', '2019100001');
        $this->flushSession();
        $this->actingAs($this->secretaria());
        $this->get($enlaceLucia['url'])->assertRedirect(route('seguimiento-egresado.egresado.inicio'));
        $datos = $this->datosDeEncuesta(['autoriza_contacto_empresa' => '1', 'contacto_jefe' => 'jefe@empresa.test'])
            + ['contacto' => ['correo_personal' => 'lucia.personal@correo.test', 'celular_whatsapp' => '+51 900 100 001']];
        $this->get(route('seguimiento-egresado.egresado.encuesta'))->assertOk();
        $this->post(route('seguimiento-egresado.egresado.encuesta.responder'), $datos)->assertRedirect(route('seguimiento-egresado.egresado.historial'));
        $this->assertSame(1, DB::table('seguimiento_egresado.medicion_laboral')->count());
        $this->assertSame('COMPLETADA', DB::table('seguimiento_egresado.invitacion_egresado')->where('id_campania', $campania)->where('id_egresado', $luciaId)->value('estado'));
        $this->assertSame(2, DB::table('seguimiento_egresado.invitacion_egresado')->where('id_campania', $campania)->where('estado', 'PENDIENTE')->count(), 'quienes no respondieron siguen pendientes');

        // 7. Empleador: invitación de desempeño para esa egresada, y también una encuesta general del mercado
        $this->flushSession();
        $this->actingAs($this->secretaria());
        $this->post(route('seguimiento-egresado.empresas.enlaces'), [
            'id_campania' => $campaniaEmpresas, 'contexto' => 'DESEMPENO', 'id_egresado' => $luciaId, 'organizacion' => 'Empresa Ficticia S.A.C.', 'contacto_empresa' => 'jefe@empresa.test', 'dias_validez' => 7,
        ])->assertSessionHas('enlace_generado');
        $enlaceEmpresa = session('enlace_generado');
        $token = $enlaceEmpresa['token'] ?? basename($enlaceEmpresa['url'] ?? '');
        $this->assertNotEmpty($token);
        $this->post(route('seguimiento-egresado.empresas.enlaces'), ['id_campania' => $campaniaEmpresas, 'contexto' => 'MERCADO', 'organizacion' => 'Empresa del Mercado', 'dias_validez' => 7])->assertSessionHas('enlace_generado');
        $tokenMercado = session('enlace_generado')['token'] ?? basename(session('enlace_generado')['url'] ?? '');

        // La empresa responde con su enlace, sin iniciar sesión
        auth()->logout();
        $this->flushSession();
        $base = ['organizacion' => 'Empresa Ficticia S.A.C.', 'sector' => 'SOFTWARE_TI', 'cargo' => 'Jefa de desarrollo', 'demandas' => ['BASES_DATOS', 'CLOUD'], 'brechas' => ['INGLES'], 'comentario' => 'Comentario ficticio'];
        $this->get(route('seguimiento-egresado.encuesta-empresa', ['token' => $token]))->assertOk();
        $this->post(route('seguimiento-egresado.encuesta-empresa.responder', ['token' => $token]), $base + ['satisfaccion' => 'SATISFECHO'])->assertRedirect();
        $this->post(route('seguimiento-egresado.encuesta-empresa.responder', ['token' => $tokenMercado]), $base)->assertRedirect();
        $this->assertSame(2, DB::table('seguimiento_egresado.respuesta_empresa')->count());
        $this->assertSame(['COMPLETADA'], DB::table('seguimiento_egresado.invitacion_empresa')->distinct()->pluck('estado')->all());

        // 8. Resultados: los indicadores salen de lo respondido, no de datos de prueba
        $this->actingAs($this->secretaria());
        $resultados = app(CalculadoraIndicadores::class)->todos(new FiltroIndicadores);
        foreach (['I1', 'I2', 'I4', 'I5'] as $codigo) {
            $this->assertSame('CALCULADO', $resultados[$codigo]->estado, "{$codigo}: ".$resultados[$codigo]->nota);
        }
        $this->get(route('seguimiento-egresado.indicadores'))->assertOk()->assertDontSee('[DEMO]');
        $this->get(route('seguimiento-egresado.empresas'))->assertOk()->assertSee('Empresa Ficticia S.A.C.');
        $this->get(route('seguimiento-egresado.reportes.indicadores.csv'))->assertOk();
        $this->get(route('seguimiento-egresado.campanias', ['ver' => $campania]))->assertOk()->assertSee('Seguimiento 2026-II');
    }
}
