<?php

namespace Tests\Feature\SeguimientoEstudiante;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CreatesPrematriculaAcademicData;
use Tests\TestCase;

final class AccesoTest extends TestCase
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

    public function test_account_requires_both_permission_and_academic_identity(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get(route('seguimiento-estudiante.index'))->assertForbidden();
        $this->getJson(route('api.seguimiento-estudiante.index'))->assertForbidden();

        $this->grantPermission($user, 'seguimiento-estudiante.view');
        $this->get(route('seguimiento-estudiante.index'))
            ->assertForbidden()
            ->assertSee('identidad académica vinculada');
        $this->getJson(route('api.seguimiento-estudiante.index'))->assertForbidden();
    }

    public function test_student_enters_with_the_portal_login_and_can_only_open_their_own_profile(): void
    {
        $user = User::factory()->create();
        $this->link($user, 1001);
        $this->grantPermission($user, 'seguimiento-estudiante.view');
        $this->createOtherStudent();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->get(route('seguimiento-estudiante.index'))
            ->assertOk()
            ->assertSee('E2026001')
            ->assertDontSee('E2026002');
        $this->get(route('seguimiento-estudiante.expediente', 1001))->assertOk()->assertSee('E2026001');
        $this->get(route('seguimiento-estudiante.expediente', 1002))->assertNotFound();
        $this->getJson(route('api.seguimiento-estudiante.index'))->assertOk()->assertJsonPath('schema', 'seguimiento_estudiante');
    }

    public function test_tutor_requires_a_permission_and_an_active_assignment_in_the_current_period(): void
    {
        $user = User::factory()->create();
        $this->link($user, 1002);
        $this->grantPermission($user, 'seguimiento-estudiante.view');
        DB::table('core.docente')->insert([
            'id_docente' => 101,
            'id_persona' => 1002,
            'id_departamento' => 30,
            'codigo_docente' => 'D-101',
            'estado' => 'Activo',
        ]);
        DB::table('seguimiento_estudiante.tutor_asignacion')->insert([
            'id_estudiante' => 1001,
            'id_docente' => 101,
            'id_periodo' => 202602,
            'fecha_asignacion' => '2026-11-01',
        ]);

        $this->actingAs($user);
        $url = route('seguimiento-estudiante.expediente', 1001);
        $this->get($url)->assertNotFound();

        $this->grantPermission($user, 'seguimiento-estudiante.tutoria.view');
        $this->get(route('seguimiento-estudiante.index'))->assertOk()->assertSee('E2026001');
        $this->get($url)->assertOk();

        DB::table('seguimiento_estudiante.tutor_asignacion')->where('id_docente', 101)->update(['fecha_fin' => '2026-11-18']);
        $this->get($url)->assertNotFound();
        DB::table('seguimiento_estudiante.tutor_asignacion')->where('id_docente', 101)->update(['fecha_fin' => null]);
        $this->travelTo(Carbon::parse('2026-12-21 12:00', 'America/Lima'));
        $this->get($url)->assertNotFound();
    }

    public function test_student_can_submit_tutoring_requests_once_per_form_and_only_for_their_active_profile(): void
    {
        $user = User::factory()->create();
        $this->link($user, 1001);
        $this->grantPermission($user, 'seguimiento-estudiante.view');
        $this->createOtherStudent();
        $this->actingAs($user);
        $expediente = route('seguimiento-estudiante.expediente', 1001);
        $post = route('seguimiento-estudiante.solicitudes.tutoria.store', 1001);
        $form = [
            'id_periodo' => 202602,
            'clave_envio' => (string) Str::uuid(),
            'motivo' => 'Necesito orientación para organizar mis estudios.',
        ];

        $this->get($expediente)->assertDontSee('Enviar solicitud');
        $this->post($post, $form)->assertForbidden();
        $this->grantPermission($user, 'seguimiento-estudiante.solicitud.create');
        $this->get($expediente)->assertOk()->assertSee('Enviar solicitud');
        $this->post(route('seguimiento-estudiante.solicitudes.tutoria.store', 1002), $form)->assertNotFound();
        $this->from($expediente)->post($post, array_replace($form, ['id_periodo' => 999]))
            ->assertRedirect($expediente)->assertSessionHasErrors('id_periodo');
        $this->from($expediente)->post($post, array_replace($form, ['motivo' => 'a']))
            ->assertRedirect($expediente)->assertSessionHasErrors('motivo');

        $this->post($post, $form + ['id_estudiante' => 1002, 'estado' => 'Atendida'])
            ->assertRedirect($expediente)->assertSessionHas('status', 'Solicitud de tutoría registrada.');
        $this->post($post, $form)->assertRedirect($expediente);
        $this->assertDatabaseHas('seguimiento_estudiante.solicitud_estudiante', [
            'id_estudiante' => 1001,
            'id_periodo' => 202602,
            'tipo' => 'Tutoria',
            'estado' => 'Pendiente',
            'descripcion' => $form['motivo'],
        ]);
        $this->from($expediente)->post($post, array_replace($form, ['motivo' => 'Un motivo diferente']))
            ->assertRedirect($expediente)->assertSessionHasErrors('motivo');
        $this->assertDatabaseCount('seguimiento_estudiante.solicitud_estudiante', 1);
        $this->get($expediente)->assertOk()->assertSee($form['motivo'])->assertSee('Pendiente');
    }

    public function test_tutor_sees_requests_only_in_the_period_of_the_assignment(): void
    {
        $user = User::factory()->create();
        $this->link($user, 1002);
        $this->grantPermission($user, 'seguimiento-estudiante.view');
        $this->grantPermission($user, 'seguimiento-estudiante.tutoria.view');
        DB::table('core.docente')->insert([
            'id_docente' => 101,
            'id_persona' => 1002,
            'id_departamento' => 30,
            'codigo_docente' => 'D-101',
            'estado' => 'Activo',
        ]);
        DB::table('seguimiento_estudiante.tutor_asignacion')->insert([
            'id_estudiante' => 1001,
            'id_docente' => 101,
            'id_periodo' => 202602,
            'fecha_asignacion' => '2026-11-01',
        ]);
        DB::table('core.periodo_academico')->insert([
            'id_periodo' => 202601,
            'codigo' => '2026-I',
            'anio' => 2026,
            'tipo' => 'I',
            'fecha_inicio' => '2026-03-01',
            'fecha_fin' => '2026-11-30',
            'numero_semanas' => 39,
            'estado' => 'Activo',
        ]);
        foreach ([202602 => 'Solicitud del período asignado', 202601 => 'Solicitud de otro período'] as $periodo => $motivo) {
            DB::table('seguimiento_estudiante.solicitud_estudiante')->insert([
                'id_estudiante' => 1001,
                'id_periodo' => $periodo,
                'tipo' => 'Tutoria',
                'asunto' => 'Solicitud de tutoría',
                'descripcion' => $motivo,
            ]);
        }

        $this->actingAs($user);
        $expediente = route('seguimiento-estudiante.expediente', 1001);
        $this->get($expediente)->assertOk()
            ->assertSee('Solicitud del período asignado')
            ->assertDontSee('Solicitud de otro período')
            ->assertDontSee('Enviar solicitud')
            ->assertDontSee('Alertas registradas')
            ->assertDontSee('Situación académica registrada');

        foreach ([202602 => 'ACTUAL', 202601 => 'EXTERNO'] as $periodo => $referencia) {
            $parametro = DB::table('seguimiento_estudiante.parametro_sat')->insertGetId([
                'codigo' => 'ALERTA_'.$referencia, 'operador' => '<', 'umbral' => 10, 'nivel_riesgo' => 'Alto',
            ], 'id_parametro');
            DB::table('seguimiento_estudiante.alerta_sat')->insert([
                'id_estudiante' => 1001, 'id_periodo' => $periodo, 'id_parametro' => $parametro,
            ]);
            DB::table('seguimiento_estudiante.situacion_academica')->insert([
                'id_estudiante' => 1001, 'id_periodo' => $periodo, 'condicion' => 'CONDICION_'.$referencia,
            ]);
        }
        $this->grantPermission($user, 'seguimiento-estudiante.alerta.view');
        $this->grantPermission($user, 'seguimiento-estudiante.continuidad.view');
        $this->get($expediente)->assertOk()->assertSee('ALERTA_ACTUAL')->assertSee('CONDICION_ACTUAL')
            ->assertDontSee('ALERTA_EXTERNO')->assertDontSee('CONDICION_EXTERNO');
    }

    public function test_tutor_programs_one_appointment_with_history_and_cannot_book_an_occupied_slot(): void
    {
        $tutor = User::factory()->create();
        $this->link($tutor, 1002);
        $this->grantPermission($tutor, 'seguimiento-estudiante.view');
        $this->grantPermission($tutor, 'seguimiento-estudiante.tutoria.view');
        DB::table('core.docente')->insert([
            'id_docente' => 101,
            'id_persona' => 1002,
            'id_departamento' => 30,
            'codigo_docente' => 'D-101',
            'estado' => 'Activo',
        ]);
        $asignacion = DB::table('seguimiento_estudiante.tutor_asignacion')->insertGetId([
            'id_estudiante' => 1001,
            'id_docente' => 101,
            'id_periodo' => 202602,
            'fecha_asignacion' => '2026-11-01',
        ], 'id_asignacion');

        $tabla = 'seguimiento_estudiante.solicitud_estudiante';
        $solicitud = [
            'id_estudiante' => 1001,
            'id_periodo' => 202602,
            'tipo' => 'Tutoria',
            'asunto' => 'Solicitud de tutoría',
        ];
        $primera = DB::table($tabla)->insertGetId($solicitud, 'id_solicitud');
        $segunda = DB::table($tabla)->insertGetId($solicitud, 'id_solicitud');

        $expediente = route('seguimiento-estudiante.expediente', 1001);
        $programar = route('seguimiento-estudiante.solicitudes.tutoria.programar', [1001, $primera]);
        $form = [
            'fecha_hora' => '2026-11-20T14:00',
            'duracion_minutos' => 30,
            'modalidad' => 'VIRTUAL',
            'lugar' => 'https://example.test/cita',
            'nota' => 'Horario acordado con el estudiante.',
        ];

        $this->actingAs($tutor)->get($expediente)->assertOk()->assertDontSee('Programar cita');
        $this->postJson($programar, $form)->assertForbidden();
        $this->grantPermission($tutor, 'seguimiento-estudiante.tutoria.programar');
        $this->get($expediente)->assertOk()->assertSee('Programar cita');
        DB::table('seguimiento_estudiante.tutor_asignacion')->where('id_asignacion', $asignacion)
            ->update(['fecha_fin' => '2026-11-18']);
        $this->postJson($programar, $form)->assertNotFound();
        DB::table('seguimiento_estudiante.tutor_asignacion')->where('id_asignacion', $asignacion)
            ->update(['fecha_fin' => null]);

        $this->postJson($programar, $form)->assertUnprocessable()->assertJsonValidationErrors('fecha_hora');
        $this->assertDatabaseCount('seguimiento_estudiante.atencion', 0);
        DB::table('seguimiento_estudiante.tipo_atencion')->insert(['nombre' => 'Tutoría']);
        $this->postJson($programar, array_replace($form, ['fecha_hora' => '2026-11-18T09:00']))
            ->assertUnprocessable()->assertJsonValidationErrors('fecha_hora');
        $this->postJson($programar, array_replace($form, ['duracion_minutos' => 9]))
            ->assertUnprocessable()->assertJsonValidationErrors('duracion_minutos');
        $this->post($programar, $form)->assertRedirect($expediente)->assertSessionHas('status', 'Cita de tutoría programada.');
        $this->postJson($programar, $form)->assertUnprocessable()->assertJsonValidationErrors('fecha_hora');

        $this->postJson(route('seguimiento-estudiante.solicitudes.tutoria.programar', [1001, $segunda]),
            array_replace($form, ['fecha_hora' => '2026-11-20T14:15']))
            ->assertUnprocessable()->assertJsonValidationErrors('fecha_hora');
        $this->assertDatabaseHas($tabla, ['id_solicitud' => $primera, 'estado' => 'Programada']);
        $this->assertDatabaseHas($tabla, ['id_solicitud' => $segunda, 'estado' => 'Pendiente']);
        $this->assertDatabaseCount('seguimiento_estudiante.atencion', 1);
        $this->assertDatabaseHas('seguimiento_estudiante.atencion', [
            'id_asignacion' => $asignacion,
            'id_solicitud' => $primera,
            'fecha_hora' => '2026-11-20 19:00:00+00',
            'duracion_minutos' => 30,
            'modalidad' => 'VIRTUAL',
            'lugar' => $form['lugar'],
        ]);
        $this->assertDatabaseCount('seguimiento_estudiante.atencion_evento', 1);
        $this->assertDatabaseHas('seguimiento_estudiante.atencion_evento', [
            'id_atencion' => DB::table('seguimiento_estudiante.atencion')->value('id_atencion'),
            'id_usuario' => $tutor->getKey(),
            'tipo' => 'programacion',
            'motivo' => $form['nota'],
            'duracion_minutos' => 30,
        ]);

        $this->postJson(route('seguimiento-estudiante.solicitudes.tutoria.programar', [1001, $segunda]),
            array_replace($form, ['fecha_hora' => '2026-11-20T14:30']))
            ->assertRedirect($expediente);
        $this->assertDatabaseCount('seguimiento_estudiante.atencion', 2);
        $this->assertDatabaseCount('seguimiento_estudiante.atencion_evento', 2);

        $estudiante = User::factory()->create();
        $this->link($estudiante, 1001);
        $this->grantPermission($estudiante, 'seguimiento-estudiante.view');
        $this->actingAs($estudiante)->get($expediente)->assertOk()
            ->assertSee('20/11/2026 14:00')
            ->assertSee($form['lugar'])
            ->assertDontSee($form['nota'])
            ->assertDontSee('Programar cita');
    }

    public function test_tutor_preserves_appointment_and_session_history_when_updating_a_request(): void
    {
        $tutor = User::factory()->create();
        $this->link($tutor, 1002);
        foreach (['view', 'tutoria.view', 'tutoria.programar'] as $permission) {
            $this->grantPermission($tutor, 'seguimiento-estudiante.'.$permission);
        }
        DB::table('core.docente')->insert([
            'id_docente' => 101,
            'id_persona' => 1002,
            'id_departamento' => 30,
            'codigo_docente' => 'D-101',
            'estado' => 'Activo',
        ]);
        DB::table('seguimiento_estudiante.tutor_asignacion')->insert([
            'id_estudiante' => 1001,
            'id_docente' => 101,
            'id_periodo' => 202602,
            'fecha_asignacion' => '2026-11-01',
        ]);
        DB::table('seguimiento_estudiante.tipo_atencion')->insert(['nombre' => 'Tutoría']);
        $solicitud = [
            'id_estudiante' => 1001,
            'id_periodo' => 202602,
            'tipo' => 'Tutoria',
            'asunto' => 'Solicitud de tutoría',
        ];
        $tabla = 'seguimiento_estudiante.solicitud_estudiante';
        $id = DB::table($tabla)->insertGetId($solicitud, 'id_solicitud');
        $otra = DB::table($tabla)->insertGetId($solicitud, 'id_solicitud');
        $resolver = fn (string $accion, int $solicitudId) => route(
            'seguimiento-estudiante.solicitudes.tutoria.resolver', [1001, $solicitudId, $accion],
        );
        $expediente = route('seguimiento-estudiante.expediente', 1001);
        $cita = [
            'fecha_hora' => '2026-11-20T14:00',
            'duracion_minutos' => 30,
            'modalidad' => 'PRESENCIAL',
            'lugar' => 'Aula 23',
            'nota' => 'Horario acordado con el estudiante.',
        ];

        $this->actingAs($tutor)->post(route('seguimiento-estudiante.solicitudes.tutoria.programar', [1001, $id]), $cita)
            ->assertRedirect($expediente);
        $reprogramar = array_replace($cita, ['fecha_hora' => '2026-11-20T14:15', 'lugar' => 'Aula 24']);
        $this->postJson($resolver('reprogramar', $id), $reprogramar + ['version' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->post($resolver('reprogramar', $id), $reprogramar + ['version' => 2])->assertRedirect($expediente);
        $this->assertDatabaseHas('seguimiento_estudiante.atencion_evento', [
            'tipo' => 'programacion', 'lugar' => 'Aula 23',
        ]);
        $this->assertDatabaseHas('seguimiento_estudiante.atencion_evento', [
            'tipo' => 'reprogramacion', 'lugar' => 'Aula 24',
        ]);

        $this->post(route('seguimiento-estudiante.solicitudes.tutoria.programar', [1001, $otra]),
            array_replace($cita, ['fecha_hora' => '2026-11-20T15:00']))->assertRedirect($expediente);
        $estudiante = User::factory()->create();
        $this->link($estudiante, 1001);
        $this->grantPermission($estudiante, 'seguimiento-estudiante.view');
        $this->grantPermission($estudiante, 'seguimiento-estudiante.solicitud.create');
        $cambiar = route('seguimiento-estudiante.solicitudes.tutoria.cambio', [1001, $otra]);
        $peticion = ['tipo_cambio' => 'CANCELACION', 'motivo_cambio' => 'No podré asistir a la cita.'];
        $this->actingAs($estudiante)->get($expediente)->assertOk()->assertSee('Solicitar cambio de cita');
        $this->post($cambiar, $peticion + ['version' => 2])->assertRedirect($expediente);
        $this->postJson($cambiar, $peticion + ['version' => 3])
            ->assertUnprocessable()->assertJsonValidationErrors('tipo_cambio');
        $this->actingAs($tutor)->get($expediente)->assertOk()
            ->assertSee('Pendiente de revisión')->assertSee('Mantener la solicitud como está');
        $this->post($resolver('mantener', $otra), ['version' => 3, 'nota' => 'Por favor confirma el nuevo motivo.'])
            ->assertRedirect($expediente);
        $this->actingAs($estudiante)->get($expediente)->assertOk()->assertSee('Rechazada');
        $this->post($cambiar, $peticion + ['version' => 4])->assertRedirect($expediente);
        $this->actingAs($tutor)->post($resolver('cancelar', $otra), ['version' => 5, 'nota' => 'Se canceló por acuerdo de ambas partes.'])
            ->assertRedirect($expediente);
        $this->assertDatabaseHas('seguimiento_estudiante.solicitud_cambio_cita', [
            'id_solicitud' => $otra, 'decision' => 'Aprobada', 'respondido_por' => $tutor->getKey(),
        ]);
        $this->assertDatabaseHas('seguimiento_estudiante.solicitud_cambio_cita', [
            'id_solicitud' => $otra, 'decision' => 'Rechazada', 'solicitado_por' => $estudiante->getKey(),
        ]);
        $this->assertDatabaseHas('seguimiento_estudiante.atencion_evento', ['tipo' => 'cancelacion']);
        $this->assertDatabaseHas($tabla, ['id_solicitud' => $otra, 'estado' => 'Cancelada']);

        $sesion = [
            'asistencia' => 'ASISTIO',
            'actividad' => 'Revisión de técnicas de estudio.',
            'acuerdos' => 'Preparar un horario semanal.',
            'observaciones' => 'Observación reservada de la sesión.',
        ];
        $this->postJson($resolver('registrar', $id), $sesion + ['version' => 3])
            ->assertUnprocessable()->assertJsonValidationErrors('asistencia');
        $this->travelTo(Carbon::parse('2026-11-20 14:45', 'America/Lima'));
        $this->get($expediente)->assertOk()->assertSee('Registrar atención')->assertSee('Aula 24');
        $this->post($resolver('registrar', $id), $sesion + ['version' => 3])->assertRedirect($expediente);
        $atencion = DB::table('seguimiento_estudiante.atencion')->where('id_solicitud', $id)->value('id_atencion');
        $this->assertDatabaseHas('seguimiento_estudiante.atencion_compromiso', [
            'id_atencion' => $atencion, 'descripcion' => $sesion['acuerdos'], 'origen_sesion' => true,
        ]);
        $this->get($expediente)->assertOk()->assertSee('Corregir atención')->assertSee($sesion['observaciones']);

        $this->post($resolver('corregir', $id), [
            'version' => 4, 'asistencia' => 'NO_ASISTIO', 'motivo_correccion' => 'Se corrigió el acta de asistencia.',
        ])->assertRedirect($expediente);
        $this->assertDatabaseHas($tabla, ['id_solicitud' => $id, 'estado' => 'Inasistencia', 'version' => 5]);
        $this->assertDatabaseHas('seguimiento_estudiante.atencion_sesion_revision', [
            'id_atencion' => $atencion, 'version' => 4, 'estado' => 'Atendida', 'acuerdos' => $sesion['acuerdos'],
        ]);
        $this->assertDatabaseHas('seguimiento_estudiante.atencion_sesion_revision', [
            'id_atencion' => $atencion, 'version' => 5, 'estado' => 'Inasistencia',
        ]);
        $this->assertDatabaseHas('seguimiento_estudiante.atencion_participante', [
            'id_atencion' => $atencion, 'id_persona' => 1001, 'papel' => 'Estudiante', 'asistio' => false,
        ]);
        $this->assertDatabaseMissing('seguimiento_estudiante.atencion_compromiso', ['id_atencion' => $atencion]);

        $this->actingAs($estudiante)->get($expediente)->assertOk()
            ->assertSee('Inasistencia')->assertSee('Aprobada')->assertDontSee($sesion['observaciones'])
            ->assertDontSee('Se canceló por acuerdo de ambas partes.')->assertDontSee('Corregir atención');
        $this->postJson($resolver('cancelar', $id), ['version' => 5, 'nota' => 'Solicitud sin permiso.'])->assertForbidden();

        $this->actingAs($tutor)->post($resolver('corregir', $id), $sesion + [
            'version' => 5, 'motivo_correccion' => 'Se verificó el registro de asistencia.',
        ])->assertRedirect($expediente);
        $this->get($expediente)->assertOk()->assertSee('Marcar compromiso como cumplido');
        $this->post($resolver('cumplir', $id), ['version' => 6])->assertRedirect($expediente);
        $this->postJson($resolver('cumplir', $id), ['version' => 7])
            ->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->postJson($resolver('corregir', $id), [
            'version' => 7, 'asistencia' => 'NO_ASISTIO', 'motivo_correccion' => 'Se revisó el registro de nuevo.',
        ])->assertUnprocessable()->assertJsonValidationErrors('acuerdos');
        $this->assertDatabaseHas('seguimiento_estudiante.atencion_compromiso', [
            'id_atencion' => $atencion, 'cumplido' => true, 'cumplido_por' => $tutor->getKey(),
        ]);
        $this->actingAs($estudiante)->get($expediente)->assertOk()
            ->assertSee('Compromiso cumplido')->assertDontSee('Marcar compromiso como cumplido');
        $this->get(route('seguimiento-estudiante.avisos.index'))->assertOk()
            ->assertSee('Tu tutor registró el cumplimiento de un compromiso.')
            ->assertDontSee($sesion['observaciones']);

        $constancia = route('seguimiento-estudiante.solicitudes.tutoria.constancia', $id);
        $pdf = $this->get($constancia)->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="constancia-tutoria.pdf"');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->get(route('seguimiento-estudiante.solicitudes.tutoria.constancia', $otra))->assertNotFound();
        $this->actingAs($tutor)->get($constancia)->assertNotFound();
    }

    public function test_secretary_prevalidates_csv_without_applying_rows(): void
    {
        $user = User::factory()->create();
        $this->link($user, 1002);
        $this->grantPermission($user, 'seguimiento-estudiante.view');
        $url = route('seguimiento-estudiante.secretaria.prevalidar');
        $archivo = fn () => UploadedFile::fake()->createWithContent('notas.csv', <<<'CSV'
            identificador_matricula;unidad;nota
            00123;Parcial 1;17
            00123;Parcial 1;16
            00124;Parcial 2;22
            CSV);
        $this->actingAs($user)->post($url, ['tipo' => 'notas', 'archivo' => $archivo()])->assertForbidden();
        $this->grantPermission($user, 'seguimiento-estudiante.secretaria.prevalidar');
        $this->post($url, ['tipo' => 'notas', 'archivo' => $archivo()])->assertOk()
            ->assertSee('3 filas revisadas')->assertSee('2 filas con errores')->assertSee('repite la referencia')
            ->assertDontSee('00123');
        $this->assertDatabaseCount('gestion_matriculas.nota', 0);
        $this->assertDatabaseCount('seguimiento_estudiante.operacion', 0);

        $faltante = UploadedFile::fake()->createWithContent('asistencia.csv', "total_clases,faltas\n4,1");
        $this->postJson($url, ['tipo' => 'asistencia', 'archivo' => $faltante])
            ->assertUnprocessable()->assertJsonValidationErrors('archivo');
    }

    public function test_student_can_only_see_their_own_admission_and_leveling_data(): void
    {
        $this->createOtherStudent();
        $estudiante = User::factory()->create();
        $this->link($estudiante, 1001);
        $this->grantPermission($estudiante, 'seguimiento-estudiante.view');
        $url = route('seguimiento-estudiante.nivelacion.ingreso');
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($estudiante)->get($url)->assertOk()->assertSee('aún no tiene datos registrados');

        DB::table('seguimiento_estudiante.perfil_ingreso')->insert([
            ['id_estudiante' => 1001, 'puntaje_ingreso' => 85.5],
            ['id_estudiante' => 1002, 'puntaje_ingreso' => 91.75],
        ]);
        DB::table('core.docente')->insert([
            'id_docente' => 101, 'id_persona' => 1002, 'id_departamento' => 30,
            'codigo_docente' => 'D-101', 'estado' => 'Activo',
        ]);
        $dimension = DB::table('seguimiento_estudiante.dimension_brecha')
            ->insertGetId(['codigo' => 'MAT', 'nombre' => 'Matemática'], 'id_dimension');
        $programa = DB::table('seguimiento_estudiante.programa_nivelacion')->insertGetId([
            'id_periodo' => 202602, 'id_dimension' => $dimension, 'nombre' => 'Nivelación matemática',
            'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-29', 'estado' => 'Publicado',
        ], 'id_programa');
        $grupo = DB::table('seguimiento_estudiante.nivelacion_grupo')->insertGetId([
            'id_programa' => $programa, 'id_docente' => 101, 'codigo' => 'N1',
        ], 'id_grupo');
        $participante = DB::table('seguimiento_estudiante.nivelacion_participante')->insertGetId([
            'id_grupo' => $grupo, 'id_estudiante' => 1001,
        ], 'id_participante');
        $otro = DB::table('seguimiento_estudiante.nivelacion_participante')->insertGetId([
            'id_grupo' => $grupo, 'id_estudiante' => 1002,
        ], 'id_participante');
        $criterio = DB::table('seguimiento_estudiante.nivelacion_criterio_evaluacion')->insertGetId([
            'id_programa' => $programa, 'nombre' => 'Ejercicios', 'peso' => 100,
        ], 'id_criterio');
        DB::table('seguimiento_estudiante.nivelacion_calificacion')->insert([
            ['id_participante' => $participante, 'id_criterio' => $criterio, 'nota' => 15.30],
            ['id_participante' => $otro, 'id_criterio' => $criterio, 'nota' => 19.71],
        ]);
        $sesion = DB::table('seguimiento_estudiante.nivelacion_sesion')->insertGetId([
            'id_grupo' => $grupo, 'fecha' => '2026-11-02', 'tema' => 'Funciones básicas',
        ], 'id_sesion');
        DB::table('seguimiento_estudiante.nivelacion_asistencia')->insert([
            'id_sesion' => $sesion, 'id_participante' => $participante, 'estado' => 'Presente',
        ]);

        $this->get($url)->assertOk()->assertDontSee('Nivelación matemática');
        DB::table('seguimiento_estudiante.programa_nivelacion')->where('id_programa', $programa)
            ->update(['publicado_en' => '2026-11-02T12:00:00-05:00']);
        $this->get($url)->assertOk()->assertSee('Nivelación matemática')->assertDontSee('15.30');
        DB::table('seguimiento_estudiante.nivelacion_calificacion')
            ->update(['publicada_en' => '2026-11-17T15:00:00-05:00']);
        $this->get($url.'?estudiante=1002')->assertOk()
            ->assertSee('85.50')->assertSee('Nivelación matemática')->assertSee('Ejercicios')
            ->assertSee('15.30')->assertSee('Funciones básicas')->assertSee('Presente')
            ->assertDontSee('91.75')->assertDontSee('19.71');
    }

    public function test_tutor_versions_plan_in_the_school_and_period_of_their_assignment(): void
    {
        $tutor = User::factory()->create();
        $this->link($tutor, 1002);
        foreach (['view', 'tutoria.view', 'plan-tutoria.editar'] as $permission) {
            $this->grantPermission($tutor, 'seguimiento-estudiante.'.$permission);
        }
        DB::table('core.docente')->insert([
            'id_docente' => 101, 'id_persona' => 1002, 'id_departamento' => 30,
            'codigo_docente' => 'D-101', 'estado' => 'Activo',
        ]);
        DB::table('seguimiento_estudiante.tutor_asignacion')->insert([
            'id_estudiante' => 1001, 'id_docente' => 101, 'id_periodo' => 202602,
            'fecha_asignacion' => '2026-11-01',
        ]);
        $mostrar = route('seguimiento-estudiante.planes.mostrar', [20, 202602]);
        $guardar = route('seguimiento-estudiante.planes.guardar', [20, 202602]);
        $this->actingAs($tutor)->get(route('seguimiento-estudiante.planes.index'))->assertOk()
            ->assertSee('Ingeniería Informática');
        $this->get($mostrar)->assertOk()->assertSee('Sin guardar');
        $this->get(route('seguimiento-estudiante.planes.mostrar', [21, 202602]))->assertNotFound();

        $aporte = [
            'version' => 0, 'request_id' => (string) Str::uuid(), 'action' => 'BORRADOR',
            'fields' => ['foundation' => 'Acompañamiento en cursos iniciales.'], 'activities' => [],
        ];
        $this->post($guardar, $aporte)->assertRedirect($mostrar);
        $this->post($guardar, $aporte)->assertRedirect($mostrar);
        $this->postJson($guardar, array_replace($aporte, ['fields' => ['foundation' => 'Edición diferente.']]))
            ->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->get($mostrar)->assertOk()->assertSee('Versión 1')->assertSee('Acompañamiento en cursos iniciales.');

        $presentar = [
            'version' => 1, 'request_id' => (string) Str::uuid(), 'action' => 'PRESENTAR',
            'fields' => array_fill_keys(['foundation', 'needs', 'objectives', 'evaluation', 'improvement'], 'Descripción con contenido suficiente.'),
            'activities' => [[
                'id' => (string) Str::uuid(), 'title' => 'Taller de técnicas de estudio', 'timing' => 'Noviembre',
                'mode' => 'GRUPAL', 'target' => 1, 'unit' => 'SESIONES', 'evidence' => 'Registro de asistencia.',
            ]],
        ];
        $this->postJson($guardar, array_replace($presentar, ['activities' => []]))
            ->assertUnprocessable()->assertJsonValidationErrors('activities');
        $this->post($guardar, $presentar)->assertRedirect($mostrar);
        $this->get($mostrar)->assertOk()->assertSee('Última versión presentada 2');
        $plan = json_decode(DB::table('seguimiento_estudiante.operacion')
            ->where('id_escuela', 20)->where('clave', 'plan-tutoria|202602|1002')->value('datos'), true);
        $this->assertSame([1, 2], array_column($plan['versions'], 'version'));
        $this->assertSame(1, $plan['population']);
        $this->assertSame('PRESENTADO', $plan['status']);

        DB::table('core.persona')->insert([
            'id_persona' => 1003, 'tipo_documento' => 'DNI', 'numero_documento' => '70000003',
            'nombres' => 'Coordinación', 'apellido_paterno' => 'Prueba',
            'id_pais_nacionalidad' => 1, 'id_distrito_domicilio' => 1,
        ]);
        $coordinador = User::factory()->create();
        $this->link($coordinador, 1003);
        $this->grantPermission($coordinador, 'seguimiento-estudiante.view');
        $this->grantPermission($coordinador, 'seguimiento-estudiante.plan-tutoria.revisar');
        $presentados = route('seguimiento-estudiante.planes.presentados', [20, 202602]);
        $revisar = route('seguimiento-estudiante.planes.revisar', [20, 202602, 1002]);
        $decision = [
            'version' => 2, 'version_presentada' => 2, 'decision' => 'OBSERVADO',
            'comentario' => 'Respalda la meta con el registro de asistencia.',
        ];
        $this->postJson($revisar, $decision)->assertForbidden();
        $this->actingAs($coordinador)->postJson($revisar, $decision)->assertNotFound();
        DB::table('seguimiento_estudiante.coordinacion_ambito')->insert([
            'id_persona' => 1003, 'id_escuela' => 20, 'id_periodo' => 202602,
        ]);
        $this->get($presentados)->assertOk()->assertSee('Taller de técnicas de estudio');
        $this->postJson($revisar, array_replace($decision, ['version' => 1]))
            ->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->post($revisar, $decision)->assertRedirect($presentados);
        $this->postJson($revisar, $decision)->assertUnprocessable()->assertJsonValidationErrors('version');

        $this->actingAs($tutor)->get($mostrar)->assertOk()->assertSee($decision['comentario']);
        $this->post($guardar, array_replace($aporte, [
            'version' => 3, 'request_id' => (string) Str::uuid(),
            'fields' => ['foundation' => 'Borrador privado después de presentar.'],
        ]))->assertRedirect($mostrar);
        $this->actingAs($coordinador)->get($presentados)->assertOk()
            ->assertSee($decision['comentario'])->assertDontSee('Borrador privado después de presentar.');
        $this->actingAs($tutor);
        DB::table('seguimiento_estudiante.tutor_asignacion')->where('id_docente', 101)->update(['fecha_fin' => '2026-11-18']);
        $this->postJson($guardar, $presentar)->assertNotFound();
    }

    public function test_general_tutoring_plan_preserves_versions_and_restricts_pdf_access(): void
    {
        Storage::fake('local');
        $coordinador = User::factory()->create();
        $this->link($coordinador, 1001);
        $this->grantPermission($coordinador, 'seguimiento-estudiante.view');
        $this->grantPermission($coordinador, 'seguimiento-estudiante.plan-general.publicar');

        $ambito = [20, 202602];
        $mostrar = route('seguimiento-estudiante.plan-general.mostrar', $ambito);
        $publicar = route('seguimiento-estudiante.plan-general.publicar', $ambito);
        $contenido = "%PDF-1.4\nPlan general sintético\n%%EOF";
        $form = [
            'version' => 0,
            'request_id' => (string) Str::uuid(),
            'title' => 'Plan general de Tutoría 2026',
            'approval_reference' => 'ACTA-42',
            'approved_by' => 'Dirección de Escuela',
            'approved_on' => '2026-11-10',
            'valid_from' => '2026-11-11',
            'valid_until' => '2026-12-01',
            'approval_confirmed' => '1',
        ];
        $pdf = fn () => UploadedFile::fake()->createWithContent('plan.pdf', $contenido);

        $this->actingAs($coordinador)->post($publicar, $form + ['pdf' => $pdf()])->assertNotFound();
        DB::table('seguimiento_estudiante.coordinacion_ambito')->insert([
            'id_persona' => 1001, 'id_escuela' => 20, 'id_periodo' => 202602,
        ]);
        $this->get(route('seguimiento-estudiante.plan-general.index'))->assertOk()->assertSee($mostrar);
        $this->get($mostrar)->assertOk()->assertSee('Sin publicación');
        $this->post($publicar, $form + ['pdf' => $pdf()])->assertRedirect($mostrar);
        $this->post($publicar, $form + ['pdf' => $pdf()])->assertRedirect($mostrar);
        $this->postJson($publicar, array_replace($form, ['request_id' => (string) Str::uuid(), 'pdf' => $pdf()]))
            ->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->post($publicar, array_replace($form, [
            'version' => 1, 'request_id' => (string) Str::uuid(), 'title' => 'Segunda versión del plan', 'pdf' => $pdf(),
        ]))->assertRedirect($mostrar);
        $this->get($mostrar)->assertOk()->assertSee('Estado del plan: Vigente')->assertSee('Versión 2')->assertSee('Versión 1');

        $registro = json_decode(DB::table('seguimiento_estudiante.operacion')
            ->where('id_escuela', 20)->where('clave', 'f4-general-tutoria|202602')->value('datos'), true);
        $this->assertCount(2, $registro['documents']);
        $actual = collect($registro['documents'])->firstWhere('version', 2);
        $descargar = route('seguimiento-estudiante.plan-general.descargar', [...$ambito, $actual['id']]);
        $retirar = route('seguimiento-estudiante.plan-general.retirar', [...$ambito, $actual['id']]);
        $motivo = ['version' => 2, 'reason' => 'Retiro autorizado por Dirección.'];

        $tutor = User::factory()->create();
        $this->link($tutor, 1002);
        $this->grantPermission($tutor, 'seguimiento-estudiante.view');
        $this->grantPermission($tutor, 'seguimiento-estudiante.tutoria.view');
        $this->actingAs($tutor)->get($descargar)->assertNotFound();
        DB::table('core.docente')->insert([
            'id_docente' => 101, 'id_persona' => 1002, 'id_departamento' => 30,
            'codigo_docente' => 'D-101', 'estado' => 'Activo',
        ]);
        DB::table('seguimiento_estudiante.tutor_asignacion')->insert([
            'id_estudiante' => 1001, 'id_docente' => 101, 'id_periodo' => 202602, 'fecha_asignacion' => '2026-11-01',
        ]);
        $this->get($descargar)->assertOk()->assertDownload('F4-general-v2.pdf')->assertContent($contenido);
        $this->get(route('seguimiento-estudiante.plan-general.descargar', [21, 202602, $actual['id']]))->assertNotFound();
        $this->postJson($retirar, $motivo)->assertForbidden();

        $this->actingAs($coordinador)->post($retirar, $motivo)->assertRedirect($mostrar);
        $this->get($mostrar)->assertSee('Estado del plan: Retirado')->assertSee($motivo['reason']);
        $this->get($descargar)->assertOk()->assertContent($contenido);
        Storage::disk('local')->put('seguimiento-estudiante/plan-general/20/202602/'.$actual['sha256'].'.pdf', 'Daño de prueba');
        $this->get($descargar)->assertServiceUnavailable();
    }

    public function test_coordination_assigns_and_reassigns_without_losing_the_previous_tutor_history(): void
    {
        DB::table('core.persona')->insert([
            'id_persona' => 1003, 'tipo_documento' => 'DNI', 'numero_documento' => '70000003',
            'nombres' => 'Segundo tutor', 'apellido_paterno' => 'Prueba',
            'id_pais_nacionalidad' => 1, 'id_distrito_domicilio' => 1,
        ]);
        DB::table('core.persona')->insert([
            'id_persona' => 1004, 'tipo_documento' => 'DNI', 'numero_documento' => '70000004',
            'nombres' => 'Coordinación', 'apellido_paterno' => 'Prueba',
            'id_pais_nacionalidad' => 1, 'id_distrito_domicilio' => 1,
        ]);
        $tutores = [];
        foreach ([101 => 1002, 102 => 1003] as $docente => $persona) {
            DB::table('core.docente')->insert([
                'id_docente' => $docente, 'id_persona' => $persona, 'id_departamento' => 30,
                'codigo_docente' => 'D-'.$docente, 'estado' => 'Activo',
            ]);
            $tutores[$docente] = User::factory()->create();
            $this->link($tutores[$docente], $persona);
            foreach (['view', 'tutoria.view', 'tutoria.programar'] as $permiso) {
                $this->grantPermission($tutores[$docente], 'seguimiento-estudiante.'.$permiso);
            }
        }
        $coordinador = User::factory()->create();
        $this->link($coordinador, 1004);
        $this->grantPermission($coordinador, 'seguimiento-estudiante.view');
        $gestion = route('seguimiento-estudiante.asignaciones.mostrar', [20, 202602]);
        $guardar = route('seguimiento-estudiante.asignaciones.guardar', [20, 202602]);
        $form = ['id_estudiante' => 1001, 'id_docente' => 101, 'id_asignacion' => 0, 'motivo' => 'Asignación inicial.'];

        $this->actingAs($coordinador)->postJson($guardar, $form)->assertForbidden();
        $this->grantPermission($coordinador, 'seguimiento-estudiante.tutoria.asignar');
        $this->postJson($guardar, $form)->assertNotFound();
        DB::table('seguimiento_estudiante.coordinacion_ambito')->insert([
            'id_persona' => 1004, 'id_escuela' => 20, 'id_periodo' => 202602,
        ]);
        DB::table('seguimiento_estudiante.tutor_habilitado')->insert([
            'id_docente' => 101, 'id_escuela' => 20, 'id_periodo' => 202602,
        ]);

        $this->get(route('seguimiento-estudiante.asignaciones.index'))->assertOk()->assertSee('Ingeniería Informática');
        $this->get($gestion)->assertOk()->assertSee('Asignar tutor')->assertDontSee('D-102');
        $this->postJson($guardar, array_replace($form, ['id_docente' => 102]))
            ->assertUnprocessable()->assertJsonValidationErrors('id_docente');
        $this->postJson(route('seguimiento-estudiante.asignaciones.guardar', [21, 202602]), $form)->assertNotFound();
        $this->post($guardar, $form)->assertRedirect($gestion);
        $this->get($gestion)->assertOk()->assertSee('Reasignar tutor');
        $asignacion = DB::table('seguimiento_estudiante.tutor_asignacion')->value('id_asignacion');
        $this->assertDatabaseHas('seguimiento_estudiante.tutor_asignacion', [
            'id_asignacion' => $asignacion, 'id_docente' => 101, 'estado' => 'Activa', 'id_usuario_asignador' => $coordinador->getKey(),
        ]);

        DB::table('seguimiento_estudiante.tipo_atencion')->insert(['nombre' => 'Tutoría']);
        $estudiante = User::factory()->create();
        $this->link($estudiante, 1001);
        $this->grantPermission($estudiante, 'seguimiento-estudiante.view');
        $this->grantPermission($estudiante, 'seguimiento-estudiante.solicitud.create');
        $this->actingAs($estudiante)->post(route('seguimiento-estudiante.solicitudes.tutoria.store', 1001), [
            'id_periodo' => 202602, 'clave_envio' => (string) Str::uuid(),
            'motivo' => 'Solicitud que debe pasar al nuevo tutor.',
        ])->assertRedirect(route('seguimiento-estudiante.expediente', 1001));
        $this->assertDatabaseHas('seguimiento_estudiante.tutoria_aviso', [
            'id_usuario' => $tutores[101]->getKey(), 'mensaje' => 'Tienes una nueva solicitud de tutoría.',
        ]);
        $solicitud = DB::table('seguimiento_estudiante.solicitud_estudiante')->value('id_solicitud');
        $cita = [
            'fecha_hora' => '2026-11-20T15:00', 'duracion_minutos' => 30, 'modalidad' => 'PRESENCIAL',
            'lugar' => 'Aula de prueba', 'nota' => 'Horario comunicado.',
        ];
        $expediente = route('seguimiento-estudiante.expediente', 1001);
        $programar = route('seguimiento-estudiante.solicitudes.tutoria.programar', [1001, $solicitud]);
        $this->actingAs($tutores[101])->post($programar, $cita)->assertRedirect($expediente);

        DB::table('seguimiento_estudiante.tutor_habilitado')->insert([
            'id_docente' => 102, 'id_escuela' => 20, 'id_periodo' => 202602,
        ]);
        $reasignar = array_replace($form, ['id_docente' => 102, 'motivo' => 'Reasignación autorizada.']);
        $this->actingAs($coordinador)->postJson($guardar, $reasignar)
            ->assertUnprocessable()->assertJsonValidationErrors('id_asignacion');
        $this->post($guardar, array_replace($reasignar, ['id_asignacion' => $asignacion]))->assertRedirect($gestion);
        $this->assertDatabaseHas('seguimiento_estudiante.tutor_asignacion', [
            'id_asignacion' => $asignacion, 'estado' => 'Finalizada', 'causa_fin' => 'REASIGNACION',
        ]);
        $this->assertDatabaseHas('seguimiento_estudiante.solicitud_estudiante', [
            'id_solicitud' => $solicitud, 'estado' => 'Pendiente', 'version' => 3,
        ]);
        $this->assertDatabaseHas('seguimiento_estudiante.atencion_evento', ['tipo' => 'cancelacion', 'lugar' => $cita['lugar']]);

        $this->actingAs($tutores[101])->get($expediente)->assertNotFound();
        $this->postJson($programar, $cita)->assertNotFound();
        $this->actingAs($tutores[102])->get($expediente)->assertOk()
            ->assertSee('Solicitud que debe pasar al nuevo tutor.')->assertDontSee('Aula de prueba');

        $bandeja = route('seguimiento-estudiante.avisos.index');
        $this->get($bandeja)->assertOk()->assertSee('Tienes una nueva asignación de tutoría.')
            ->assertDontSee('Una de tus asignaciones de tutoría se reasignó.')
            ->assertDontSee('Solicitud que debe pasar al nuevo tutor.');
        $aviso = DB::table('seguimiento_estudiante.tutoria_aviso')->where('id_usuario', $tutores[102]->getKey())->value('id_aviso');
        $leer = route('seguimiento-estudiante.avisos.leer', $aviso);
        $this->actingAs($tutores[101])->postJson($leer)->assertNotFound();
        $this->actingAs($tutores[102])->post($leer)->assertRedirect($bandeja);
        $this->assertNotNull(DB::table('seguimiento_estudiante.tutoria_aviso')->where('id_aviso', $aviso)->value('leido_en'));
        $this->post($programar, $cita + ['version' => 3])->assertRedirect($expediente);
        $this->assertDatabaseCount('seguimiento_estudiante.atencion', 2);

        $actual = DB::table('seguimiento_estudiante.tutor_asignacion')->where('estado', 'Activa')->value('id_asignacion');
        $this->actingAs($coordinador)->post($guardar, array_replace($form, ['id_asignacion' => $actual]))
            ->assertRedirect($gestion);
        $this->assertDatabaseCount('seguimiento_estudiante.tutor_asignacion', 3);
        $this->assertDatabaseHas('seguimiento_estudiante.tutor_asignacion', ['id_docente' => 101, 'estado' => 'Activa']);

        DB::statement(<<<'SQL'
            INSERT INTO core.persona (id_persona, tipo_documento, numero_documento, nombres, apellido_paterno,
                id_pais_nacionalidad, id_distrito_domicilio)
            SELECT n, 'DNI', (70100000 + n)::text, 'Estudiante', 'Sintético', 1, 1
              FROM generate_series(2001, 2020) AS n
            SQL);
        DB::statement(<<<'SQL'
            INSERT INTO core.estudiante (id_estudiante, id_persona, codigo_universitario, id_escuela, id_plan,
                id_periodo_ingreso, id_modalidad_ingreso, estado)
            SELECT n, n, 'E' || n, 20, 50, 202602, 40, 'Activo'
              FROM generate_series(2001, 2020) AS n
            SQL);
        DB::statement(<<<'SQL'
            INSERT INTO seguimiento_estudiante.tutor_asignacion (id_estudiante, id_docente, id_periodo, fecha_asignacion)
            SELECT n, 102, 202602, '2026-11-01'
              FROM generate_series(2001, 2020) AS n
            SQL);
        $actual = DB::table('seguimiento_estudiante.tutor_asignacion')->where('id_estudiante', 1001)
            ->where('estado', 'Activa')->value('id_asignacion');
        $this->postJson($guardar, array_replace($reasignar, ['id_asignacion' => $actual]))
            ->assertUnprocessable()->assertJsonValidationErrors('id_docente');
        $this->assertDatabaseHas('seguimiento_estudiante.tutor_asignacion', [
            'id_asignacion' => $actual, 'id_docente' => 101, 'estado' => 'Activa',
        ]);
    }

    public function test_coordination_closes_tutor_assignments_using_the_current_approved_catalogue(): void
    {
        DB::table('core.persona')->insert([
            'id_persona' => 1003, 'tipo_documento' => 'DNI', 'numero_documento' => '70000003',
            'nombres' => 'Coordinación', 'apellido_paterno' => 'Prueba',
            'id_pais_nacionalidad' => 1, 'id_distrito_domicilio' => 1,
        ]);
        DB::table('core.docente')->insert([
            'id_docente' => 101, 'id_persona' => 1002, 'id_departamento' => 30, 'codigo_docente' => 'D-101', 'estado' => 'Activo',
        ]);
        $asignacion = DB::table('seguimiento_estudiante.tutor_asignacion')->insertGetId([
            'id_estudiante' => 1001, 'id_docente' => 101, 'id_periodo' => 202602, 'fecha_asignacion' => '2026-11-01',
        ], 'id_asignacion');
        $coordinador = User::factory()->create();
        $this->link($coordinador, 1003);
        $tutor = User::factory()->create();
        $this->link($tutor, 1002);
        $this->grantPermission($tutor, 'seguimiento-estudiante.view');
        $this->grantPermission($tutor, 'seguimiento-estudiante.tutoria.view');
        $ambito = [20, 202602];
        $catalogo = route('seguimiento-estudiante.asignaciones.causas', $ambito);
        $guardar = route('seguimiento-estudiante.asignaciones.causas.guardar', $ambito);
        $mostrar = route('seguimiento-estudiante.asignaciones.mostrar', $ambito);
        $finalizar = route('seguimiento-estudiante.asignaciones.finalizar', $ambito);
        $datos = [
            'revision' => 0, 'estado' => 'VIGENTE', 'referencia' => 'Acta institucional 07',
            'motivo' => 'Publicación con aprobación registrada.', 'autoridad' => 'Dirección de Escuela',
            'fecha_aprobacion' => '2026-11-18', 'aprobado' => '1',
            'causas' => [
                ['codigo' => 'CESAR', 'nombre' => 'Finalización aprobada', 'efecto' => 'FINALIZAR', 'habilitada' => true],
                ['codigo' => 'CAMBIO', 'nombre' => 'Cambio obligatorio', 'efecto' => 'REASIGNAR', 'habilitada' => true],
            ],
        ];
        $this->actingAs($coordinador);
        $this->grantPermission($coordinador, 'seguimiento-estudiante.view');
        $this->grantPermission($coordinador, 'seguimiento-estudiante.tutoria.asignar');
        $this->postJson($guardar, $datos)->assertForbidden();
        $this->grantPermission($coordinador, 'seguimiento-estudiante.tutoria.causas.gestionar');
        $this->postJson($guardar, $datos)->assertNotFound();
        DB::table('seguimiento_estudiante.coordinacion_ambito')->insert([
            'id_persona' => 1003, 'id_escuela' => 20, 'id_periodo' => 202602,
        ]);
        $this->get($catalogo)->assertOk();
        $this->postJson($guardar, array_replace($datos, ['aprobado' => '0']))->assertUnprocessable()->assertJsonValidationErrors('aprobado');
        $this->post($guardar, $datos)->assertRedirect($catalogo);
        $this->postJson($guardar, $datos)->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->get($mostrar)->assertOk()->assertSee('Cerrar asignación con causa');
        $registro = json_decode(DB::table('seguimiento_estudiante.operacion')
            ->where('id_escuela', 20)->where('clave', 'causas-asignacion|202602')->value('datos'), true);
        $form = [
            'id_estudiante' => 1001, 'id_asignacion' => $asignacion, 'version_causa' => $registro['activa'],
            'causa' => 'CESAR', 'motivo' => 'Cese de la asignación autorizado.',
        ];
        $this->postJson($finalizar, array_replace($form, ['version_causa' => (string) Str::uuid()]))
            ->assertUnprocessable()->assertJsonValidationErrors('version_causa');
        $this->postJson($finalizar, array_replace($form, ['causa' => 'CAMBIO']))
            ->assertUnprocessable()->assertJsonValidationErrors('reemplazo');
        $this->actingAs($tutor)->get(route('seguimiento-estudiante.expediente', 1001))->assertOk();
        $this->postJson($finalizar, $form)->assertForbidden();

        $this->actingAs($coordinador)->post($finalizar, $form)->assertRedirect($mostrar);
        $this->post($finalizar, $form)->assertRedirect($mostrar);
        $this->assertDatabaseHas('seguimiento_estudiante.tutor_asignacion', [
            'id_asignacion' => $asignacion, 'estado' => 'Finalizada', 'causa_fin' => 'CESAR',
            'version_causa_fin' => $registro['activa'], 'id_usuario_finalizador' => $coordinador->getKey(),
        ]);
        $this->assertDatabaseCount('seguimiento_estudiante.tutor_asignacion', 1);
        $this->actingAs($tutor)->get(route('seguimiento-estudiante.expediente', 1001))->assertNotFound();

        DB::table('core.docente')->insert([
            'id_docente' => 102, 'id_persona' => 1003, 'id_departamento' => 30, 'codigo_docente' => 'D-102', 'estado' => 'Activo',
        ]);
        DB::table('seguimiento_estudiante.tutor_habilitado')->insert([
            'id_docente' => 101, 'id_escuela' => 20, 'id_periodo' => 202602,
        ]);
        $nueva = DB::table('seguimiento_estudiante.tutor_asignacion')->insertGetId([
            'id_estudiante' => 1001, 'id_docente' => 102, 'id_periodo' => 202602, 'fecha_asignacion' => '2026-11-19',
        ], 'id_asignacion');
        $cambio = array_replace($form, [
            'id_asignacion' => $nueva, 'causa' => 'CAMBIO', 'reemplazo' => 101,
            'motivo' => 'Reemplazo formal autorizado.',
        ]);
        $this->actingAs($coordinador)->post($finalizar, $cambio)->assertRedirect($mostrar);
        $this->post($finalizar, $cambio)->assertRedirect($mostrar);
        $this->assertDatabaseHas('seguimiento_estudiante.tutor_asignacion', [
            'id_asignacion' => $nueva, 'estado' => 'Finalizada', 'causa_fin' => 'CAMBIO',
            'version_causa_fin' => $registro['activa'], 'id_usuario_finalizador' => $coordinador->getKey(),
        ]);
        $this->assertDatabaseHas('seguimiento_estudiante.tutor_asignacion', [
            'id_estudiante' => 1001, 'id_docente' => 101, 'estado' => 'Activa',
        ]);
        $this->assertDatabaseCount('seguimiento_estudiante.tutor_asignacion', 3);
    }

    public function test_student_with_two_academic_profiles_can_download_from_the_selected_school(): void
    {
        DB::table('core.escuela_profesional')->insert([
            'id_escuela' => 21, 'id_facultad' => 10, 'codigo' => 'EP-SEG', 'nombre' => 'Segunda Escuela', 'estado' => 'Activo',
        ]);
        DB::table('core.plan_estudios')->insert([
            'id_plan' => 51, 'id_escuela' => 21, 'codigo' => 'PLAN-SEG', 'nombre' => 'Plan de Segunda Escuela',
            'version' => '2026', 'anio_aprobacion' => 2026, 'fecha_vigencia_inicio' => '2026-01-01', 'estado' => 'Vigente',
        ]);
        DB::table('core.estudiante')->insert([
            'id_estudiante' => 1003, 'id_persona' => 1001, 'id_escuela' => 21, 'id_plan' => 51,
            'codigo_universitario' => 'E2026003', 'id_periodo_ingreso' => 202602, 'id_modalidad_ingreso' => 40, 'estado' => 'Activo',
        ]);
        $this->createOtherStudent();
        DB::table('seguimiento_estudiante.perfil_ingreso')->insert([
            ['id_estudiante' => 1001, 'puntaje_ingreso' => 81.25],
            ['id_estudiante' => 1003, 'puntaje_ingreso' => 92.50],
        ]);
        $estudiante = User::factory()->create();
        $this->link($estudiante, 1001);
        $this->grantPermission($estudiante, 'seguimiento-estudiante.view');
        $this->actingAs($estudiante)->get(route('seguimiento-estudiante.nivelacion.ingreso', 1003))
            ->assertOk()->assertSee('92.50')->assertDontSee('81.25');
        $this->get(route('seguimiento-estudiante.nivelacion.ingreso', 1002))->assertNotFound();

        DB::table('core.docente')->insert([
            'id_docente' => 101, 'id_persona' => 1002, 'id_departamento' => 30, 'codigo_docente' => 'D-101', 'estado' => 'Activo',
        ]);
        $asignacion = DB::table('seguimiento_estudiante.tutor_asignacion')->insertGetId([
            'id_estudiante' => 1003, 'id_docente' => 101, 'id_periodo' => 202602, 'fecha_asignacion' => '2026-11-01',
        ], 'id_asignacion');
        $tipo = DB::table('seguimiento_estudiante.tipo_atencion')->insertGetId(['nombre' => 'Tutoría'], 'id_tipo_atencion');
        $solicitud = DB::table('seguimiento_estudiante.solicitud_estudiante')->insertGetId([
            'id_estudiante' => 1003, 'id_periodo' => 202602, 'tipo' => 'Tutoria', 'asunto' => 'Tutoría', 'estado' => 'Atendida',
        ], 'id_solicitud');
        DB::table('seguimiento_estudiante.atencion')->insert([
            'id_asignacion' => $asignacion, 'id_tipo_atencion' => $tipo, 'id_solicitud' => $solicitud,
            'fecha_hora' => '2026-11-18T14:00:00-05:00', 'duracion_minutos' => 30,
        ]);
        $this->get(route('seguimiento-estudiante.solicitudes.tutoria.constancia', $solicitud))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    private function link(User $user, int $persona): void
    {
        DB::table('seguimiento_estudiante.identidad_cuenta')->insert([
            'id_usuario' => $user->getKey(),
            'id_persona' => $persona,
        ]);
    }

    private function grantPermission(User $user, string $permission): void
    {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    private function createOtherStudent(): void
    {
        DB::table('core.estudiante')->insert([
            'id_estudiante' => 1002,
            'id_persona' => 1002,
            'codigo_universitario' => 'E2026002',
            'id_escuela' => 20,
            'id_plan' => 50,
            'id_periodo_ingreso' => 202602,
            'id_modalidad_ingreso' => 40,
            'estado' => 'Activo',
        ]);
    }
}
