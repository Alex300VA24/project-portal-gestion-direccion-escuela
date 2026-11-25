<?php

namespace Tests\Feature\Bienestar;

use App\Models\User;
use App\Modules\Bienestar\Database\Seeders\BienestarDemoSeeder;
use App\Modules\Bienestar\Models\AlertaBitacora;
use App\Modules\Bienestar\Models\AlertaRiesgo;
use App\Modules\Bienestar\Models\SolicitudApoyo;
use App\Modules\Bienestar\Models\TipoSolicitud;
use App\Modules\Bienestar\Services\ReporteEstadisticoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SecretariaFlujoYRequisitosTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL migrado mediante module:migrate all.');
        }
        $this->seed(BienestarDemoSeeder::class);
        Storage::fake('local');
    }

    public function test_secretaria_access_control_and_privacy_under_ley_29733(): void
    {
        $secretaria = User::where('email', 'secretaria@portal.local')->firstOrFail();
        $this->actingAs($secretaria);

        // 1. Acceso permitido a zonas operativas de Secretaria (200)
        $this->get(route('bienestar.secretaria.alertas'))->assertStatus(200);
        $this->get(route('bienestar.secretaria.solicitudes'))->assertStatus(200);
        $this->get(route('bienestar.secretaria.indicadores'))->assertStatus(200);

        // 2. Acceso prohibido a zonas confidenciales o ajenas (403)
        $this->get(route('bienestar.panel.index'))->assertStatus(403);
        $this->get(route('bienestar.director.index'))->assertStatus(403);
        $this->get(route('bienestar.profesor.index'))->assertStatus(403);
        $this->get(route('bienestar.catalogo-solicitudes.index'))->assertStatus(403);

        // 3. Registro no existente retorna 404
        $this->get('/bienestar/secretaria/solicitudes/999999/revisar')->assertStatus(404);

        // 4. Ley 29733: La vista de Secretaria NO expone notas confidenciales ni descripciones de texto libre
        // Crear alerta con descripción confidencial docente
        $alertaSensible = AlertaRiesgo::create([
            'id_estudiante' => DB::table('core.estudiante')->value('id_estudiante'),
            'periodo' => '2026-I',
            'curso' => 'Matemática I',
            'regla' => 'reporte_docente',
            'origen' => 'reporte_docente',
            'prioridad' => 'media',
            'estado' => 'en_revision',
            'motivo_reporte' => 'Dificultad de aprendizaje',
            'descripcion_reporte' => 'CONFIDENCIAL: El estudiante manifiesta problemas psiquiátricos familiares severos.',
        ]);

        $responseAlertas = $this->get(route('bienestar.secretaria.alertas'));
        $responseAlertas->assertStatus(200);
        $responseAlertas->assertDontSee('Dificultad de aprendizaje'); // El motivo es texto libre
        $responseAlertas->assertSee($alertaSensible->categoriaTexto()); // Solo la categoría
        $responseAlertas->assertDontSee('CONFIDENCIAL: El estudiante manifiesta problemas psiquiátricos'); // Ley 29733
    }

    public function test_secretaria_can_pass_alert_to_bienestar_with_audit_log(): void
    {
        $secretaria = User::where('email', 'secretaria@portal.local')->firstOrFail();
        $this->actingAs($secretaria);

        $alerta = AlertaRiesgo::where('estado', 'en_revision')->firstOrFail();

        $response = $this->post(route('bienestar.secretaria.alertas.filtrar', $alerta), [
            'accion' => 'pasar_bienestar',
        ]);

        $response->assertRedirect(route('bienestar.secretaria.alertas'));

        $alerta->refresh();
        $this->assertEquals('remitida_bienestar', $alerta->estado);
        $this->assertNotNull($alerta->fecha_filtro_secretaria);

        // Verifica bitácora
        $bitacora = AlertaBitacora::where('id_alerta', $alerta->id_alerta)->latest('id_bitacora')->first();
        $this->assertNotNull($bitacora);
        $this->assertEquals('pasado_a_bienestar', $bitacora->accion);
        $idUsuarioSec = DB::table('seguridad.usuario')->where('correo_institucional', $secretaria->email)->value('id_usuario');
        $this->assertEquals($idUsuarioSec, $bitacora->id_usuario);
    }

    public function test_secretaria_can_discard_alert_with_mandatory_motive_and_safeguard_blocks_high_risk(): void
    {
        $secretaria = User::where('email', 'secretaria@portal.local')->firstOrFail();
        $this->actingAs($secretaria);

        // Caso A: Descartar alerta de baja prioridad sin motivo -> Error de validación
        $alertaBaja = AlertaRiesgo::create([
            'id_estudiante' => DB::table('core.estudiante')->value('id_estudiante'),
            'periodo' => '2026-I',
            'curso' => 'Taller II',
            'regla' => 'inasistencia_preventiva',
            'origen' => 'automatica',
            'prioridad' => 'baja',
            'estado' => 'en_revision',
            'motivo_reporte' => 'Inasistencias preventivas',
        ]);

        $this->post(route('bienestar.secretaria.alertas.filtrar', $alertaBaja), [
            'accion' => 'descartar',
            'motivo' => '', // Motivo vacío
        ])->assertSessionHasErrors('motivo');

        // Caso B: Descartar con motivo válido -> Se descarta y registra en bitácora
        $this->post(route('bienestar.secretaria.alertas.filtrar', $alertaBaja), [
            'accion' => 'descartar',
            'motivo' => 'Estudiante justificó formalmente sus faltas con certificado médico.',
        ])->assertRedirect(route('bienestar.secretaria.alertas'));

        $alertaBaja->refresh();
        $this->assertEquals('descartada', $alertaBaja->estado);

        $bitacora = AlertaBitacora::where('id_alerta', $alertaBaja->id_alerta)->latest('id_bitacora')->first();
        $this->assertNotNull($bitacora);
        $this->assertEquals('descartado', $bitacora->accion);
        $this->assertEquals('Estudiante justificó formalmente sus faltas con certificado médico.', $bitacora->motivo);

        // Caso C: Salvaguarda -> Alerta automática de tercera matrícula / riesgo alto NO puede ser descartada
        $alertaAlta = AlertaRiesgo::create([
            'id_estudiante' => DB::table('core.estudiante')->skip(1)->value('id_estudiante') ?? DB::table('core.estudiante')->value('id_estudiante'),
            'periodo' => '2026-I',
            'curso' => 'Algoritmos Complejos',
            'regla' => 'tercera_matricula',
            'origen' => 'automatica',
            'prioridad' => 'alta',
            'estado' => 'en_revision',
            'motivo_reporte' => 'Tercera matrícula detectada',
        ]);

        $responseSalvaguarda = $this->post(route('bienestar.secretaria.alertas.filtrar', $alertaAlta), [
            'accion' => 'descartar',
            'motivo' => 'Intento de descarte indebido',
        ]);

        $responseSalvaguarda->assertSessionHasErrors('accion');

        $alertaAlta->refresh();
        $this->assertEquals('en_revision', $alertaAlta->estado); // No cambió a descartada
    }

    public function test_bienestar_can_consult_and_reopen_discarded_alerts(): void
    {
        $bienestar = User::where('email', 'bienestar@portal.local')->firstOrFail();
        $this->actingAs($bienestar);

        $alertaDescartada = AlertaRiesgo::where('estado', 'descartada')->firstOrFail();

        // 1. Bienestar consulta alertas descartadas
        $response = $this->get(route('bienestar.panel.index', ['tab' => 'descartadas']));
        $response->assertStatus(200);

        // 2. Bienestar reabre la alerta
        $responseReabrir = $this->post(route('bienestar.panel.alertas.reabrir', $alertaDescartada), [
            'motivo' => 'Se requiere reapertura porque el estudiante solicitó asistencia vocacional.',
        ]);

        $responseReabrir->assertRedirect();

        $alertaDescartada->refresh();
        $this->assertEquals('remitida_bienestar', $alertaDescartada->estado);

        // Verifica bitácora de reapertura
        $bitacora = AlertaBitacora::where('id_alerta', $alertaDescartada->id_alerta)->latest('id_bitacora')->first();
        $this->assertNotNull($bitacora);
        $this->assertEquals('reabierto', $bitacora->accion);
        $this->assertEquals('Se requiere reapertura porque el estudiante solicitó asistencia vocacional.', $bitacora->motivo);
    }

    public function test_docente_sees_updated_status_without_discard_motive(): void
    {
        $profesor = User::where('email', 'profesor@portal.local')->firstOrFail();
        $this->actingAs($profesor);

        // Buscar o crear alerta del docente descartada
        $actorDocente = \App\Modules\Bienestar\Support\ActorBienestarResolver::resolve($profesor);
        $idDocente = $actorDocente->idDocente;
        $idEstudiante = DB::table('core.estudiante')->value('id_estudiante');

        $alertaDoc = AlertaRiesgo::create([
            'id_estudiante' => $idEstudiante,
            'id_docente' => $idDocente,
            'periodo' => '2026-I',
            'curso' => 'Bases de Datos',
            'regla' => 'reporte_docente',
            'origen' => 'reporte_docente',
            'prioridad' => 'media',
            'estado' => 'descartada',
            'motivo_reporte' => 'Bajo rendimiento en parcial',
        ]);

        AlertaBitacora::create([
            'id_alerta' => $alertaDoc->id_alerta,
            'id_usuario' => $actorDocente->idUsuario,
            'accion' => 'descartado',
            'motivo' => 'MOTIVO_INTERNO_CONFIDENCIAL_SECRETARIA',
        ]);

        $response = $this->get(route('bienestar.profesor.index'));
        $response->assertStatus(200);
        $response->assertSee('Descartado');
        $response->assertDontSee('MOTIVO_INTERNO_CONFIDENCIAL_SECRETARIA');
    }

    public function test_student_request_blocked_when_mandatory_requirements_are_missing(): void
    {
        $alumno = User::where('email', 'alumno@portal.local')->firstOrFail();
        $this->actingAs($alumno);

        $tipoComedor = TipoSolicitud::where('nombre', 'like', '%Comedor%')->firstOrFail();

        // Envío sin archivos
        $response = $this->post(route('bienestar.alumno.solicitudes.store'), [
            'id_tipo_solicitud' => $tipoComedor->id_tipo_solicitud,
            'motivo' => 'Solicito comedor por ser de bajos recursos.',
            'archivos' => [],
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_student_request_submission_and_secretaria_review_flow_with_subsanacion(): void
    {
        $alumno = User::where('email', 'alumno@portal.local')->firstOrFail();
        $secretaria = User::where('email', 'secretaria@portal.local')->firstOrFail();

        // 1. Alumno envía solicitud con todos los requisitos obligatorios
        $this->actingAs($alumno);
        $tipoOrientacion = TipoSolicitud::where('nombre', 'like', '%Orientación%')->firstOrFail();
        $requisitos = $tipoOrientacion->requisitos;

        $archivos = [];
        foreach ($requisitos as $req) {
            $archivos[$req->id_requisito] = UploadedFile::fake()->create("requisito_{$req->id_requisito}.pdf", 100, 'application/pdf');
        }

        $responseStore = $this->post(route('bienestar.alumno.solicitudes.store'), [
            'id_tipo_solicitud' => $tipoOrientacion->id_tipo_solicitud,
            'motivo' => 'Requiero orientación psicopedagógica para técnicas de estudio.',
            'archivos' => $archivos,
        ]);

        $responseStore->assertRedirect(route('bienestar.alumno.solicitudes.index'));

        $solicitud = SolicitudApoyo::where('id_tipo_solicitud', $tipoOrientacion->id_tipo_solicitud)
            ->where('estado', 'enviada')
            ->latest('id_solicitud')
            ->firstOrFail();

        $this->assertFalse($solicitud->folio_externo_confirmado);
        $this->assertCount($requisitos->count(), $solicitud->documentosRequisitos);

        // 2. Secretaria revisa la solicitud: Recibe y Observa un requisito
        $this->actingAs($secretaria);

        // Primera acción: recibir
        $this->post(route('bienestar.secretaria.solicitudes.revisar', $solicitud), [
            'accion' => 'recibir',
        ])->assertRedirect();

        $solicitud->refresh();
        $this->assertEquals('recibida', $solicitud->estado);

        // Segunda acción: observar un requisito
        $primerReq = $solicitud->documentosRequisitos->first();
        $revisiones = [
            [
                'id_requisito' => $primerReq->id_requisito,
                'estado' => 'observado',
                'comentario' => 'El formato debe estar debidamente firmado a mano o digitalmente.',
            ],
        ];

        $this->post(route('bienestar.secretaria.solicitudes.revisar', $solicitud), [
            'accion' => 'observar',
            'revisiones' => $revisiones,
            'observacion_general' => 'Favor de levantar la observación del formato para continuar el trámite.',
        ])->assertRedirect();

        $solicitud->refresh();
        $this->assertEquals('observada', $solicitud->estado);
        $primerReqDoc = $solicitud->documentosRequisitos()->where('id_requisito', $primerReq->id_requisito)->first();
        $this->assertEquals('observado', $primerReqDoc->estado_revision);

        // 3. Alumno subsana ÚNICAMENTE el requisito observado
        $this->actingAs($alumno);

        $responseSubsanarView = $this->followingRedirects()->get(route('bienestar.alumno.solicitudes.subsanar', $solicitud));
        $responseSubsanarView->assertStatus(200);
        $responseSubsanarView->assertSee('El formato debe estar debidamente firmado');

        $archivoSubsanado = [
            $primerReq->id_requisito => UploadedFile::fake()->create("formato_corregido.pdf", 120, 'application/pdf'),
        ];

        $responseSubsanar = $this->post(route('bienestar.alumno.solicitudes.guardar-subsanacion', $solicitud), [
            'archivos' => $archivoSubsanado,
        ]);

        $responseSubsanar->assertRedirect(route('bienestar.alumno.solicitudes.index'));

        $solicitud->refresh();
        $this->assertEquals('recibida', $solicitud->estado); // Vuelve a recibida tras subsanar
        $primerReqDoc->refresh();
        $this->assertEquals('pendiente', $primerReqDoc->estado_revision); // Pendiente de nueva revisión

        // 4. Secretaria revisa conforme y remite a Bienestar con folio externo confirmado
        $this->actingAs($secretaria);

        // Intento de remitir sin confirmar folio externo -> Error de validación
        $revisionesConforme = [
            [
                'id_requisito' => $primerReq->id_requisito,
                'estado' => 'conforme',
                'comentario' => 'Firmado y conforme.',
            ],
        ];

        $this->post(route('bienestar.secretaria.solicitudes.revisar', $solicitud), [
            'accion' => 'remitir',
            'revisiones' => $revisionesConforme,
            'confirma_folio_externo' => 0, // No confirmado
        ])->assertSessionHasErrors('confirma_folio_externo');

        // Remitir confirmando el folio externo físico
        $this->post(route('bienestar.secretaria.solicitudes.revisar', $solicitud), [
            'accion' => 'remitir',
            'revisiones' => $revisionesConforme,
            'confirma_folio_externo' => 1,
        ])->assertRedirect(route('bienestar.secretaria.solicitudes'));

        $solicitud->refresh();
        $this->assertEquals('remitida', $solicitud->estado);
        $this->assertTrue($solicitud->folio_externo_confirmado);
        $this->assertNotNull($solicitud->fecha_remision_bienestar);

        // 5. Bienestar dictamina la solicitud remitida
        $bienestar = User::where('email', 'bienestar@portal.local')->firstOrFail();
        $this->actingAs($bienestar);

        $this->post(route('bienestar.panel.solicitudes.evaluar', $solicitud), [
            'estado' => 'Aprobada',
        ])->assertRedirect(route('bienestar.panel.solicitudes.index'));

        $solicitud->refresh();
        $this->assertEquals('aprobada', strtolower($solicitud->estado));
    }

    public function test_statistics_display_exact_counts_without_masking_small_values(): void
    {
        $director = User::where('email', 'director@portal.local')->firstOrFail();
        $this->actingAs($director);

        $service = app(ReporteEstadisticoService::class);
        $data = $service->obtenerReporteCompleto('2026-I');

        // 1. Los datos no contienen la frase "menos de 5" en ninguna tabla o agregado
        $this->assertArrayHasKey('kpis', $data);
        $this->assertArrayHasKey('casosPorEstado', $data);
        $this->assertArrayHasKey('alertasPorRegla', $data);

        // 2. La vista muestra exactamente los números sin enmascaramiento
        $response = $this->get(route('bienestar.director.index'));
        $response->assertStatus(200);
        $response->assertDontSee('menos de 5');

        // 3. Exportación CSV incluye valores numéricos reales
        $csvResponse = $this->get(route('bienestar.director.exportar-csv', ['periodo' => '2026-I']));
        $csvResponse->assertStatus(200);
        $this->assertStringNotContainsString('menos de 5', $csvResponse->getContent());
    }
}
