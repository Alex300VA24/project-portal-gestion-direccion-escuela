<?php

namespace Tests\Feature\TesisProyectoTesis;

use App\Modules\TesisProyectoTesis\Models\Observacion;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\ReporteSimilitud;
use App\Modules\TesisProyectoTesis\Models\Revision;
use App\Modules\TesisProyectoTesis\Support\Estados;
use Illuminate\Support\Facades\DB;

final class VerificacionAsesorTest extends TesisTestCase
{
    private function proyectoEnDesarrollo(): Proyecto
    {
        return $this->proyecto(['alumno@portal.local', 'alumno2@portal.local'], Estados::EN_DESARROLLO, asesor: 'asesor@portal.local');
    }

    private function verificar(Proyecto $p, array $marcas, array $obs = [], string $accion = 'conformidad')
    {
        return $this->como('asesor@portal.local')->post(route('tesis-proyecto-tesis.asesor.verificar.guardar', $p), ['marcas' => $marcas, 'obs' => $obs, 'accion' => $accion]);
    }

    private function turnitin(Proyecto $p, string $porcentaje, bool $conformidad = true)
    {
        return $this->como('asesor@portal.local')->post(route('tesis-proyecto-tesis.asesor.similitud.registrar', $p), [
            'porcentaje' => $porcentaje, 'archivo' => $this->pdf('turnitin.pdf'), 'indicaciones' => 'Parafrasear 2.3', 'conformidad' => $conformidad ? '1' : '0',
        ]);
    }

    public function test_rubrica_del_proyecto_tiene_18_criterios_y_de_tesis_25(): void
    {
        $this->assertCount(18, $this->marcas('Proyecto'));
        $this->assertCount(25, $this->marcas('Tesis'));
        $this->assertArrayHasKey(20, $this->marcas('Proyecto'));
        $this->assertArrayNotHasKey(20, $this->marcas('Tesis'));
    }

    public function test_conformidad_completa_y_turnitin_apto_pasa_a_verificado(): void
    {
        $p = $this->proyectoEnDesarrollo();
        $e = $this->entrega($p);

        $this->como('asesor@portal.local')->get(route('tesis-proyecto-tesis.asesor.verificar', $p))->assertOk()->assertSee('Verificar con la rúbrica')->assertSee('18 criterios');

        $this->verificar($p, $this->marcas('Proyecto', [6 => 'no_aplica']), [6 => 'Investigación cualitativa'])
            ->assertRedirect(route('tesis-proyecto-tesis.asesor.similitud', $p))->assertSessionHasNoErrors();

        $r = Revision::query()->where('tipo', 'Verificacion')->firstOrFail();
        $this->assertTrue($r->conforme);
        $this->assertTrue($r->cumple_todo);
        $this->assertSame(18, $r->detalles()->count());
        $this->assertSame(0, Observacion::query()->where('estado', 'Pendiente')->count());

        $this->como('asesor@portal.local')->get(route('tesis-proyecto-tesis.asesor.similitud', $p))->assertOk()->assertSee('Conforme');
        $this->turnitin($p, '14.5')->assertRedirect(route('tesis-proyecto-tesis.asesor.proyectos'))->assertSessionHasNoErrors();

        $this->assertSame(Estados::VERIFICADO_ASESOR, $p->refresh()->estado);
        $rep = ReporteSimilitud::query()->firstOrFail();
        $this->assertSame('Apto', $rep->resultado);
        $this->assertSame($e->id_entrega, $rep->id_entrega);
        $this->assertDatabaseHas('tesis.historial_estado', ['id_proyecto' => $p->id_proyecto, 'estado_nuevo' => 'VerificadoAsesor', 'cambiado_por' => $this->idUsuario('asesor@portal.local')]);
        $this->assertTrue(DB::table('comun.notificacion')->where('id_usuario_destino', $this->idUsuario('director@portal.local'))->where('tipo_evento', 'asesor.conformidad')->exists());

        $this->como('director@portal.local')->get(route('tesis-proyecto-tesis.director.panel'))->assertSee($p->codigo);
    }

    public function test_criterios_no_cumple_exigen_observacion_y_bloquean_conformidad(): void
    {
        $p = $this->proyectoEnDesarrollo();
        $e = $this->entrega($p);
        $marcas = $this->marcas('Proyecto', [4 => 'no_cumple', 11 => 'no_cumple']);

        $this->verificar($p, $marcas, [4 => 'Reformula el problema'], 'observaciones')->assertSessionHasErrors('obs.11');
        $this->verificar($p, $marcas, [4 => 'Reformula', 11 => 'Citas sin DOI'], 'conformidad')->assertSessionHasErrors('conformidad');
        $this->assertSame(0, Revision::count());

        $this->verificar($p, $marcas, [4 => 'Reformula el problema', 11 => 'Citas sin DOI'], 'observaciones')->assertSessionHasNoErrors();
        $r = Revision::query()->firstOrFail();
        $this->assertFalse($r->conforme);
        $this->assertFalse($r->cumple_todo);
        $this->assertSame(2, Observacion::query()->whereNotNull('id_criterio')->where('estado', 'Pendiente')->count());
        $this->assertSame('Observada', $e->refresh()->estado);

        // Misma versión: no se vuelve a verificar; Turnitin bloqueado.
        $this->verificar($p, $this->marcas('Proyecto'))->assertSessionHasErrors('marcas');
        $this->turnitin($p, '10')->assertSessionHasErrors('porcentaje');
        $this->assertSame(Estados::EN_DESARROLLO, $p->refresh()->estado);
        $this->como('alumno@portal.local')->get(route('tesis-proyecto-tesis.alumno.proyecto.observaciones', $p))->assertSee('Rúbrica · criterio 11');
    }

    public function test_marcas_incompletas_o_invalidas(): void
    {
        $p = $this->proyectoEnDesarrollo();
        $this->entrega($p);
        $marcas = $this->marcas('Proyecto');
        unset($marcas[5]);

        $this->verificar($p, $marcas)->assertSessionHasErrors('marcas.5');
        $this->verificar($p, $this->marcas('Proyecto', [3 => 'no_aplica']))->assertSessionHasErrors('marcas.3');
        $this->verificar($p, $this->marcas('Proyecto') + [21 => 'cumple'])->assertSessionHasErrors('marcas');
        $this->verificar($p, $this->marcas('Proyecto', [1 => 'quizas']))->assertSessionHasErrors('marcas.1');
        $this->assertSame(0, Revision::count());
    }

    public function test_sin_version_final_no_hay_verificacion(): void
    {
        $p = $this->proyectoEnDesarrollo();
        $this->entrega($p, 'Avance', 'SinObservaciones');

        $this->como('asesor@portal.local')->get(route('tesis-proyecto-tesis.asesor.verificar', $p))->assertOk()->assertSee('Aún no hay una versión final');
        $this->verificar($p, $this->marcas())->assertSessionHasErrors('marcas');

        $this->entrega($p, 'Avance', 'Culminada');
        $this->verificar($p, $this->marcas())->assertSessionHasNoErrors();
    }

    public function test_similitud_mayor_al_limite_vuelve_al_estudiante(): void
    {
        $p = $this->proyectoEnDesarrollo();
        $e = $this->entrega($p);
        $this->verificar($p, $this->marcas());

        $this->turnitin($p, '26.40', false)->assertRedirect(route('tesis-proyecto-tesis.asesor.similitud', $p));
        $this->assertSame('NoApto', ReporteSimilitud::firstOrFail()->resultado);
        $this->assertSame(Estados::EN_DESARROLLO, $p->refresh()->estado);
        $this->assertSame('Observada', $e->refresh()->estado);
        $this->assertTrue(DB::table('comun.notificacion')->where('id_usuario_destino', $this->idUsuario('alumno@portal.local'))->where('tipo_evento', 'similitud.no_apto')->exists());

        // Nueva versión, nueva verificación y nuevo Turnitin.
        $this->entrega($p);
        $this->verificar($p, $this->marcas())->assertSessionHasNoErrors();
        $this->turnitin($p, '21')->assertSessionHasNoErrors();
        $this->assertSame(Estados::VERIFICADO_ASESOR, $p->refresh()->estado);
        $this->assertSame(2, ReporteSimilitud::count());
    }

    public function test_apto_sin_conformidad_y_observaciones_abiertas_bloquean(): void
    {
        $p = $this->proyectoEnDesarrollo();
        $avance = $this->entrega($p, 'Avance');
        $this->como('asesor@portal.local')->post(route('tesis-proyecto-tesis.asesor.revisar.guardar', [$p, $avance]), ['dictamen' => 'Observado', 'observaciones' => [['seccion' => 'Título', 'descripcion' => 'Ajusta el título']]]);
        $this->entrega($p);
        $this->verificar($p, $this->marcas());

        $this->turnitin($p, '10', false)->assertSessionHasErrors('conformidad');
        $this->turnitin($p, '10')->assertSessionHasErrors('porcentaje');
        $this->turnitin($p, '150')->assertSessionHasErrors('porcentaje');
        $this->assertSame(0, ReporteSimilitud::count());
        $this->assertSame(Estados::EN_DESARROLLO, $p->refresh()->estado);
    }

    public function test_autorizacion_de_rubrica_y_turnitin(): void
    {
        $p = $this->proyectoEnDesarrollo();
        $this->entrega($p);

        $this->como('profesor@portal.local')->get(route('tesis-proyecto-tesis.asesor.verificar', $p))->assertForbidden();
        $this->como('profesor@portal.local')->post(route('tesis-proyecto-tesis.asesor.verificar.guardar', $p), ['marcas' => $this->marcas(), 'accion' => 'conformidad'])->assertForbidden();
        $this->como('alumno@portal.local')->post(route('tesis-proyecto-tesis.asesor.similitud.registrar', $p), ['porcentaje' => 5])->assertForbidden();
        $this->como('director@portal.local')->get(route('tesis-proyecto-tesis.asesor.similitud', $p))->assertForbidden();
        $this->assertSame(0, Revision::count());
    }
}
