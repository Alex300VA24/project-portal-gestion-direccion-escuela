<?php

namespace Tests\Feature\InvestigacionFormativa;

use App\Modules\InvestigacionFormativa\Exceptions\VersionDesactualizada;
use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;
use App\Modules\InvestigacionFormativa\Services\DecisionTrabajoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Feature\InvestigacionFormativa\Concerns\CreaDatosInvestigacion;
use Tests\TestCase;

final class DecisionesTrabajoTest extends TestCase
{
    use CreaDatosInvestigacion;
    use DatabaseTransactions;

    private array $datos;

    /** @var array<int, int> */
    private array $entregables = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL.');
        }

        $this->datos = $this->crearDatosInvestigacion();
        $this->crearGruposInvestigacion();
        $this->crearTipoProducto();
        $this->configurarSeccionCompleta(9901);

        foreach (DB::table('investigacion_formativa.entregable')
            ->where('id_seccion', 9901)
            ->orderBy('orden')
            ->pluck('id_entregable', 'orden') as $orden => $idEntregable) {
            $this->entregables[(int) $orden] = (int) $idEntregable;
        }

        $this->crearProyecto(9961, 9951, 'Proyecto aprobable');
        $this->crearProyecto(9962, 9954, 'Proyecto alterno');
        $this->crearUnidadesEvaluadas(9961, 9101, 9301);
        $this->crearUnidadesEvaluadas(9962, 9201, 9311);
    }

    public function test_la_aprobacion_final_exige_las_tres_unidades_evaluadas_y_registra_evidencia(): void
    {
        DB::table('investigacion_formativa.evaluacion')->where('id_evaluacion', 9303)->delete();

        $this->postAprobar(9951, version: 0)
            ->assertRedirect(route('investigacion-formativa.docente.grupos.show', [9901, 9951]))
            ->assertSessionHasErrors('decision');

        $this->assertSame(0, $this->decisionesCount('aprobacion'));

        $this->evaluarEntrega(9103, 9303);

        $this->postAprobar(9951, version: 0)
            ->assertRedirect(route('investigacion-formativa.docente.grupos.show', [9901, 9951]))
            ->assertSessionHas('status');

        $fila = DB::table('investigacion_formativa.decision_trabajo')
            ->where('tipo', 'aprobacion')
            ->first();

        $this->assertNotNull($fila);
        $this->assertSame('registrar', $fila->accion);
        $this->assertSame(1, (int) $fila->version);
        $this->assertSame((int) $this->datos['id_docente_a'], (int) $fila->id_docente);
        $this->assertSame($this->datos['users']['docente_a']->id, (int) $fila->user_id);

        $evidencia = json_decode((string) $fila->evidencia, true);
        $this->assertIsArray($evidencia);
        $this->assertSame([1, 2, 3], array_map('intval', array_keys($evidencia['unidades'])));
        $this->assertSame(9301, $evidencia['unidades']['1']['id_evaluacion']);
        $this->assertSame(1, $evidencia['unidades']['1']['version_evaluacion']);

        $this->actingAs($this->datos['users']['docente_a'])
            ->get(route('investigacion-formativa.docente.grupos.show', [9901, 9951]))
            ->assertOk()
            ->assertSee('Trabajo aprobado');
    }

    public function test_no_se_puede_aprobar_sin_permiso_ni_en_una_seccion_cerrada(): void
    {
        $this->actingAs($this->datos['users']['docente_b'])
            ->post(route('investigacion-formativa.docente.decisiones.aprobar', [9901, 9951]), ['version' => 0])
            ->assertForbidden();

        $this->actingAs($this->datos['users']['director'])
            ->post(route('investigacion-formativa.docente.decisiones.aprobar', [9901, 9951]), ['version' => 0])
            ->assertForbidden();

        DB::table('core.seccion')->where('id_seccion', 9901)->update(['estado' => 'Cerrada']);

        $this->actingAs($this->datos['users']['docente_a'])
            ->post(route('investigacion-formativa.docente.decisiones.aprobar', [9901, 9951]), ['version' => 0])
            ->assertForbidden();

        $this->assertSame(0, $this->decisionesCount('aprobacion'));
    }

    public function test_una_seccion_deshabilitada_rechaza_la_decision_sin_registrarla(): void
    {
        DB::table('investigacion_formativa.seccion_configuracion')
            ->where('id_seccion', 9901)
            ->update(['habilitada' => false]);

        $this->postAprobar(9951, version: 0)
            ->assertRedirect(route('investigacion-formativa.docente.grupos.show', [9901, 9951]))
            ->assertSessionHasErrors('decision');

        $this->assertSame(0, $this->decisionesCount('aprobacion'));
    }

    public function test_la_seleccion_requiere_una_aprobacion_vigente(): void
    {
        $this->postSeleccionar(9951, version: 0)
            ->assertRedirect(route('investigacion-formativa.docente.grupos.show', [9901, 9951]))
            ->assertSessionHasErrors('decision');

        $this->assertSame(0, $this->decisionesCount('seleccion'));

        $this->postAprobar(9951, version: 0)->assertSessionHas('status');

        $this->postSeleccionar(9951, version: 0)->assertSessionHas('status');

        $this->assertSame(1, $this->decisionesCount('seleccion'));

        $estado = app(DecisionTrabajoService::class)->estadoDeSeccion(9901);
        $this->assertSame(1, $estado->aprobados);
        $this->assertSame(1, $estado->destacados);
        $this->assertSame(9961, $estado->seleccion->id_proyecto);
        $this->assertTrue($estado->por_grupo[9951]->destacado);
        $this->assertFalse($estado->por_grupo[9954]->destacado);
    }

    public function test_la_seleccion_manual_reemplaza_al_anterior_y_conserva_el_historial(): void
    {
        $this->postAprobar(9951, version: 0);
        $this->postAprobar(9954, version: 0);

        $this->postSeleccionar(9951, version: 0)->assertSessionHas('status');

        $estado = app(DecisionTrabajoService::class)->estadoDeSeccion(9901);
        $this->assertTrue($estado->por_grupo[9951]->destacado);

        $this->postSeleccionar(9954, version: 1)->assertSessionHas('status');

        $estado = app(DecisionTrabajoService::class)->estadoDeSeccion(9901);
        $this->assertFalse($estado->por_grupo[9951]->destacado);
        $this->assertTrue($estado->por_grupo[9954]->destacado);
        $this->assertSame(9962, $estado->seleccion->id_proyecto);
        $this->assertSame(2, $this->decisionesCount('seleccion'));

        $this->actingAs($this->datos['users']['docente_a'])
            ->delete(route('investigacion-formativa.docente.decisiones.retirar', [9901, 9954]), [
                'version' => 2,
                'motivo' => 'El grupo solicitó revisar el producto final.',
            ])
            ->assertSessionHas('status');

        $estado = app(DecisionTrabajoService::class)->estadoDeSeccion(9901);
        $this->assertSame(0, $estado->destacados);
        $this->assertNull($estado->seleccion);

        $historial = app(DecisionTrabajoService::class)->historialSeleccion(9901);
        $this->assertCount(3, $historial);
        $this->assertSame('retirar', $historial[0]->accion);
        $this->assertSame('El grupo solicitó revisar el producto final.', $historial[0]->motivo);
    }

    public function test_retirar_exige_que_el_grupo_sea_el_destacado_vigente(): void
    {
        $this->postAprobar(9951, version: 0);

        $this->actingAs($this->datos['users']['docente_a'])
            ->delete(route('investigacion-formativa.docente.decisiones.retirar', [9901, 9951]), ['version' => 0])
            ->assertSessionHasErrors('decision');

        $this->assertSame(0, $this->decisionesCount('seleccion'));
    }

    public function test_la_evidencia_cambiada_marca_las_decisiones_desactualizadas(): void
    {
        $this->postAprobar(9951, version: 0);
        $this->postSeleccionar(9951, version: 0);

        $this->reemplazarUnidad(9961, orden: 1, idEntrega: 9104, idEvaluacion: 9304);

        $estado = app(DecisionTrabajoService::class)->estadoDeSeccion(9901);
        $this->assertSame(0, $estado->aprobados);
        $this->assertTrue($estado->por_grupo[9951]->aprobacion_desactualizada);
        $this->assertSame(0, $estado->destacados);
        $this->assertTrue($estado->por_grupo[9951]->seleccion_desactualizada);

        $this->postSeleccionar(9951, version: 1)
            ->assertRedirect(route('investigacion-formativa.docente.grupos.show', [9901, 9951]))
            ->assertSessionHasErrors('decision');

        $this->postAprobar(9951, version: 1)->assertSessionHas('status');
        $this->postSeleccionar(9951, version: 1)->assertSessionHas('status');

        $estado = app(DecisionTrabajoService::class)->estadoDeSeccion(9901);
        $this->assertSame(1, $estado->aprobados);
        $this->assertSame(1, $estado->destacados);
        $this->assertFalse($estado->por_grupo[9951]->aprobacion_desactualizada);
        $this->assertFalse($estado->por_grupo[9951]->seleccion_desactualizada);
    }

    public function test_una_version_de_formulario_antigua_se_rechaza(): void
    {
        $this->postAprobar(9951, version: 0)->assertSessionHas('status');

        $this->postAprobar(9951, version: 0)
            ->assertRedirect(route('investigacion-formativa.docente.grupos.show', [9901, 9951]))
            ->assertSessionHasErrors('decision');

        $this->assertSame(1, $this->decisionesCount('aprobacion'));

        $this->postAprobar(9951, version: 1)->assertSessionHas('status');
        $this->assertSame(2, $this->decisionesCount('aprobacion'));
    }

    public function test_dos_solicitudes_de_seleccion_con_la_misma_version_registran_una_sola(): void
    {
        $this->postAprobar(9951, version: 0);
        $this->postAprobar(9954, version: 0);

        $actor = app(AccesoInvestigacionService::class)->resolver($this->datos['users']['docente_a']);
        $servicio = app(DecisionTrabajoService::class);

        $servicio->seleccionar($actor, 9961, 0);

        try {
            $servicio->seleccionar($actor, 9962, 0);
            $this->fail('La segunda selección debía rechazarse por versión desactualizada.');
        } catch (VersionDesactualizada) {
            $this->assertTrue(true);
        }

        $this->assertSame(1, $this->decisionesCount('seleccion'));

        $estado = $servicio->estadoDeSeccion(9901);
        $this->assertSame(9961, $estado->seleccion->id_proyecto);
    }

    public function test_el_reporte_del_director_muestra_aprobados_y_destacados(): void
    {
        $this->postAprobar(9951, version: 0);
        $this->postSeleccionar(9951, version: 0);

        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.resultados', 9901))
            ->assertOk()
            ->assertSee('Aprobación final y trabajo destacado')
            ->assertSee('Grupo A')
            ->assertSee('Aprobado')
            ->assertSee('Destacado');

        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.index'))
            ->assertOk()
            ->assertSee('Trabajos aprobados')
            ->assertSee('Trabajos destacados');
    }

    private function postAprobar(int $idGrupo, int $version): TestResponse
    {
        return $this->actingAs($this->datos['users']['docente_a'])
            ->from(route('investigacion-formativa.docente.grupos.show', [9901, $idGrupo]))
            ->post(route('investigacion-formativa.docente.decisiones.aprobar', [9901, $idGrupo]), [
                'version' => $version,
            ]);
    }

    private function postSeleccionar(int $idGrupo, int $version): TestResponse
    {
        return $this->actingAs($this->datos['users']['docente_a'])
            ->from(route('investigacion-formativa.docente.grupos.show', [9901, $idGrupo]))
            ->post(route('investigacion-formativa.docente.decisiones.seleccionar', [9901, $idGrupo]), [
                'version' => $version,
            ]);
    }

    private function decisionesCount(string $tipo): int
    {
        return DB::table('investigacion_formativa.decision_trabajo')
            ->where('id_seccion', 9901)
            ->where('tipo', $tipo)
            ->count();
    }

    private function crearProyecto(int $idProyecto, int $idGrupo, string $titulo): void
    {
        DB::table('investigacion_formativa.proyecto')->insert([
            'id_proyecto' => $idProyecto,
            'id_grupo' => $idGrupo,
            'id_linea' => 9801,
            'id_tipo_producto' => 9981,
            'titulo' => $titulo,
            'estado' => 'Aceptado',
            'version' => 1,
        ]);
    }

    /**
     * Crea una entrega y una evaluación por unidad, con identificadores fijos
     * para poder inspeccionar la evidencia guardada.
     */
    private function crearUnidadesEvaluadas(int $idProyecto, int $idEntregaBase, int $idEvaluacionBase): void
    {
        foreach ([1, 2, 3] as $orden) {
            $idEntrega = $idEntregaBase + $orden - 1;
            $idEvaluacion = $idEvaluacionBase + $orden - 1;

            DB::table('investigacion_formativa.entrega')->insert([
                'id_entrega' => $idEntrega,
                'id_proyecto' => $idProyecto,
                'id_entregable' => $this->entregables[$orden],
                'version' => 1,
                'id_archivo' => $this->crearArchivo("proyecto-{$idProyecto}-unidad-{$orden}.pdf"),
            ]);

            $this->evaluarEntrega($idEntrega, $idEvaluacion);
        }
    }

    private function evaluarEntrega(int $idEntrega, int $idEvaluacion): void
    {
        DB::table('investigacion_formativa.evaluacion')->insert([
            'id_evaluacion' => $idEvaluacion,
            'id_entrega' => $idEntrega,
            'id_docente_evaluador' => $this->datos['id_docente_a'],
            'comentario' => 'Evaluación de prueba.',
            'version' => 1,
        ]);
    }

    private function reemplazarUnidad(int $idProyecto, int $orden, int $idEntrega, int $idEvaluacion): void
    {
        DB::table('investigacion_formativa.entrega')->insert([
            'id_entrega' => $idEntrega,
            'id_proyecto' => $idProyecto,
            'id_entregable' => $this->entregables[$orden],
            'version' => 2,
            'id_archivo' => $this->crearArchivo("proyecto-{$idProyecto}-unidad-{$orden}-v2.pdf"),
        ]);

        DB::table('investigacion_formativa.evaluacion')->insert([
            'id_evaluacion' => $idEvaluacion,
            'id_entrega' => $idEntrega,
            'id_docente_evaluador' => $this->datos['id_docente_a'],
            'comentario' => 'Reevaluación de la versión nueva.',
            'version' => 2,
        ]);
    }

    private function crearArchivo(string $nombre): int
    {
        $idModulo = DB::table('seguridad.modulo_sistema')
            ->where('codigo', 'investigacion_formativa')
            ->value('id_modulo');

        return DB::table('comun.archivo')->insertGetId([
            'id_modulo' => $idModulo,
            'nombre_original' => $nombre,
            'ruta_almacenamiento' => "investigacion-formativa/test/{$nombre}",
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 10,
            'hash_sha256' => hash('sha256', $nombre),
            'subido_por' => 8802,
        ], 'id_archivo');
    }
}
