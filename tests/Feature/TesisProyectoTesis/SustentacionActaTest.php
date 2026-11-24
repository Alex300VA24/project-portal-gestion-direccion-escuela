<?php

namespace Tests\Feature\TesisProyectoTesis;

use App\Modules\TesisProyectoTesis\Models\Acta;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\ResultadoJurado;
use App\Modules\TesisProyectoTesis\Models\Sustentacion;
use App\Modules\TesisProyectoTesis\Support\Estados;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class SustentacionActaTest extends TesisTestCase
{
    private function listo(array $alumnos = ['alumno@portal.local', 'alumno2@portal.local']): Proyecto
    {
        return $this->proyecto($alumnos, Estados::LISTO_SUSTENTAR, Estados::ETAPA_TESIS, 'asesor@portal.local', $this->juradoDemo());
    }

    private function programar(Proyecto $p, ?string $fecha = null, string $hora = '09:00', string $ambiente = 'INFO-5')
    {
        return $this->como('secretaria@portal.local')->post(route('tesis-proyecto-tesis.secretaria.sustentaciones.guardar', $p), [
            'fecha' => $fecha ?? Carbon::today()->toDateString(), 'hora' => $hora, 'ambiente' => $ambiente,
        ]);
    }

    private function resultado(Sustentacion $s, string $jurado, string $resultado, bool $mencion = false)
    {
        return $this->como($jurado)->post(route('tesis-proyecto-tesis.jurado.resultado.registrar', $s), [
            'resultado' => $resultado, 'mencion' => $mencion ? '1' : '0', 'hora_fin' => '11:00',
        ]);
    }

    public function test_programacion_valida_notifica_y_cambia_estado(): void
    {
        $p = $this->listo();

        $this->como('secretaria@portal.local')->get(route('tesis-proyecto-tesis.secretaria.sustentaciones'))->assertOk()->assertSee($p->codigo);
        $this->como('secretaria@portal.local')->get(route('tesis-proyecto-tesis.secretaria.sustentaciones.programar', $p))->assertOk()->assertSee('Agenda de la semana')->assertSee('Dra. Ana Paredes Luna');

        $this->programar($p)->assertRedirect(route('tesis-proyecto-tesis.secretaria.sustentaciones'))->assertSessionHasNoErrors();

        $s = Sustentacion::query()->firstOrFail();
        $this->assertSame(1, $s->numero_intento);
        $this->assertSame('Presencial', $s->modalidad);
        $this->assertSame(Estados::PROGRAMADO, $p->refresh()->estado);
        foreach (['alumno@portal.local', 'alumno2@portal.local', 'asesor@portal.local', 'jurado1@portal.local', 'jurado3@portal.local'] as $c) {
            $this->assertTrue(DB::table('comun.notificacion')->where('id_usuario_destino', $this->idUsuario($c))->where('tipo_evento', 'sustentacion.programada')->exists(), $c);
        }
        $this->como('alumno@portal.local')->get(route('tesis-proyecto-tesis.alumno.panel'))->assertSee($s->fecha_hora->format('d/m/Y H:i'));
    }

    public function test_validaciones_y_choques_de_agenda(): void
    {
        $p = $this->listo(['alumno@portal.local']);
        $otro = $this->listo(['alumno2@portal.local']);

        $this->programar($p, Carbon::yesterday()->toDateString())->assertSessionHasErrors('fecha');
        $this->programar($p, null, '25:00')->assertSessionHasErrors('hora');
        $this->programar($p, null, '09:00', 'NO-EXISTE')->assertSessionHasErrors('ambiente');

        $this->programar($p, Carbon::tomorrow()->toDateString(), '09:00')->assertSessionHasNoErrors();
        // Mismo jurado una hora después: choque de agenda de los jurados.
        $this->programar($otro, Carbon::tomorrow()->toDateString(), '10:00', 'LAB-8')->assertSessionHasErrors('hora');
        // Misma aula: choque del ambiente.
        $this->programar($otro, Carbon::tomorrow()->toDateString(), '10:30', 'INFO-5')->assertSessionHasErrors('ambiente');
        // Ya programado: no se vuelve a programar.
        $this->programar($p, Carbon::tomorrow()->addDay()->toDateString())->assertSessionHasErrors('fecha');

        $noListo = $this->proyecto(['alumno3@portal.local'], Estados::EN_TESIS, Estados::ETAPA_TESIS, 'asesor@portal.local', $this->juradoDemo());
        $this->programar($noListo, Carbon::tomorrow()->addDays(3)->toDateString())->assertSessionHasErrors('fecha');

        $this->assertSame(1, Sustentacion::count());
    }

    public function test_aprobado_por_mayoria_genera_acta_y_al_informar_cierra_el_proceso(): void
    {
        $p = $this->listo();
        $this->programar($p, null, '08:00');
        $s = Sustentacion::query()->firstOrFail();

        $this->resultado($s, 'jurado1@portal.local', 'Aprobatorio', true)->assertSessionHasNoErrors();
        $this->resultado($s, 'jurado1@portal.local', 'Aprobatorio')->assertSessionHasErrors('resultado');

        // Antes de que estén los tres no se ven los resultados individuales.
        $this->como('jurado2@portal.local')->get(route('tesis-proyecto-tesis.jurado.resultado', $s))->assertOk()->assertSee('Registrado')->assertDontSee('>Aprobatorio<', false);

        $this->resultado($s, 'jurado2@portal.local', 'Desaprobatorio')->assertSessionHasNoErrors();
        $this->assertSame(Estados::PROGRAMADO, $p->refresh()->estado);
        $this->resultado($s, 'jurado3@portal.local', 'Aprobatorio', true)->assertSessionHasNoErrors();

        $s->refresh();
        $this->assertSame('Realizada', $s->estado);
        $this->assertSame('Aprobado', $s->resultado);
        $this->assertSame('Mayoria', $s->modo_resultado);
        $this->assertSame(2, $s->total_aprobatorios);
        $this->assertFalse($s->mencion_honrosa, 'La mención honrosa solo se otorga por unanimidad.');
        $this->assertSame('11:00:00', $s->hora_fin);
        $this->assertNull(ResultadoJurado::query()->whereNotNull('nota')->first(), 'Sin nota.');

        $p->refresh();
        $this->assertSame(Estados::SUSTENTADO, $p->estado);
        $this->assertNotNull($p->fecha_aprobacion_tesis);

        $acta = Acta::query()->where('tipo', 'Sustentacion')->firstOrFail();
        $this->assertSame('Aprobado por mayoría', $acta->resultado);
        $this->assertSame('Expedito', $acta->condicion);
        $this->assertNull($acta->nota_final);
        $this->assertMatchesRegularExpression('/^ACT-\d{4}-\d{3}$/', $acta->numero);

        $this->como('secretaria@portal.local')->get(route('tesis-proyecto-tesis.secretaria.actas.ver', $acta))->assertOk()
            ->assertSee('ACTA DE SUSTENTACIÓN DE TESIS')->assertSee('( X ) Aprobado, por mayoría', false)->assertSee('INFO-5')->assertSee('ORCID');

        $this->como('secretaria@portal.local')->post(route('tesis-proyecto-tesis.secretaria.actas.firmada', $acta), ['archivo' => $this->pdf('acta.pdf')])->assertSessionHasNoErrors();
        $this->como('secretaria@portal.local')->post(route('tesis-proyecto-tesis.secretaria.actas.informar', $acta))->assertSessionHasNoErrors();
        $this->assertSame(Estados::TESIS_APROBADA, $p->refresh()->estado);
        $this->assertTrue(DB::table('comun.notificacion')->where('id_usuario_destino', $this->idUsuario('director@portal.local'))->where('tipo_evento', 'acta.informada')->exists());
    }

    public function test_unanimidad_con_mencion_honrosa(): void
    {
        $p = $this->listo(['alumno@portal.local']);
        $this->programar($p, null, '08:00');
        $s = Sustentacion::query()->firstOrFail();

        foreach (['jurado1@portal.local', 'jurado2@portal.local', 'jurado3@portal.local'] as $j) {
            $this->resultado($s, $j, 'Aprobatorio', true)->assertSessionHasNoErrors();
        }

        $s->refresh();
        $this->assertSame('Unanimidad', $s->modo_resultado);
        $this->assertTrue($s->mencion_honrosa);
        $this->assertSame('Aprobado con mención honrosa', Acta::query()->firstOrFail()->resultado);
    }

    public function test_desaprobado_permite_reprogramar_sin_limite(): void
    {
        $p = $this->listo(['alumno@portal.local']);
        $this->programar($p, null, '08:00');
        $s1 = Sustentacion::query()->firstOrFail();

        $this->resultado($s1, 'jurado1@portal.local', 'Aprobatorio');
        $this->resultado($s1, 'jurado2@portal.local', 'Desaprobatorio');
        $this->resultado($s1, 'jurado3@portal.local', 'Desaprobatorio');

        $this->assertSame('Desaprobado', $s1->refresh()->resultado);
        $this->assertSame(Estados::DESAPROBADO, $p->refresh()->estado);
        $acta = Acta::query()->firstOrFail();
        $this->assertSame('Impedido', $acta->condicion);

        // Se archiva el acta desaprobada; informar no cierra el proceso.
        $this->como('secretaria@portal.local')->post(route('tesis-proyecto-tesis.secretaria.actas.firmada', $acta), ['archivo' => $this->pdf()]);
        $this->como('secretaria@portal.local')->post(route('tesis-proyecto-tesis.secretaria.actas.informar', $acta))->assertSessionHasNoErrors();
        $this->assertSame(Estados::DESAPROBADO, $p->refresh()->estado);

        // Nueva fecha: intento 2.
        $this->programar($p, Carbon::today()->toDateString(), '15:00')->assertSessionHasNoErrors();
        $s2 = Sustentacion::query()->where('numero_intento', 2)->firstOrFail();
        $this->assertSame(Estados::PROGRAMADO, $p->refresh()->estado);
        foreach (['jurado1@portal.local', 'jurado2@portal.local', 'jurado3@portal.local'] as $j) {
            $this->resultado($s2, $j, 'Aprobatorio')->assertSessionHasNoErrors();
        }
        $this->assertSame(Estados::SUSTENTADO, $p->refresh()->estado);
        $this->assertSame(2, Acta::query()->where('tipo', 'Sustentacion')->count());
    }

    public function test_resultado_solo_desde_el_dia_y_por_jurado_activo(): void
    {
        $p = $this->listo(['alumno@portal.local']);
        $this->programar($p, Carbon::tomorrow()->toDateString(), '09:00');
        $s = Sustentacion::query()->firstOrFail();

        $this->resultado($s, 'jurado1@portal.local', 'Aprobatorio')->assertSessionHasErrors('resultado');
        $this->resultado($s, 'profesor@portal.local', 'Aprobatorio')->assertForbidden();
        $this->resultado($s, 'asesor@portal.local', 'Aprobatorio')->assertForbidden();
        $this->como('alumno@portal.local')->post(route('tesis-proyecto-tesis.jurado.resultado.registrar', $s), ['resultado' => 'Aprobatorio'])->assertForbidden();
        $this->resultado($s, 'jurado1@portal.local', 'Excelente')->assertSessionHasErrors('resultado');
        $this->assertSame(0, ResultadoJurado::count());
    }

    public function test_autorizacion_de_programacion(): void
    {
        $p = $this->listo();

        foreach (['director@portal.local', 'jurado1@portal.local', 'alumno@portal.local'] as $c) {
            $this->como($c)->get(route('tesis-proyecto-tesis.secretaria.sustentaciones'))->assertForbidden();
            $this->como($c)->post(route('tesis-proyecto-tesis.secretaria.sustentaciones.guardar', $p), ['fecha' => Carbon::today()->toDateString(), 'hora' => '09:00', 'ambiente' => 'INFO-5'])->assertForbidden();
        }
        $this->assertSame(0, Sustentacion::count());
    }
}
