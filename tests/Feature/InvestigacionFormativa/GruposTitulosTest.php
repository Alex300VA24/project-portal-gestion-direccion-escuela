<?php

namespace Tests\Feature\InvestigacionFormativa;

use App\Modules\InvestigacionFormativa\Models\Entrega;
use App\Modules\InvestigacionFormativa\Models\Proyecto;
use App\Modules\InvestigacionFormativa\Policies\EntregaPolicy;
use App\Modules\InvestigacionFormativa\Policies\ProyectoPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\InvestigacionFormativa\Concerns\CreaDatosInvestigacion;
use Tests\TestCase;

final class GruposTitulosTest extends TestCase
{
    use CreaDatosInvestigacion;
    use DatabaseTransactions;

    private array $datos;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL.');
        }

        $this->datos = $this->crearDatosInvestigacion();
    }

    public function test_la_creacion_de_grupo_guarda_miembros_proyecto_y_historial(): void
    {
        $this->configurarSeccionCompleta(9901);
        $this->crearTipoProducto();

        $this->actingAs($this->datos['users']['alumno_a'])
            ->post(route('investigacion-formativa.alumno.grupos.store', 9901), [
                'nombre' => 'Grupo de pruebas',
                'titulo' => 'Título de investigación',
                'integrante_ids' => [9901, 9902],
                'id_linea' => 9801,
                'id_tipo_producto' => 9981,
            ])
            ->assertRedirect(route('investigacion-formativa.alumno.grupos.show', 9901))
            ->assertSessionHasNoErrors();

        $grupo = DB::table('investigacion_formativa.grupo')
            ->where('id_seccion', 9901)
            ->where('nombre', 'Grupo de pruebas')
            ->first();

        $this->assertNotNull($grupo);
        $proyecto = DB::table('investigacion_formativa.proyecto')
            ->where('id_grupo', $grupo->id_grupo)
            ->first();

        $this->assertNotNull($proyecto);
        $this->assertSame('Título de investigación', $proyecto->titulo);
        $this->assertSame('Registrado', $proyecto->estado);
        $this->assertSame(1, (int) $proyecto->version);

        $miembros = DB::table('investigacion_formativa.grupo_estudiante')
            ->where('id_grupo', $grupo->id_grupo)
            ->orderBy('id_estudiante')
            ->get();

        $this->assertCount(2, $miembros);
        $this->assertSame(9901, (int) $miembros[0]->id_estudiante);
        $this->assertTrue((bool) $miembros[0]->es_coordinador);
        $this->assertSame(9902, (int) $miembros[1]->id_estudiante);
        $this->assertFalse((bool) $miembros[1]->es_coordinador);

        $this->assertDatabaseHas('investigacion_formativa.revision_titulo', [
            'id_proyecto' => $proyecto->id_proyecto,
            'titulo' => 'Título de investigación',
            'estado' => 'Registrado',
            'motivo' => null,
        ]);
    }

    public function test_la_creacion_de_grupo_rechaza_integrantes_fuera_del_padron(): void
    {
        $this->configurarSeccionCompleta(9901);
        $this->crearTipoProducto();

        $this->actingAs($this->datos['users']['alumno_a'])
            ->post(route('investigacion-formativa.alumno.grupos.store', 9901), [
                'nombre' => 'Grupo inválido',
                'titulo' => 'Título inválido',
                'integrante_ids' => [9901, 9903],
                'id_linea' => 9801,
                'id_tipo_producto' => 9981,
            ])
            ->assertRedirect(route('investigacion-formativa.alumno.grupos.create', 9901))
            ->assertSessionHasErrors(['grupo']);

        $this->assertDatabaseMissing('investigacion_formativa.grupo', [
            'id_seccion' => 9901,
            'nombre' => 'Grupo inválido',
        ]);
        $this->assertDatabaseMissing('investigacion_formativa.proyecto', [
            'titulo' => 'Título inválido',
        ]);
    }

    public function test_un_integrante_no_puede_formar_un_segundo_grupo(): void
    {
        $this->configurarSeccionCompleta(9901);
        $this->crearTipoProducto();
        $this->crearGrupoPorHttp([9901, 9902], 'Grupo único');

        $this->actingAs($this->datos['users']['alumno_b'])
            ->post(route('investigacion-formativa.alumno.grupos.store', 9901), [
                'nombre' => 'Segundo grupo',
                'titulo' => 'Segundo título',
                'integrante_ids' => [9901, 9902],
                'id_linea' => 9801,
                'id_tipo_producto' => 9981,
            ])
            ->assertRedirect(route('investigacion-formativa.alumno.grupos.create', 9901))
            ->assertSessionHasErrors(['grupo']);

        $this->assertSame(1, DB::table('investigacion_formativa.grupo')
            ->where('id_seccion', 9901)
            ->whereIn('nombre', ['Grupo único', 'Segundo grupo'])
            ->count());
    }

    public function test_una_seccion_incompleta_no_permite_crear_grupos(): void
    {
        $this->crearTipoProducto();

        $this->actingAs($this->datos['users']['alumno_a'])
            ->get(route('investigacion-formativa.alumno.grupos.create', 9901))
            ->assertRedirect(route('investigacion-formativa.alumno.cursos.index'))
            ->assertSessionHasErrors(['grupo']);
    }

    public function test_el_coordinador_puede_editar_y_el_docente_acepta_el_titulo(): void
    {
        $proyecto = $this->crearProyectoPropio();

        $this->actingAs($this->datos['users']['alumno_a'])
            ->patch(route('investigacion-formativa.alumno.titulos.update', 9901), [
                'titulo' => 'Título actualizado',
                'version' => 1,
            ])
            ->assertRedirect(route('investigacion-formativa.alumno.grupos.show', 9901))
            ->assertSessionHasNoErrors();

        $proyecto->refresh();

        $this->assertSame('Título actualizado', $proyecto->titulo);
        $this->assertSame('Registrado', $proyecto->estado);
        $this->assertSame(2, $proyecto->version);

        $this->actingAs($this->datos['users']['docente_a'])
            ->patch(route('investigacion-formativa.docente.titulos.update', [9901, 9951]), [
                'estado' => 'Aceptado',
                'motivo' => null,
                'version' => 2,
            ])
            ->assertRedirect(route('investigacion-formativa.docente.grupos.show', [9901, 9951]))
            ->assertSessionHasNoErrors();

        $proyecto->refresh();

        $this->assertSame('Aceptado', $proyecto->estado);
        $this->assertSame(3, $proyecto->version);
        $this->assertSame(3, DB::table('investigacion_formativa.revision_titulo')
            ->where('id_proyecto', $proyecto->id_proyecto)
            ->count());
    }

    public function test_un_integrante_no_coordinador_no_puede_editar_el_titulo(): void
    {
        $this->crearProyectoPropio();
        DB::table('investigacion_formativa.grupo_estudiante')->insert([
            'id_grupo' => 9951,
            'id_seccion' => 9901,
            'id_estudiante' => 9902,
            'es_coordinador' => false,
        ]);

        $this->actingAs($this->datos['users']['alumno_b'])
            ->patch(route('investigacion-formativa.alumno.titulos.update', 9901), [
                'titulo' => 'Título no autorizado',
                'version' => 1,
            ])
            ->assertForbidden();
    }

    public function test_el_docente_responsable_acepta_y_solicita_cambios(): void
    {
        $proyecto = $this->crearProyectoPropio();

        $this->actingAs($this->datos['users']['docente_a'])
            ->from(route('investigacion-formativa.docente.grupos.show', [9901, 9951]))
            ->patch(route('investigacion-formativa.docente.titulos.update', [9901, 9951]), [
                'estado' => 'Cambio solicitado',
                'motivo' => null,
                'version' => 1,
            ])
            ->assertRedirect(route('investigacion-formativa.docente.grupos.show', [9901, 9951]))
            ->assertSessionHasErrors(['motivo']);

        $this->assertSame('Registrado', $proyecto->fresh()->estado);

        $this->actingAs($this->datos['users']['docente_a'])
            ->patch(route('investigacion-formativa.docente.titulos.update', [9901, 9951]), [
                'estado' => 'Cambio solicitado',
                'motivo' => 'Delimita mejor el problema.',
                'version' => 1,
            ])
            ->assertRedirect(route('investigacion-formativa.docente.grupos.show', [9901, 9951]));

        $proyecto->refresh();

        $this->assertSame('Cambio solicitado', $proyecto->estado);
        $this->assertSame(2, $proyecto->version);
        $this->assertDatabaseHas('investigacion_formativa.revision_titulo', [
            'id_proyecto' => $proyecto->id_proyecto,
            'estado' => 'Cambio solicitado',
            'motivo' => 'Delimita mejor el problema.',
        ]);
    }

    public function test_el_docente_ajeno_y_el_grupo_de_otra_seccion_son_denegados(): void
    {
        $this->crearProyectoPropio();

        $this->actingAs($this->datos['users']['docente_b'])
            ->get(route('investigacion-formativa.docente.grupos.show', [9901, 9951]))
            ->assertForbidden();

        $this->actingAs($this->datos['users']['docente_a'])
            ->get(route('investigacion-formativa.docente.grupos.show', [9902, 9951]))
            ->assertNotFound();
    }

    public function test_una_version_desactualizada_del_titulo_devuelve_conflicto(): void
    {
        $this->crearProyectoPropio();

        $this->actingAs($this->datos['users']['alumno_a'])
            ->patch(route('investigacion-formativa.alumno.titulos.update', 9901), [
                'titulo' => 'Título con versión antigua',
                'version' => 99,
            ])
            ->assertStatus(409)
            ->assertSee('Recarga la página');
    }

    public function test_el_titulo_no_se_rea_bre_despues_de_una_entrega(): void
    {
        $proyecto = $this->crearProyectoPropio();
        $this->crearEntregaParaProyecto($proyecto);

        $this->actingAs($this->datos['users']['alumno_a'])
            ->patch(route('investigacion-formativa.alumno.titulos.update', 9901), [
                'titulo' => 'Título posterior a la entrega',
                'version' => 1,
            ])
            ->assertStatus(409);

        $this->assertSame('Título propio', $proyecto->fresh()->titulo);
    }

    public function test_las_politicas_de_proyecto_y_entrega_usan_el_grupo_exacto(): void
    {
        $this->crearGruposInvestigacion();
        $this->crearTipoProducto();
        DB::table('investigacion_formativa.proyecto')->insert([
            [
                'id_proyecto' => 12001,
                'id_grupo' => 9951,
                'id_linea' => 9801,
                'id_tipo_producto' => 9981,
                'titulo' => 'Proyecto propio',
                'estado' => 'Registrado',
                'version' => 1,
            ],
            [
                'id_proyecto' => 12002,
                'id_grupo' => 9954,
                'id_linea' => 9801,
                'id_tipo_producto' => 9981,
                'titulo' => 'Proyecto de otro grupo',
                'estado' => 'Registrado',
                'version' => 1,
            ],
        ]);

        $propio = Proyecto::query()->findOrFail(12001);
        $ajeno = Proyecto::query()->findOrFail(12002);
        $alumno = $this->datos['users']['alumno_a'];
        $proyectoPolicy = app(ProyectoPolicy::class);
        $entregaPolicy = app(EntregaPolicy::class);

        $this->assertTrue($proyectoPolicy->view($alumno, $propio));
        $this->assertFalse($proyectoPolicy->view($alumno, $ajeno));
        $this->assertFalse($proyectoPolicy->update($alumno, $ajeno));
        $this->assertFalse($entregaPolicy->create($alumno, $ajeno));
        $this->assertFalse($entregaPolicy->download(
            $alumno,
            new Entrega(['id_proyecto' => 12002]),
        ));

        $this->assertTrue($proyectoPolicy->view($this->datos['users']['docente_a'], $ajeno));
        $this->assertTrue($proyectoPolicy->view($this->datos['users']['director'], $ajeno));
    }

    public function test_el_estudiante_y_el_docente_pueden_leer_su_grupo(): void
    {
        $this->crearProyectoPropio();

        $this->actingAs($this->datos['users']['alumno_a'])
            ->get(route('investigacion-formativa.alumno.grupos.show', 9901))
            ->assertOk()
            ->assertSee('Grupo A')
            ->assertSee('Título propio');

        $this->actingAs($this->datos['users']['docente_a'])
            ->get(route('investigacion-formativa.docente.grupos.show', [9901, 9951]))
            ->assertOk()
            ->assertSee('Grupo A')
            ->assertSee('Título propio');
    }

    /**
     * @param  list<int>  $integrantes
     */
    private function crearGrupoPorHttp(array $integrantes, string $nombre): void
    {
        $this->actingAs($this->datos['users']['alumno_a'])
            ->post(route('investigacion-formativa.alumno.grupos.store', 9901), [
                'nombre' => $nombre,
                'titulo' => 'Título de integración',
                'integrante_ids' => $integrantes,
                'id_linea' => 9801,
                'id_tipo_producto' => 9981,
            ])
            ->assertRedirect(route('investigacion-formativa.alumno.grupos.show', 9901))
            ->assertSessionHasNoErrors();
    }

    private function crearProyectoPropio(): Proyecto
    {
        $this->crearGruposInvestigacion();
        $this->crearTipoProducto();
        $this->configurarSeccionCompleta(9901);
        DB::table('investigacion_formativa.proyecto')->insert([
            'id_proyecto' => 12001,
            'id_grupo' => 9951,
            'id_linea' => 9801,
            'id_tipo_producto' => 9981,
            'titulo' => 'Título propio',
            'estado' => 'Registrado',
            'version' => 1,
        ]);
        DB::table('investigacion_formativa.revision_titulo')->insert([
            'id_proyecto' => 12001,
            'titulo' => 'Título propio',
            'estado' => 'Registrado',
            'motivo' => null,
            'user_id' => $this->datos['users']['alumno_a']->id,
        ]);

        return Proyecto::query()->findOrFail(12001);
    }

    private function crearEntregaParaProyecto(Proyecto $proyecto): void
    {
        $idArchivo = DB::table('comun.archivo')->insertGetId([
            'id_modulo' => DB::table('seguridad.modulo_sistema')
                ->where('codigo', 'investigacion_formativa')
                ->value('id_modulo'),
            'nombre_original' => 'avance.pdf',
            'ruta_almacenamiento' => 'investigacion-formativa/test/titulo.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 10,
            'hash_sha256' => str_repeat('d', 64),
            'subido_por' => 8804,
        ], 'id_archivo');

        DB::table('investigacion_formativa.entrega')->insert([
            'id_proyecto' => $proyecto->id_proyecto,
            'id_entregable' => DB::table('investigacion_formativa.entregable')
                ->where('id_seccion', 9901)
                ->where('orden', 1)
                ->value('id_entregable'),
            'version' => 1,
            'id_archivo' => $idArchivo,
        ]);
    }
}
