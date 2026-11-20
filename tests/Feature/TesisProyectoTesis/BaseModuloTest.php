<?php

namespace Tests\Feature\TesisProyectoTesis;

use App\Models\User;
use App\Modules\TesisProyectoTesis\Database\Seeders\TesisDatabaseSeeder;
use App\Modules\TesisProyectoTesis\Models\CriterioRubrica;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\Rubrica;
use App\Modules\TesisProyectoTesis\Models\TipoSolicitud;
use App\Modules\TesisProyectoTesis\Services\ArchivoService;
use App\Modules\TesisProyectoTesis\Services\HistorialService;
use App\Modules\TesisProyectoTesis\Services\IdentidadService;
use App\Modules\TesisProyectoTesis\Services\NotificacionService;
use App\Modules\TesisProyectoTesis\Support\Estados;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class BaseModuloTest extends TesisTestCase
{
    public function test_migraciones_agregan_uuid_y_columnas_del_mer(): void
    {
        foreach (['proyecto_tesis', 'solicitud', 'resolucion', 'entrega', 'revision', 'observacion', 'sustentacion', 'acta', 'reporte_similitud'] as $tabla) {
            $this->assertTrue(Schema::hasColumn("tesis.$tabla", 'uuid'), "Falta uuid en tesis.$tabla");
        }

        $this->assertTrue(Schema::hasColumns('tesis.proyecto_tesis', ['enfoque', 'tipo_investigacion', 'diseno', 'lugar_ejecucion', 'duracion_meses', 'financiamiento', 'id_periodo_tesis']));
        $this->assertTrue(Schema::hasColumns('tesis.detalle_evaluacion', ['cumple', 'no_aplica']));
        $this->assertTrue(Schema::hasColumns('tesis.resultado_jurado', ['resultado', 'propone_mencion']));
        $this->assertTrue(Schema::hasColumns('tesis.acta', ['tipo', 'condicion', 'id_archivo_firmado']));
    }

    public function test_restricciones_del_esquema_rechazan_valores_invalidos(): void
    {
        $p = $this->proyecto();

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('tesis.proyecto_tesis')->where('id_proyecto', $p->id_proyecto)->update(['enfoque' => 'Inventado']);
    }

    public function test_rubrica_tiene_26_criterios_18_de_proyecto_y_25_de_tesis(): void
    {
        $rubrica = Rubrica::query()->where('estado', 'Vigente')->firstOrFail();
        $criterios = CriterioRubrica::query()->where('id_rubrica', $rubrica->id_rubrica)->get();

        $this->assertCount(26, $criterios);
        $this->assertCount(18, $criterios->whereIn('aplica_a', ['Ambas', 'Proyecto']));
        $this->assertCount(25, $criterios->whereIn('aplica_a', ['Ambas', 'Tesis']));
        $this->assertTrue($criterios->firstWhere('orden', 6)->permite_no_aplica);
        $this->assertSame(1, $criterios->where('permite_no_aplica', true)->count());
    }

    public function test_seeders_son_idempotentes(): void
    {
        $antes = [CriterioRubrica::count(), TipoSolicitud::count(), User::count(), DB::table('core.docente')->count(), DB::table('seguridad.usuario')->count()];

        $this->seed(TesisDatabaseSeeder::class);

        $this->assertSame($antes, [CriterioRubrica::count(), TipoSolicitud::count(), User::count(), DB::table('core.docente')->count(), DB::table('seguridad.usuario')->count()]);
        $this->assertSame(3, TipoSolicitud::count());
    }

    public function test_identidad_resuelve_estudiante_docente_y_usuario_sin_vinculo(): void
    {
        $identidad = app(IdentidadService::class);

        $alumno = $identidad->actual($this->user('alumno@portal.local'));
        $this->assertNotNull($alumno?->idEstudiante);
        $this->assertNull($alumno->idDocente);
        $this->assertSame('Lucía Ramírez Torres', $alumno->nombre);

        $asesor = $identidad->actual($this->user('asesor@portal.local'));
        $this->assertNotNull($asesor?->idDocente);

        $sinVinculo = User::factory()->create(['email' => 'nadie@portal.local']);
        $this->assertNull($identidad->actual($sinVinculo));
    }

    public function test_route_key_es_uuid_y_rechaza_ids_numericos(): void
    {
        $p = $this->proyecto();

        $this->assertSame('uuid', $p->getRouteKeyName());
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $p->getRouteKey());
        $this->assertNull((new Proyecto)->resolveRouteBinding((string) $p->id_proyecto));
        $this->assertTrue($p->is((new Proyecto)->resolveRouteBinding($p->uuid)));
    }

    public function test_policy_limita_acceso_por_rol_y_relacion(): void
    {
        $p = $this->proyecto(['alumno@portal.local'], Estados::EN_DESARROLLO, asesor: 'asesor@portal.local', jurados: $this->juradoDemo());
        $otro = $this->proyecto(['alumno2@portal.local']);

        $this->assertTrue($this->user('alumno@portal.local')->can('verComoAlumno', $p));
        $this->assertFalse($this->user('alumno2@portal.local')->can('verComoAlumno', $p));
        $this->assertTrue($this->user('asesor@portal.local')->can('verComoAsesor', $p));
        $this->assertFalse($this->user('asesor@portal.local')->can('verComoAsesor', $otro));
        $this->assertTrue($this->user('jurado1@portal.local')->can('verComoJurado', $p));
        $this->assertFalse($this->user('jurado1@portal.local')->can('verComoJurado', $otro));
        $this->assertFalse($this->user('jurado1@portal.local')->can('verComoAsesor', $p));
        $this->assertTrue($this->user('director@portal.local')->can('gestionarComoDirector', $p));
        $this->assertFalse($this->user('secretaria@portal.local')->can('gestionarComoDirector', $p));
        $this->assertTrue($this->user('secretaria@portal.local')->can('gestionarComoSecretaria', $p));
        $this->assertFalse($this->user('alumno2@portal.local')->can('ver', $p));
    }

    public function test_portada_sin_sesion_no_muestra_datos_y_lo_demas_exige_login(): void
    {
        $p = $this->proyecto();

        $this->get('/tesis-proyecto-tesis')->assertOk()->assertSee('Tesis y Proyecto de Tesis')->assertSee('Iniciar sesión')->assertDontSee($p->codigo);
        $this->get(route('tesis-proyecto-tesis.archivos.descargar', ['proyecto', $p->uuid]))->assertRedirect('/login');
        $this->post(route('tesis-proyecto-tesis.notificaciones.leer'))->assertRedirect('/login');
    }

    public function test_usuario_sin_vinculo_academico_ve_mensaje_claro(): void
    {
        $user = User::factory()->create(['email' => 'sinvinculo@portal.local', 'email_verified_at' => now()]);
        $user->assignRole('alumno');

        $this->actingAs($user)->get('/tesis-proyecto-tesis')
            ->assertOk()
            ->assertSee('no está vinculada a un usuario académico');
    }

    public function test_profesor_sin_asignaciones_ve_estado_vacio(): void
    {
        $this->como('profesor@portal.local')->get('/tesis-proyecto-tesis')
            ->assertOk()
            ->assertSee('Aún no tienes proyectos asignados');
    }

    public function test_descarga_de_archivo_exige_relacion_con_el_proyecto(): void
    {
        $p = $this->proyecto(['alumno@portal.local']);
        $idArchivo = app(ArchivoService::class)->guardar($this->pdf('plan_de_tesis_lucia.pdf'), $this->idUsuario('alumno@portal.local'));
        $p->update(['id_archivo_documento' => $idArchivo]);

        $ruta = route('tesis-proyecto-tesis.archivos.descargar', ['proyecto', $p->uuid]);

        $this->como('alumno@portal.local')->get($ruta)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->como('alumno2@portal.local')->get($ruta)->assertForbidden();
        $this->como('director@portal.local')->get($ruta)->assertOk();
        $this->como('alumno@portal.local')->get('/tesis-proyecto-tesis/archivos/otro/'.$p->uuid)->assertNotFound();
        $this->como('alumno@portal.local')->get('/tesis-proyecto-tesis/archivos/proyecto/'.$p->id_proyecto)->assertNotFound();

        $ruta = DB::table('comun.archivo')->where('id_archivo', $idArchivo)->value('ruta_almacenamiento');
        // Nombre generado (uuid), nunca el del estudiante. Antes se comprobaba que no contuviera «f1»,
        // pero un uuid hexadecimal lo contiene en ~11 % de los casos: la prueba fallaba al azar.
        $this->assertMatchesRegularExpression('#^tesis/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.pdf$#', $ruta);
        $this->assertStringNotContainsString('plan_de_tesis', $ruta);
        Storage::disk('local')->assertExists($ruta);
    }

    public function test_historial_y_notificaciones_registran_cambios(): void
    {
        $p = $this->proyecto();
        $idDirector = $this->idUsuario('director@portal.local');

        app(HistorialService::class)->cambiarEstado($p, Estados::CON_ASESOR, $idDirector, 'Prueba');
        app(NotificacionService::class)->notificar([$this->idUsuario('alumno@portal.local')], 'prueba', 'Título', 'Mensaje', $p);

        $this->assertDatabaseHas('tesis.historial_estado', ['id_proyecto' => $p->id_proyecto, 'estado_anterior' => Estados::REGISTRADO, 'estado_nuevo' => Estados::CON_ASESOR, 'cambiado_por' => $idDirector]);
        $this->assertSame(1, app(NotificacionService::class)->noLeidas($this->idUsuario('alumno@portal.local')));

        $this->como('alumno@portal.local')->from('/tesis-proyecto-tesis')->post(route('tesis-proyecto-tesis.notificaciones.leer'))->assertRedirect();
        $this->assertSame(0, app(NotificacionService::class)->noLeidas($this->idUsuario('alumno@portal.local')));
    }

    public function test_api_del_modulo_solo_expone_nombre_y_esquema(): void
    {
        $this->proyecto();

        $this->getJson('/api/tesis-proyecto-tesis')->assertOk()->assertExactJson(['module' => 'Tesis y Proyecto de Tesis', 'schema' => 'tesis']);
    }
}
