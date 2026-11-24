<?php

namespace Tests\Feature\TesisProyectoTesis;

use App\Modules\TesisProyectoTesis\Models\Entrega;
use App\Modules\TesisProyectoTesis\Models\Observacion;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\Revision;
use App\Modules\TesisProyectoTesis\Support\Estados;
use Illuminate\Support\Facades\DB;

final class AvancesRevisionTest extends TesisTestCase
{
    private function subir(Proyecto $p, string $alumno = 'alumno@portal.local', string $tipo = 'Avance', int $semana = 6): Entrega
    {
        $this->como($alumno)->post(route('tesis-proyecto-tesis.alumno.proyecto.avances.subir', $p), [
            'tipo' => $tipo, 'numero_semana' => $semana, 'comentario' => 'Avance de prueba', 'archivo' => $this->pdf('avance.pdf'),
        ])->assertRedirect(route('tesis-proyecto-tesis.alumno.proyecto.avances', $p))->assertSessionHasNoErrors();

        return Entrega::query()->where('id_proyecto', $p->id_proyecto)->latest('id_entrega')->firstOrFail();
    }

    private function revisar(Proyecto $p, Entrega $e, array $datos)
    {
        return $this->como('asesor@portal.local')->post(route('tesis-proyecto-tesis.asesor.revisar.guardar', [$p, $e]), $datos);
    }

    public function test_ciclo_completo_avance_observacion_respuesta_levantada(): void
    {
        $p = $this->proyecto(['alumno@portal.local', 'alumno2@portal.local'], Estados::CON_ASESOR, asesor: 'asesor@portal.local');

        // 1. Primer avance: pasa a EnDesarrollo y avisa al asesor.
        $v1 = $this->subir($p);
        $this->assertSame(1, $v1->version);
        $this->assertSame(Estados::EN_DESARROLLO, $p->refresh()->estado);
        $this->assertTrue(DB::table('comun.notificacion')->where('id_usuario_destino', $this->idUsuario('asesor@portal.local'))->where('tipo_evento', 'entrega.subida')->exists());

        $this->como('asesor@portal.local')->get(route('tesis-proyecto-tesis.asesor.proyectos'))->assertOk()->assertSee($p->codigo)->assertSee('Revisar avance');
        $this->como('asesor@portal.local')->get(route('tesis-proyecto-tesis.asesor.revisar', [$p, $v1]))->assertOk()->assertSee('Revisar avance');

        // 2. El asesor observa.
        $this->revisar($p, $v1, [
            'dictamen' => 'Observado',
            'observaciones' => [
                ['seccion' => 'Matriz de consistencia', 'descripcion' => 'Define las dimensiones de la variable.'],
                ['seccion' => 'Marco teórico', 'descripcion' => ''],
            ],
        ])->assertRedirect(route('tesis-proyecto-tesis.asesor.proyectos'))->assertSessionHasNoErrors();

        $this->assertSame('Observada', $v1->refresh()->estado);
        $obs = Observacion::query()->firstOrFail();
        $this->assertSame(1, Observacion::count(), 'La fila vacía no se guarda.');
        $this->assertSame('Pendiente', $obs->estado);

        $this->como('alumno2@portal.local')->get(route('tesis-proyecto-tesis.alumno.observaciones'))->assertRedirect(route('tesis-proyecto-tesis.alumno.proyecto.observaciones', $p));
        $this->como('alumno2@portal.local')->get(route('tesis-proyecto-tesis.alumno.proyecto.observaciones', $p))->assertOk()->assertSee('Define las dimensiones de la variable.');

        // 3. Segundo avance y respuesta indicando la entrega.
        $v2 = $this->subir($p, 'alumno2@portal.local', semana: 7);
        $this->assertSame(2, $v2->version);
        $this->como('alumno2@portal.local')->post(route('tesis-proyecto-tesis.alumno.observaciones.responder', $obs->uuid), [
            'respuesta' => 'Se agregaron tres dimensiones en la tabla 3.', 'entrega' => $v2->uuid,
        ])->assertSessionHasNoErrors();
        $obs->refresh();
        $this->assertSame('Respondida', $obs->estado);
        $this->assertSame($v2->id_entrega, $obs->id_entrega_correccion);

        // 4. El asesor revisa v2 sin observaciones y levanta la anterior.
        $this->revisar($p, $v2, ['dictamen' => 'SinObservaciones', 'levantar' => [$obs->uuid]])->assertSessionHasNoErrors();
        $this->assertSame('Levantada', $obs->refresh()->estado);
        $this->assertNotNull($obs->subsanada_en);
        $this->assertSame('SinObservaciones', $v2->refresh()->estado);

        // 5. Versión final culminada.
        $v3 = $this->subir($p, tipo: 'Final', semana: 8);
        $this->revisar($p, $v3, ['dictamen' => 'Culminado'])->assertSessionHasNoErrors();
        $this->assertSame('Culminada', $v3->refresh()->estado);
        $this->como('alumno@portal.local')->get(route('tesis-proyecto-tesis.alumno.panel'))->assertOk()->assertSee('Culminada');
    }

    public function test_reglas_del_dictamen_y_doble_revision(): void
    {
        $p = $this->proyecto(['alumno@portal.local'], Estados::EN_DESARROLLO, asesor: 'asesor@portal.local');
        $e = $this->subir($p);

        $this->revisar($p, $e, ['dictamen' => 'Observado'])->assertSessionHasErrors('observaciones');
        $this->revisar($p, $e, ['dictamen' => 'SinObservaciones', 'observaciones' => [['seccion' => 'Título', 'descripcion' => 'Muy largo el título']]])->assertSessionHasErrors('dictamen');
        $this->revisar($p, $e, ['dictamen' => 'Otro'])->assertSessionHasErrors('dictamen');
        $this->revisar($p, $e, ['dictamen' => 'Observado', 'observaciones' => [['seccion' => 'Inventada', 'descripcion' => 'Texto suficiente']]])->assertSessionHasErrors('observaciones.0.seccion');
        $this->assertSame(0, Revision::count());

        $this->revisar($p, $e, ['dictamen' => 'SinObservaciones'])->assertSessionHasNoErrors();
        $this->revisar($p, $e, ['dictamen' => 'SinObservaciones'])->assertSessionHasErrors('dictamen');
        $this->assertSame(1, Revision::count());
    }

    public function test_solo_se_levanta_lo_respondido_y_por_su_autor(): void
    {
        $p = $this->proyecto(['alumno@portal.local'], Estados::EN_DESARROLLO, asesor: 'asesor@portal.local');
        $e = $this->subir($p);
        $this->revisar($p, $e, ['dictamen' => 'Observado', 'observaciones' => [['seccion' => 'Título', 'descripcion' => 'Ajusta el título.']]]);
        $obs = Observacion::query()->firstOrFail();

        $this->como('asesor@portal.local')->post(route('tesis-proyecto-tesis.asesor.observaciones.levantar', [$p, $obs->uuid]))->assertSessionHasErrors('levantar');
        $this->assertSame('Pendiente', $obs->refresh()->estado);
    }

    public function test_validaciones_de_entrega_y_respuesta(): void
    {
        $p = $this->proyecto(['alumno@portal.local'], Estados::CON_ASESOR, asesor: 'asesor@portal.local');

        $this->como('alumno@portal.local')->post(route('tesis-proyecto-tesis.alumno.proyecto.avances.subir', $p), [
            'tipo' => 'Otro', 'numero_semana' => 40, 'archivo' => \Illuminate\Http\UploadedFile::fake()->create('a.exe', 10, 'application/octet-stream'),
        ])->assertSessionHasErrors(['tipo', 'numero_semana', 'archivo']);
        $this->assertSame(0, Entrega::count());

        $e = $this->subir($p);
        $this->revisar($p, $e, ['dictamen' => 'Observado', 'observaciones' => [['seccion' => 'Título', 'descripcion' => 'Ajusta el título.']]]);
        $obs = Observacion::query()->firstOrFail();

        $this->como('alumno@portal.local')->post(route('tesis-proyecto-tesis.alumno.observaciones.responder', $obs->uuid), ['respuesta' => ''])->assertSessionHasErrors('respuesta');
        $this->como('alumno@portal.local')->post(route('tesis-proyecto-tesis.alumno.observaciones.responder', $obs->uuid), ['respuesta' => 'Corregido el título', 'entrega' => $e->uuid])->assertSessionHasErrors('entrega');
    }

    public function test_no_se_reciben_entregas_sin_asesor_o_en_estados_cerrados(): void
    {
        $sinAsesor = $this->proyecto(['alumno@portal.local']);
        $this->como('alumno@portal.local')->post(route('tesis-proyecto-tesis.alumno.proyecto.avances.subir', $sinAsesor), [
            'tipo' => 'Avance', 'numero_semana' => 3, 'archivo' => $this->pdf(),
        ])->assertSessionHasErrors('archivo');

        $verificado = $this->proyecto(['alumno2@portal.local'], Estados::VERIFICADO_ASESOR, asesor: 'asesor@portal.local');
        $this->como('alumno2@portal.local')->post(route('tesis-proyecto-tesis.alumno.proyecto.avances.subir', $verificado), [
            'tipo' => 'Avance', 'numero_semana' => 3, 'archivo' => $this->pdf(),
        ])->assertSessionHasErrors('archivo');

        $this->assertSame(0, Entrega::count());
    }

    public function test_acceso_cruzado_alumno_y_asesor(): void
    {
        $p = $this->proyecto(['alumno@portal.local'], Estados::EN_DESARROLLO, asesor: 'asesor@portal.local');
        $ajeno = $this->proyecto(['alumno2@portal.local'], Estados::EN_DESARROLLO, asesor: 'profesor@portal.local');
        $e = $this->subir($p);
        $this->revisar($p, $e, ['dictamen' => 'Observado', 'observaciones' => [['seccion' => 'Título', 'descripcion' => 'Ajusta el título.']]]);
        $obs = Observacion::query()->firstOrFail();

        // Alumno ajeno
        $this->como('alumno2@portal.local')->get(route('tesis-proyecto-tesis.alumno.proyecto.avances', $p))->assertForbidden();
        $this->como('alumno2@portal.local')->post(route('tesis-proyecto-tesis.alumno.proyecto.avances.subir', $p), ['tipo' => 'Avance', 'numero_semana' => 1, 'archivo' => $this->pdf()])->assertForbidden();
        $this->como('alumno2@portal.local')->post(route('tesis-proyecto-tesis.alumno.observaciones.responder', $obs->uuid), ['respuesta' => 'Intento ajeno'])->assertForbidden();
        $this->como('alumno2@portal.local')->get(route('tesis-proyecto-tesis.archivos.descargar', ['entrega', $e->uuid]))->assertForbidden();

        // Asesor de otro proyecto y jurado
        $this->como('profesor@portal.local')->get(route('tesis-proyecto-tesis.asesor.revisar', [$p, $e]))->assertForbidden();
        $this->como('profesor@portal.local')->post(route('tesis-proyecto-tesis.asesor.revisar.guardar', [$p, $e]), ['dictamen' => 'SinObservaciones'])->assertForbidden();
        $this->como('profesor@portal.local')->get(route('tesis-proyecto-tesis.asesor.proyectos'))->assertOk()->assertDontSee($p->codigo)->assertSee($ajeno->codigo);

        // Entrega de otro proyecto en la URL del propio (binding con alcance)
        $eAjena = $this->subir($ajeno, 'alumno2@portal.local');
        $this->como('asesor@portal.local')->get(route('tesis-proyecto-tesis.asesor.revisar', [$p, $eAjena]))->assertNotFound();
        $this->como('asesor@portal.local')->get('/tesis-proyecto-tesis/asesor/proyectos/'.$p->uuid.'/entregas/'.$e->id_entrega.'/revisar')->assertNotFound();

        // Roles sin acceso al carril del asesor
        $this->como('alumno@portal.local')->get(route('tesis-proyecto-tesis.asesor.proyectos'))->assertForbidden();
        $this->como('director@portal.local')->get(route('tesis-proyecto-tesis.asesor.proyecto', $p))->assertForbidden();
    }
}
