<?php

namespace Tests\Feature\PreMatricula;

use App\Models\User;
use App\Modules\PreMatricula\Models\CronogramaPrematricula;
use App\Modules\PreMatricula\Models\HistorialAcademico;
use App\Modules\PreMatricula\Models\ImportacionHistorial;
use App\Modules\PreMatricula\Models\LimiteCreditosEstudiante;
use App\Modules\PreMatricula\Models\RevisionSolicitud;
use App\Modules\PreMatricula\Models\SolicitudPrematricula;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Shuchkin\SimpleXLSXGen;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesPrematriculaAcademicData;
use Tests\TestCase;

/**
 * Flujo completo del módulo por HTTP: alumno, coordinación, constancias, demanda e importación.
 */
final class FlujoPrematriculaTest extends TestCase
{
    use CreatesPrematriculaAcademicData;
    use DatabaseTransactions;

    private User $alumno;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL para probar esquemas y restricciones.');
        }

        $this->createPrematriculaAcademicData();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        // Como en master, solo un período en PREMATRICULA tiene ventanas activas.
        DB::table('core.periodo_academico')->where('id_periodo', 202602)->update(['estado' => 'PREMATRICULA']);

        DB::table('core.periodo_academico')->insert([
            'id_periodo' => 202601,
            'codigo' => '2026-I',
            'anio' => 2026,
            'tipo' => 'I',
            'fecha_inicio' => '2026-03-01',
            'fecha_fin' => '2026-07-20',
            'numero_semanas' => 17,
            'estado' => 'Cerrado',
        ]);
        DB::table('core.curso_prerequisito')->insert(['id_curso_plan' => 502, 'id_curso_plan_requisito' => 501]);
        DB::table('seguridad.usuario')->insert([
            'id_usuario' => 802,
            'id_persona' => 1001,
            'correo_institucional' => 'e2026001@unitru.edu.pe',
            'contrasena_hash' => '$2y$12$prueba.funcional.sin.uso.real',
            'debe_cambiar_contrasena' => false,
            'estado' => 'Activo',
        ]);

        $this->alumno = $this->usuario('e2026001@unitru.edu.pe', 'alumno');
        $this->director = $this->usuario('revisor@example.test', 'director_escuela');
    }

    public function test_los_invitados_ven_la_entrada_y_las_pantallas_piden_sesion(): void
    {
        $this->get('/pre-matricula')->assertOk()->assertSee('Pre Matrícula')->assertSee('prematricula');
        $this->get(route('pre-matricula.alumno.index'))->assertRedirect(route('login'));
        $this->get(route('pre-matricula.coordinacion.index'))->assertRedirect(route('login'));
    }

    public function test_la_entrada_redirige_segun_el_rol(): void
    {
        $this->actingAs($this->alumno)->get('/pre-matricula')->assertRedirect(route('pre-matricula.alumno.index'));
        $this->actingAs($this->director)->get('/pre-matricula')->assertRedirect(route('pre-matricula.coordinacion.index'));
    }

    public function test_cada_rol_solo_entra_a_sus_pantallas(): void
    {
        $this->actingAs($this->alumno)->get(route('pre-matricula.coordinacion.index'))->assertForbidden();
        $this->actingAs($this->director)->get(route('pre-matricula.alumno.index'))->assertForbidden();
        $this->actingAs($this->usuario('profesor@example.test', 'profesor'))->get(route('pre-matricula.coordinacion.demanda'))->assertForbidden();
    }

    public function test_un_alumno_sin_vinculo_ve_el_aviso(): void
    {
        $this->actingAs($this->usuario('sin.vinculo@unitru.edu.pe', 'alumno'))
            ->get(route('pre-matricula.alumno.index'))
            ->assertOk()
            ->assertSee('Tu cuenta no está vinculada a un estudiante');
    }

    public function test_sin_ventana_abierta_no_se_crea_el_borrador(): void
    {
        $this->actingAs($this->alumno)->get(route('pre-matricula.alumno.index'))->assertOk()->assertSee('No hay un período habilitado');
        $this->actingAs($this->alumno)->post(route('pre-matricula.alumno.agregar'), ['id_curso_plan' => 501])->assertSessionHas('error');
        $this->assertSame(0, SolicitudPrematricula::query()->count());
    }

    public function test_el_borrador_se_crea_al_ingresar_con_ventana_activa(): void
    {
        $this->abrirVentana();

        $this->actingAs($this->alumno)->get(route('pre-matricula.alumno.index'))->assertOk()->assertSee('Cursos disponibles');
        $this->actingAs($this->alumno)->get(route('pre-matricula.alumno.index'))->assertOk();

        $this->assertSame(1, SolicitudPrematricula::query()->count());
        $this->assertSame(SolicitudPrematricula::ESTADO_BORRADOR, SolicitudPrematricula::query()->sole()->estado);
    }

    public function test_el_alumno_registra_su_prematricula_y_descarga_la_constancia(): void
    {
        $this->abrirVentana();

        $this->actingAs($this->alumno)->get(route('pre-matricula.alumno.index'))->assertOk();
        $this->actingAs($this->alumno)->get(route('pre-matricula.alumno.index'))
            ->assertOk()->assertSee('Fundamentos de Programación')->assertDontSee('Programación Avanzada');

        $this->actingAs($this->alumno)->post(route('pre-matricula.alumno.agregar'), ['id_curso_plan' => 501])->assertSessionHas('status');
        $this->actingAs($this->alumno)->post(route('pre-matricula.alumno.agregar'), ['id_curso_plan' => 502])->assertSessionHas('error');
        $this->actingAs($this->alumno)->post(route('pre-matricula.alumno.registrar'))->assertSessionHas('status');

        $solicitud = SolicitudPrematricula::query()->sole();
        $this->assertSame(SolicitudPrematricula::ESTADO_ENVIADA, $solicitud->estado);
        $revision = RevisionSolicitud::query()->findOrFail($solicitud->id_revision_vigente);
        $this->assertSame(1, (int) $revision->numero_revision);
        $this->assertSame('E2026001', $revision->snapshot['estudiante']['codigo_universitario']);
        $this->assertSame([501], array_column($revision->snapshot['detalles'], 'id_curso_plan'));

        $pdf = $this->actingAs($this->alumno)->get(route('pre-matricula.constancia', $revision->id_revision));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->actingAs($this->director)->get(route('pre-matricula.constancia', $revision->id_revision))->assertOk();
        $this->actingAs($this->usuario('otro@unitru.edu.pe', 'alumno'))->get(route('pre-matricula.constancia', $revision->id_revision))->assertNotFound();

        $this->actingAs($this->alumno)->get(route('pre-matricula.alumno.historial'))->assertOk()->assertSee($revision->codigo_constancia);
    }

    public function test_la_correccion_solo_se_permite_en_ventana_extraordinaria(): void
    {
        $ordinaria = $this->abrirVentana();
        $this->registrarCurso(501);

        $this->actingAs($this->alumno)->post(route('pre-matricula.alumno.corregir'))->assertSessionHas('error');

        $ordinaria->forceFill(['fecha_fin' => now()->subMinutes(10)])->saveQuietly();
        $this->abrirVentana(CronogramaPrematricula::TIPO_EXTRAORDINARIA, now()->subMinutes(5));

        $this->actingAs($this->alumno)->post(route('pre-matricula.alumno.corregir'))->assertSessionHas('status');
        $solicitud = SolicitudPrematricula::query()->sole();
        $this->assertSame(SolicitudPrematricula::ESTADO_BORRADOR, $solicitud->estado);
        $this->assertNotNull($solicitud->id_revision_vigente, 'La constancia anterior sigue vigente durante la corrección.');

        $this->actingAs($this->alumno)->post(route('pre-matricula.alumno.registrar'))->assertSessionHas('status');
        $this->assertSame(2, RevisionSolicitud::query()->whereNotNull('numero_revision')->count());
    }

    public function test_un_prerrequisito_aprobado_habilita_el_curso_siguiente(): void
    {
        HistorialAcademico::query()->create(['id_estudiante' => 1001, 'id_curso_plan' => 501, 'id_periodo' => 202601, 'nota_final' => 15, 'estado' => HistorialAcademico::APROBADO]);
        $this->abrirVentana();
        $this->actingAs($this->alumno)->get(route('pre-matricula.alumno.index'));

        $this->actingAs($this->alumno)->get(route('pre-matricula.alumno.index'))
            ->assertOk()->assertSee('Programación Avanzada')->assertDontSee('Fundamentos de Programación');
    }

    public function test_no_se_supera_el_limite_de_creditos(): void
    {
        LimiteCreditosEstudiante::query()->create(['id_estudiante' => 1001, 'creditos_maximos' => 2]);
        $this->abrirVentana();
        $this->actingAs($this->alumno)->get(route('pre-matricula.alumno.index'));

        $this->actingAs($this->alumno)->post(route('pre-matricula.alumno.agregar'), ['id_curso_plan' => 501])->assertSessionHas('error');
        $this->assertSame(0, DB::table('prematricula.detalle_solicitud')->count());
    }

    public function test_la_coordinacion_crea_ventanas_respetando_las_reglas(): void
    {
        $datos = ['id_periodo' => 202602, 'tipo' => 'ORDINARIA', 'fecha_inicio' => '2026-12-01T08:00', 'fecha_fin' => '2026-12-05T18:00', 'motivo' => 'Ventana ordinaria 2026-II'];

        $this->actingAs($this->director)->post(route('pre-matricula.coordinacion.ventanas.store'), $datos)->assertSessionHasNoErrors();
        $ventana = CronogramaPrematricula::query()->sole();
        $this->assertSame(801, (int) $ventana->creado_por);
        $this->assertSame('2026-12-01 13:00', $ventana->fecha_inicio->utc()->format('Y-m-d H:i'), 'La hora de Lima se guarda en UTC.');

        $this->actingAs($this->director)->post(route('pre-matricula.coordinacion.ventanas.store'), $datos)->assertSessionHasErrors('tipo');
        $this->actingAs($this->director)->post(route('pre-matricula.coordinacion.ventanas.store'), [...$datos, 'tipo' => 'EXTRAORDINARIA', 'fecha_inicio' => '2026-12-04T08:00', 'fecha_fin' => '2026-12-08T08:00'])->assertSessionHasErrors('fecha_inicio');
        $this->actingAs($this->director)->post(route('pre-matricula.coordinacion.ventanas.store'), [...$datos, 'tipo' => 'EXTRAORDINARIA', 'fecha_inicio' => '2026-12-06T08:00', 'fecha_fin' => '2026-12-08T08:00'])->assertSessionHasNoErrors();

        $this->actingAs($this->director)->get(route('pre-matricula.coordinacion.ventanas', ['periodo' => 202602]))->assertOk()->assertSee('Ventana ordinaria 2026-II');
    }

    public function test_la_coordinacion_consulta_registros_demanda_y_estudiantes(): void
    {
        $this->abrirVentana();
        $this->registrarCurso(501);
        $solicitud = SolicitudPrematricula::query()->sole();

        $this->actingAs($this->director)->get(route('pre-matricula.coordinacion.index'))->assertOk()->assertSee('Fundamentos de Programación');
        $this->actingAs($this->director)->get(route('pre-matricula.coordinacion.registros', ['estado' => 'ENVIADA']))->assertOk()->assertSee('E2026001');
        $this->actingAs($this->director)->get(route('pre-matricula.coordinacion.registros.show', $solicitud->id_solicitud))->assertOk()->assertSee('CP-501');
        $this->actingAs($this->director)->get(route('pre-matricula.coordinacion.demanda'))->assertOk()->assertSee('CP-501');
        $this->actingAs($this->director)->get(route('pre-matricula.coordinacion.demanda.interesados', [202602, 501]))->assertOk()->assertSee('E2026001');
        $this->actingAs($this->director)->get(route('pre-matricula.coordinacion.demanda.interesados', [202602, 502]))->assertNotFound();

        $csv = $this->actingAs($this->director)->get(route('pre-matricula.coordinacion.demanda.exportar', 202602));
        $csv->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('CP-501', $csv->getContent());

        $this->actingAs($this->director)->get(route('pre-matricula.coordinacion.estudiantes', ['buscar' => 'E2026001']))->assertOk()->assertSee('Ver situación');
        $this->actingAs($this->director)->get(route('pre-matricula.coordinacion.estudiantes.show', 'E2026001'))->assertOk()->assertSee('Historial académico');
    }

    public function test_la_importacion_de_resultados_valida_antes_de_guardar(): void
    {
        Storage::fake('local');
        $this->abrirVentana();
        $this->actingAs($this->alumno)->get(route('pre-matricula.alumno.index'));

        $this->actingAs($this->director)->get(route('pre-matricula.coordinacion.resultados.plantilla'))->assertOk();

        $conError = $this->xlsx([['E2026001', 'CP-501', '2026-I', 25, 'APROBADO']]);
        $this->actingAs($this->director)->post(route('pre-matricula.coordinacion.resultados.validar'), ['archivo' => $conError, 'motivo' => 'Actas 2026-I'])
            ->assertSessionHas('reporte', fn (array $r): bool => $r['valido'] === false && $r['total_errores'] === 1);

        $valido = $this->xlsx([['E2026001', 'CP-501', '2026-I', 16, 'APROBADO']]);
        $this->actingAs($this->director)->post(route('pre-matricula.coordinacion.resultados.validar'), ['archivo' => $valido, 'motivo' => 'Actas 2026-I']);
        $reporte = session('reporte');
        $this->assertTrue($reporte['valido']);
        $this->assertSame(0, HistorialAcademico::query()->count(), 'Validar no guarda nada.');

        $this->actingAs($this->director)->post(route('pre-matricula.coordinacion.resultados.confirmar'), [
            'sha256' => $reporte['sha256'], 'huella' => $reporte['huella'], 'motivo' => 'Actas 2026-I',
        ])->assertSessionHas('status');

        $this->assertSame(HistorialAcademico::APROBADO, HistorialAcademico::query()->sole()->estado);
        $this->assertSame(801, (int) ImportacionHistorial::query()->sole()->creado_por);
        $this->actingAs($this->director)->get(route('pre-matricula.coordinacion.resultados'))->assertOk()->assertSee('Actas 2026-I');

        $this->actingAs($this->alumno)->get(route('pre-matricula.alumno.index'))->assertSee('Programación Avanzada');
    }

    public function test_la_api_del_modulo_se_mantiene(): void
    {
        $this->getJson('/api/pre-matricula')->assertExactJson(['module' => 'Pre Matrícula', 'schema' => 'prematricula']);
    }

    private function usuario(string $correo, string $rol): User
    {
        $user = User::factory()->create(['email' => $correo, 'email_verified_at' => now()]);
        $user->assignRole(Role::findOrCreate($rol, 'web'));

        return $user;
    }

    private function abrirVentana(string $tipo = CronogramaPrematricula::TIPO_ORDINARIA, $inicio = null): CronogramaPrematricula
    {
        return CronogramaPrematricula::query()->create([
            'id_periodo' => 202602,
            'tipo' => $tipo,
            'fecha_inicio' => $inicio ?? now()->subHour(),
            'fecha_fin' => now()->addDay(),
            'estado' => 'PROGRAMADA',
            'motivo' => 'Ventana de prueba',
            'creado_por' => 801,
        ]);
    }

    private function registrarCurso(int $idCursoPlan): void
    {
        $this->actingAs($this->alumno)->get(route('pre-matricula.alumno.index'));
        $this->actingAs($this->alumno)->post(route('pre-matricula.alumno.agregar'), ['id_curso_plan' => $idCursoPlan]);
        $this->actingAs($this->alumno)->post(route('pre-matricula.alumno.registrar'))->assertSessionHas('status');
    }

    /** @param  list<list<mixed>>  $filas */
    private function xlsx(array $filas): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'res').'.xlsx';
        SimpleXLSXGen::fromArray([['codigo_universitario', 'codigo_curso', 'codigo_periodo', 'nota_final', 'estado'], ...$filas])->saveAs($ruta);

        return new UploadedFile($ruta, 'resultados.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
