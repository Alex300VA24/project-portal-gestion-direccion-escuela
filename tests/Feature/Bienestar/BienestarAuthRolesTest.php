<?php

namespace Tests\Feature\Bienestar;

use App\Models\User;
use App\Modules\Bienestar\Database\Seeders\BienestarDemoSeeder;
use App\Modules\Bienestar\Models\AlertaRiesgo;
use App\Modules\Bienestar\Models\ArticuloInformativo;
use App\Modules\Bienestar\Models\CasoBienestar;
use App\Modules\Bienestar\Models\Cita;
use App\Modules\Bienestar\Models\Derivacion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class BienestarAuthRolesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Asegurar que el seeder de demostración esté cargado
        $this->seed(BienestarDemoSeeder::class);
    }

    public function test_public_user_can_view_published_articles_but_not_drafts(): void
    {
        $articuloPublicado = ArticuloInformativo::where('estado', 'publicado')->firstOrFail();
        $articuloBorrador = ArticuloInformativo::where('estado', 'borrador')->firstOrFail();

        // 1. Catálogo público
        $response = $this->get(route('bienestar.informacion.index'));
        $response->assertStatus(200);
        $response->assertSee($articuloPublicado->titulo);
        $response->assertDontSee($articuloBorrador->titulo);

        // 2. Detalle de artículo publicado
        $this->get(route('bienestar.informacion.show', $articuloPublicado))
            ->assertStatus(200)
            ->assertSee($articuloPublicado->titulo);

        // 3. Detalle de borrador bloqueado para público (404)
        $this->get(route('bienestar.informacion.show', $articuloBorrador))
            ->assertStatus(404);

        // 4. Intento de crear artículo redirige a login
        $this->get(route('bienestar.informacion.create'))
            ->assertRedirect(route('login'));

        // 5. Zonas operativas restringidas redirigen a login
        $this->get(route('bienestar.panel.index'))->assertRedirect(route('login'));
        $this->get(route('bienestar.profesor.index'))->assertRedirect(route('login'));
        $this->get(route('bienestar.director.index'))->assertRedirect(route('login'));
    }

    public function test_alumno_can_view_public_articles_submit_support_request_and_view_citas(): void
    {
        $alumno = User::where('email', 'alumno@portal.local')->firstOrFail();
        $articuloBorrador = ArticuloInformativo::where('estado', 'borrador')->firstOrFail();

        $this->actingAs($alumno);

        // Puede ver catálogo público
        $this->get(route('bienestar.informacion.index'))->assertStatus(200);

        // No puede ver borradores
        $this->get(route('bienestar.informacion.show', $articuloBorrador))->assertStatus(404);

        // 403 en zonas no autorizadas para alumno
        $this->get(route('bienestar.profesor.index'))->assertStatus(403);
        $this->get(route('bienestar.panel.index'))->assertStatus(403);
        $this->get(route('bienestar.director.index'))->assertStatus(403);

        // Acceso a vistas propias de estudiante (200)
        $this->get(route('bienestar.alumno.solicitudes.index'))->assertStatus(200);
        $this->get(route('bienestar.alumno.solicitudes.create'))->assertStatus(200);
        $this->get(route('bienestar.alumno.citas.index'))->assertStatus(200);

        // Enviar nueva solicitud de apoyo socioeconómico
        $idBeneficio = DB::table('bienestar.beneficio')->where('activo', true)->value('id_beneficio');
        $this->post(route('bienestar.alumno.solicitudes.store'), [
            'id_beneficio' => $idBeneficio,
            'motivo' => 'Solicitud de apoyo alimentario debido a vulnerabilidad económica.',
        ])->assertRedirect(route('bienestar.alumno.solicitudes.index'));

        $this->assertDatabaseHas('bienestar.solicitud_apoyo', [
            'id_beneficio' => $idBeneficio,
            'motivo' => 'Solicitud de apoyo alimentario debido a vulnerabilidad económica.',
            'estado' => 'Pendiente',
        ]);
    }

    public function test_profesor_can_report_student_at_risk_and_view_own_reports(): void
    {
        $profesor = User::where('email', 'profesor@portal.local')->firstOrFail();

        $this->actingAs($profesor);

        // 1. Acceso a portal docente
        $this->get(route('bienestar.profesor.index'))->assertStatus(200);
        $this->get(route('bienestar.profesor.create'))->assertStatus(200);

        // 2. Búsqueda exacta de estudiante por código
        $responseBusqueda = $this->getJson(route('bienestar.profesor.buscar-estudiante', ['codigo' => '1021400222']));
        $responseBusqueda->assertStatus(200);
        $responseBusqueda->assertJsonFragment(['codigo' => '1021400222']);

        // 3. Envío de reporte de riesgo docente
        $responseReporte = $this->post(route('bienestar.profesor.store'), [
            'codigo_estudiante' => '1021400222',
            'curso' => 'Ingeniería de Software I',
            'motivo_reporte' => 'Ausentismo recurrente en evaluaciones',
            'descripcion_reporte' => 'El estudiante ha faltado a las últimas 3 prácticas consecutivas.',
            'prioridad' => 'alta',
        ]);

        $responseReporte->assertRedirect(route('bienestar.profesor.index'));
        $this->assertDatabaseHas('bienestar.alerta_riesgo', [
            'curso' => 'Ingeniería de Software I',
            'origen' => 'reporte_docente',
            'prioridad' => 'alta',
            'estado' => 'pendiente',
        ]);

        // 4. 403 en panel de bienestar y dirección
        $this->get(route('bienestar.panel.index'))->assertStatus(403);
        $this->get(route('bienestar.director.index'))->assertStatus(403);
    }

    public function test_secretaria_can_manage_alerts_articles_appointments_and_derivations(): void
    {
        $secretaria = User::where('email', 'secretaria@portal.local')->firstOrFail();
        $articuloBorrador = ArticuloInformativo::where('estado', 'borrador')->firstOrFail();
        $alerta = AlertaRiesgo::where('estado', 'pendiente')->firstOrFail();

        $this->actingAs($secretaria);

        // 1. Acceso al panel de Bienestar
        $this->get(route('bienestar.panel.index'))->assertStatus(200);

        // 2. Acceso a artículos en borrador y formulario de creación
        $this->get(route('bienestar.informacion.show', $articuloBorrador))->assertStatus(200);
        $this->get(route('bienestar.informacion.create'))->assertStatus(200);

        // 3. Sanitización de artículos informativos (elimina etiquetas peligrosas)
        $this->post(route('bienestar.informacion.store'), [
            'titulo' => 'Normativa de Tutoría 2026',
            'resumen' => 'Resumen de inducción y tutoría docente.',
            'detalle' => '<p>Contenido seguro</p><script>alert("hack")</script><iframe src="evil.com"></iframe>',
            'fuente_normativa' => 'Directiva DBU-02',
            'fecha_ultima_revision' => date('Y-m-d'),
            'estado' => 'publicado',
        ])->assertRedirect(route('bienestar.informacion.index'));

        $articuloCreado = ArticuloInformativo::where('titulo', 'Normativa de Tutoría 2026')->firstOrFail();
        $this->assertStringNotContainsString('<script>', $articuloCreado->detalle);
        $this->assertStringNotContainsString('<iframe>', $articuloCreado->detalle);
        $this->assertStringContainsString('Contenido seguro', $articuloCreado->detalle);

        // 4. Atención de alerta: abrir caso
        $tipoCasoId = DB::table('bienestar.tipo_caso')->value('id_tipo_caso');
        $this->post(route('bienestar.panel.guardar-atencion', $alerta), [
            'accion' => 'abrir_caso',
            'id_tipo_caso' => $tipoCasoId,
            'descripcion_caso' => 'Apertura formal de caso para estudiante en riesgo',
        ])->assertRedirect(route('bienestar.panel.index'));

        $alerta->refresh();
        $this->assertEquals('en_atencion', $alerta->estado);
        $this->assertNotNull($alerta->id_caso);

        // 5. Derivación: Requiere obligatoriamente consentimiento
        $this->post(route('bienestar.panel.guardar-derivacion'), [
            'id_caso' => $alerta->id_caso,
            'entidad_externa' => 'Centro de Salud Mental Comunitario',
            'motivo' => 'Apoyo psicológico integral',
            'consentimiento_estudiante' => 0,
        ])->assertSessionHasErrors('consentimiento_estudiante');

        // Derivación consentida
        $this->post(route('bienestar.panel.guardar-derivacion'), [
            'id_caso' => $alerta->id_caso,
            'entidad_externa' => 'Centro de Salud Mental Comunitario',
            'motivo' => 'Apoyo psicológico integral',
            'consentimiento_estudiante' => 1,
        ])->assertRedirect();

        $derivacion = Derivacion::where('id_caso', $alerta->id_caso)->latest('id_derivacion')->firstOrFail();
        $this->assertTrue($derivacion->consentimiento_estudiante);

        // Ficha imprimible permitida
        $this->get(route('bienestar.panel.imprimir-derivacion', $derivacion))->assertStatus(200);

        // Si el consentimiento fuera false, devuelve 403
        $derivacion->update(['consentimiento_estudiante' => false]);
        $this->get(route('bienestar.panel.imprimir-derivacion', $derivacion))->assertStatus(403);

        // 6. Registro de resultado externo de derivación (cifrado)
        $this->post(route('bienestar.panel.registrar-resultado', $derivacion), [
            'resultado_especialista' => 'Estudiante asistió a evaluación psicológica clínica completa.',
            'estado_derivacion' => 'Atendida',
        ])->assertRedirect();

        $derivacion->refresh();
        $this->assertEquals('Estudiante asistió a evaluación psicológica clínica completa.', $derivacion->resultado_especialista);
        $this->assertEquals('Atendida', $derivacion->estado);
    }

    public function test_secretaria_can_create_manual_alert_and_evaluate_support_request(): void
    {
        $secretaria = User::where('email', 'secretaria@portal.local')->firstOrFail();
        $this->actingAs($secretaria);

        // 1. Creación de alerta manual
        $this->get(route('bienestar.panel.alertas.create'))->assertStatus(200);

        $this->post(route('bienestar.panel.alertas.store-manual'), [
            'codigo_estudiante' => '1021400121',
            'periodo' => '2026-I',
            'prioridad' => 'alta',
            'motivo_reporte' => 'Estudiante atendido directamente en entrevista presencial de bienestar',
            'descripcion_reporte' => 'Estudiante se presentó en coordinación reportando problemas personales graves.',
        ])->assertRedirect(route('bienestar.panel.index'));

        $this->assertDatabaseHas('bienestar.alerta_riesgo', [
            'periodo' => '2026-I',
            'origen' => 'manual_bienestar',
            'prioridad' => 'alta',
            'estado' => 'pendiente',
            'regla' => 'manual_bienestar',
            'motivo_reporte' => 'Estudiante atendido directamente en entrevista presencial de bienestar',
        ]);

        // 2. Evaluación de solicitud de apoyo socioeconómico
        $idSolicitud = DB::table('bienestar.solicitud_apoyo')->where('estado', 'Pendiente')->value('id_solicitud');
        $this->assertNotNull($idSolicitud);

        $this->get(route('bienestar.panel.solicitudes.index'))->assertStatus(200);

        $this->post(route('bienestar.panel.solicitudes.evaluar', $idSolicitud), [
            'estado' => 'Aprobada',
        ])->assertRedirect(route('bienestar.panel.solicitudes.index'));

        $this->assertDatabaseHas('bienestar.solicitud_apoyo', [
            'id_solicitud' => $idSolicitud,
            'estado' => 'Aprobada',
        ]);
    }

    public function test_clinical_and_report_data_is_encrypted_at_rest_in_database(): void
    {
        // 1. CasoBienestar (descripcion cifrada en BD cruda y descifrada vía Eloquent)
        $casoRaw = DB::table('bienestar.caso_bienestar')->whereNotNull('descripcion')->first();
        $this->assertNotNull($casoRaw);
        $this->assertStringNotContainsString('Estudiante presenta reiteradas tardanzas', $casoRaw->descripcion);

        $casoModel = CasoBienestar::find($casoRaw->id_caso);
        $this->assertStringContainsString('Estudiante presenta reiteradas tardanzas', $casoModel->descripcion);

        // 2. Cita (notas_atencion cifrada en BD)
        $citaRaw = DB::table('bienestar.cita')->whereNotNull('notas_atencion')->first();
        $this->assertNotNull($citaRaw);
        $this->assertStringNotContainsString('Cita inicial de orientación', $citaRaw->notas_atencion);

        $citaModel = Cita::find($citaRaw->id_cita);
        $this->assertStringContainsString('Cita inicial de orientación', $citaModel->notas_atencion);

        // 3. Derivacion (resultado_especialista cifrado)
        $derivacion = Derivacion::first();
        if ($derivacion) {
            $derivacion->resultado_especialista = 'Diagnóstico confidencial externo';
            $derivacion->save();

            $rawDeriv = DB::table('bienestar.derivacion')->where('id_derivacion', $derivacion->id_derivacion)->first();
            $this->assertStringNotContainsString('Diagnóstico confidencial externo', $rawDeriv->resultado_especialista);
            $this->assertEquals('Diagnóstico confidencial externo', $derivacion->fresh()->resultado_especialista);
        }
    }

    public function test_director_only_sees_aggregated_statistics_with_k_anonymity_and_mutations_return_403(): void
    {
        $director = User::where('email', 'director@portal.local')->firstOrFail();

        $this->actingAs($director);

        // 1. Acceso a vista estadística
        $response = $this->get(route('bienestar.director.index'));
        $response->assertStatus(200);

        // 2. K-anonimato: Celdas menores a 5 se muestran como "menos de 5"
        $response->assertSee('menos de 5');

        // 3. Regla obligatoria: Sin listados de estudiantes, ni nombres, ni códigos, ni EST-***
        $response->assertDontSee('Carlos Méndez');
        $response->assertDontSee('Lucía Paredes');
        $response->assertDontSee('1021400222');
        $response->assertDontSee('EST-***');

        // 4. Bloqueo de mutaciones: cualquier POST, PUT, DELETE devuelve 403
        $this->post('/bienestar/director/cualquier-endpoint', ['data' => 1])->assertStatus(403);
        $this->put('/bienestar/director/modificar', ['data' => 1])->assertStatus(403);
        $this->delete('/bienestar/director/eliminar', ['data' => 1])->assertStatus(403);

        // 5. 403 en panel de bienestar y portal docente
        $this->get(route('bienestar.panel.index'))->assertStatus(403);
        $this->get(route('bienestar.profesor.index'))->assertStatus(403);
    }
}
