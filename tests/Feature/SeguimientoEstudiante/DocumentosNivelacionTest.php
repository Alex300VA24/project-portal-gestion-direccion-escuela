<?php

namespace Tests\Feature\SeguimientoEstudiante;

use App\Models\User;
use App\Modules\SeguimientoEstudiante\Services\Nivelacion\DocumentosNivelacionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\Support\CreatesPrematriculaAcademicData;
use Tests\TestCase;

final class DocumentosNivelacionTest extends TestCase
{
    use CreatesPrematriculaAcademicData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL con las migraciones modulares.');
        }

        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-19 12:00', 'America/Lima'));
        $this->createPrematriculaAcademicData();
    }

    public function test_personal_pdf_and_published_private_material_require_own_enrollment(): void
    {
        [$programa, $inscripcion] = $this->crearPrograma();
        $estudiante = $this->crearCuenta(1001);
        $docente = $this->crearCuenta(1002, 'nivelacion.documentos');
        $resumen = route('seguimiento-estudiante.nivelacion.resumen', $inscripcion);
        $this->get($resumen)->assertRedirect(route('login'));
        $this->actingAs($docente)->get($resumen)->assertNotFound();
        $this->actingAs($estudiante)->get($resumen)->assertNotFound();

        DB::table('seguimiento_estudiante.programa_nivelacion')->where('id_programa', $programa)
            ->update(['publicado_en' => '2026-11-15T08:00:00-05:00']);
        foreach (['Publicado' => '2026-11-18T09:00:00-05:00', 'Pendiente' => null] as $criterio => $publicacion) {
            $id = DB::table('seguimiento_estudiante.nivelacion_criterio_evaluacion')->insertGetId([
                'id_programa' => $programa, 'nombre' => $criterio, 'peso' => 50,
            ], 'id_criterio');
            DB::table('seguimiento_estudiante.nivelacion_calificacion')->insert([
                'id_participante' => $inscripcion, 'id_criterio' => $id, 'nota' => 18, 'publicada_en' => $publicacion,
            ]);
        }
        $proyeccion = app(DocumentosNivelacionService::class)->resumen(1001, $inscripcion);
        $this->assertSame(['Publicado'], $proyeccion['notas']->pluck('nombre')->all());
        $documento = $this->get($resumen)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('Content-Disposition', 'attachment; filename="nivelacion-personal.pdf"');
        $this->assertStringContainsString('no-store', $documento->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF-', $documento->getContent());

        $this->crearPersona(1003);
        $coordinador = $this->crearCuenta(1003, 'nivelacion.documentos', 'nivelacion.materiales.gestionar');
        DB::table('seguimiento_estudiante.coordinacion_ambito')->insert(['id_persona' => 1003, 'id_escuela' => 20, 'id_periodo' => 202602]);
        Storage::fake('local');
        $adjuntar = route('seguimiento-estudiante.nivelacion.adjuntar', $programa);
        $form = fn (string $titulo, int $visible) => [
            'titulo' => $titulo,
            'archivo' => UploadedFile::fake()->createWithContent('ejercicios.pdf', $documento->getContent()),
            'visible' => $visible,
        ];

        $this->actingAs($docente)->post($adjuntar, $form('Material ajeno', 1))->assertForbidden();
        $this->actingAs($coordinador)->post($adjuntar, $form('Material publicado', 1))->assertForbidden();
        DB::table('seguridad.usuario')->insert([
            'id_persona' => 1003, 'correo_institucional' => 'coordinacion@example.test', 'contrasena_hash' => 'prueba', 'estado' => 'Activo',
        ]);

        $this->post($adjuntar, $form('Material publicado', 1))->assertRedirect(route('seguimiento-estudiante.nivelacion.documentos'));
        $this->post($adjuntar, $form('Material en borrador', 0))->assertRedirect(route('seguimiento-estudiante.nivelacion.documentos'));

        $ruta = DB::table('comun.archivo')->orderBy('id_archivo')->first(['id_archivo', 'ruta_almacenamiento']);
        Storage::disk('local')->assertExists($ruta->ruta_almacenamiento);
        $material = route('seguimiento-estudiante.nivelacion.material', [$inscripcion, $ruta->id_archivo]);
        $borrador = DB::table('seguimiento_estudiante.nivelacion_material')->whereNull('visible_desde')->value('id_archivo');
        $this->actingAs($docente)->get($material)->assertNotFound();
        $this->actingAs($estudiante)->get($material)->assertDownload('material-nivelacion.pdf')->assertOk();
        $this->get(route('seguimiento-estudiante.nivelacion.material', [$inscripcion, $borrador]))->assertNotFound();
        $this->get(route('seguimiento-estudiante.nivelacion.ingreso'))->assertOk()
            ->assertSee('Descargar mi resumen en PDF')->assertSee('Material publicado')->assertDontSee('Material en borrador');

        $verBorrador = route('seguimiento-estudiante.nivelacion.material-gestion', [$programa, $borrador]);
        $publicar = route('seguimiento-estudiante.nivelacion.visibilidad-material', [$programa, $borrador]);
        $this->actingAs($docente)->get($verBorrador)->assertNotFound();
        $this->actingAs($coordinador)->get($verBorrador)->assertDownload('material-nivelacion.pdf');
        $this->post($publicar, ['visible' => 1])->assertRedirect(route('seguimiento-estudiante.nivelacion.documentos'));
        $descargar = route('seguimiento-estudiante.nivelacion.material', [$inscripcion, $borrador]);
        $this->actingAs($estudiante)->get($descargar)->assertDownload('material-nivelacion.pdf');
        $this->actingAs($coordinador)->post($publicar, ['visible' => 0])->assertRedirect(route('seguimiento-estudiante.nivelacion.documentos'));
        $this->actingAs($estudiante)->get($descargar)->assertNotFound();

    }

    public function test_staff_downloads_require_document_permission_scope_and_published_closure(): void
    {
        [$programa] = $this->crearPrograma();
        $this->crearPersona(1003);
        $estudiante = $this->crearCuenta(1001);
        $docente = $this->crearCuenta(1002, 'nivelacion.documentos');
        $coordinador = $this->crearCuenta(1003, 'nivelacion.documentos');
        $ficha = route('seguimiento-estudiante.nivelacion.gestion', [$programa, 'programa']);
        $informe = route('seguimiento-estudiante.nivelacion.gestion', [$programa, 'informe']);

        DB::table('seguimiento_estudiante.programa_nivelacion')->where('id_programa', $programa)
            ->update(['publicado_en' => '2026-11-15T08:00:00-05:00']);
        $this->actingAs($estudiante)->get($ficha)->assertForbidden();
        $this->actingAs($docente)->get($informe)->assertNotFound();
        $documento = $this->get($ficha)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $documento->getContent());
        $this->actingAs($coordinador)->get($ficha)->assertNotFound();
        DB::table('seguimiento_estudiante.coordinacion_ambito')->insert(['id_persona' => 1003, 'id_escuela' => 20, 'id_periodo' => 202602]);
        $this->get(route('seguimiento-estudiante.nivelacion.documentos'))->assertOk()->assertSee('Informe final pendiente');
        $this->get($informe)->assertConflict();
        DB::table('seguimiento_estudiante.programa_nivelacion')->where('id_programa', $programa)->update([
            'estado' => 'Cerrado',
            'cierre_publicado_en' => '2026-11-18T08:00:00-05:00',
            'documento_cierre' => json_encode(array_fill_keys([
                'contexto', 'metodologia', 'ejecucion', 'logros', 'analisis', 'conclusiones', 'recomendaciones', 'referencia',
            ], 'Evidencia sintética para la prueba.')),
        ]);
        $this->get(route('seguimiento-estudiante.nivelacion.documentos'))->assertOk()->assertSee('Descargar informe final PDF');
        $cierre = $this->get($informe)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $cierre->getContent());
    }

    private function crearPrograma(): array
    {
        DB::table('core.docente')->insert([
            'id_docente' => 101, 'id_persona' => 1002, 'id_departamento' => 30, 'codigo_docente' => 'D-101', 'estado' => 'Activo',
        ]);
        $dimension = DB::table('seguimiento_estudiante.dimension_brecha')
            ->insertGetId(['codigo' => 'MAT', 'nombre' => 'Matemática'], 'id_dimension');
        $programa = DB::table('seguimiento_estudiante.programa_nivelacion')->insertGetId([
            'id_periodo' => 202602, 'id_escuela' => 20, 'id_dimension' => $dimension, 'nombre' => 'Nivelación matemática',
            'fecha_inicio' => '2026-11-15', 'fecha_fin' => '2026-11-18', 'estado' => 'Publicado',
        ], 'id_programa');
        $grupo = DB::table('seguimiento_estudiante.nivelacion_grupo')->insertGetId([
            'id_programa' => $programa, 'id_docente' => 101, 'codigo' => 'N1',
        ], 'id_grupo');
        $inscripcion = DB::table('seguimiento_estudiante.nivelacion_participante')->insertGetId([
            'id_grupo' => $grupo, 'id_estudiante' => 1001,
        ], 'id_participante');

        return [$programa, $inscripcion];
    }

    private function crearCuenta(int $persona, string ...$permisos): User
    {
        $cuenta = User::factory()->create();
        DB::table('seguimiento_estudiante.identidad_cuenta')->insert(['id_usuario' => $cuenta->getKey(), 'id_persona' => $persona]);
        foreach (['view', ...$permisos] as $permiso) {
            $cuenta->givePermissionTo(Permission::findOrCreate('seguimiento-estudiante.'.$permiso, 'web'));
        }

        return $cuenta;
    }

    private function crearPersona(int $id): void
    {
        DB::table('core.persona')->insert([
            'id_persona' => $id, 'tipo_documento' => 'DNI', 'numero_documento' => (string) (70000000 + $id),
            'nombres' => 'Coordinación', 'apellido_paterno' => 'Prueba',
            'id_pais_nacionalidad' => 1, 'id_distrito_domicilio' => 1,
        ]);
    }
}
