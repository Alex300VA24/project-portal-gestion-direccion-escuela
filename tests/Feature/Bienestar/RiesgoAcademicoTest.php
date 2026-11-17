<?php

namespace Tests\Feature\Bienestar;

use App\Modules\Bienestar\Contracts\FuenteAcademicaInterface;
use App\Modules\Bienestar\Database\Seeders\BienestarDemoSeeder;
use App\Modules\Bienestar\DTO\RegistroAcademicoDTO;
use App\Modules\Bienestar\Models\AlertaRiesgo;
use App\Modules\Bienestar\Services\RiesgoAcademicoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class RiesgoAcademicoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BienestarDemoSeeder::class);
    }

    public function test_inasistencias_edge_cases_19_99_vs_20_and_30_vs_30_01(): void
    {
        $mockFuente = new class implements FuenteAcademicaInterface
        {
            public function obtenerRegistrosPeriodo(string $periodo): array
            {
                return [
                    // Caso 1: 19.99% -> No genera alerta preventiva
                    new RegistroAcademicoDTO('1021400121', $periodo, 'Curso A', 1, 19.99, 16, 50),

                    // Caso 2: 20.00% -> Genera alerta inasistencia_preventiva
                    new RegistroAcademicoDTO('1021400222', $periodo, 'Curso B', 1, 20.00, 16, 50),

                    // Caso 3: 30.00% -> Genera alerta inasistencia_preventiva (no superó 30%)
                    new RegistroAcademicoDTO('1021400323', $periodo, 'Curso C', 1, 30.00, 16, 50),

                    // Caso 4: 30.01% -> Genera alerta inasistencia_inhabilitacion (>30%)
                    new RegistroAcademicoDTO('1021400424', $periodo, 'Curso D', 1, 30.01, 16, 50),
                ];
            }

            public function obtenerEstudiantesDocente(string $codigoDocente, string $periodo): array
            {
                return [];
            }

            public function buscarEstudiantePorCodigo(string $codigoEstudiante): ?object
            {
                return null;
            }
        };

        $service = new RiesgoAcademicoService($mockFuente);
        $periodo = '2026-TEST';
        $service->detectarYActualizarAlertas($periodo);

        // 19.99% -> No alerta
        $this->assertDatabaseMissing('bienestar.alerta_riesgo', [
            'periodo' => $periodo,
            'curso' => 'Curso A',
        ]);

        // 20.00% -> inasistencia_preventiva
        $this->assertDatabaseHas('bienestar.alerta_riesgo', [
            'periodo' => $periodo,
            'curso' => 'Curso B',
            'regla' => 'inasistencia_preventiva',
            'prioridad' => 'media',
        ]);

        // 30.00% -> inasistencia_preventiva
        $this->assertDatabaseHas('bienestar.alerta_riesgo', [
            'periodo' => $periodo,
            'curso' => 'Curso C',
            'regla' => 'inasistencia_preventiva',
            'prioridad' => 'media',
        ]);

        // 30.01% -> inasistencia_inhabilitacion
        $this->assertDatabaseHas('bienestar.alerta_riesgo', [
            'periodo' => $periodo,
            'curso' => 'Curso D',
            'regla' => 'inasistencia_inhabilitacion',
            'prioridad' => 'alta',
        ]);
    }

    public function test_creditos_rule_evaluated_once_globally_and_exempts_egresante(): void
    {
        $mockFuente = new class implements FuenteAcademicaInterface
        {
            public function obtenerRegistrosPeriodo(string $periodo): array
            {
                return [
                    // Estudiante matriculado en 11 créditos (< 12)
                    // Caso 1: 11 créditos, creditosPendientesEgreso = 8 (< 12) -> Exento por ser egresante
                    new RegistroAcademicoDTO('1021400121', $periodo, 'Curso X', 1, 0, 11, 8),
                    new RegistroAcademicoDTO('1021400121', $periodo, 'Curso Y', 1, 0, 11, 8),

                    // Caso 2: 11 créditos, creditosPendientesEgreso = null -> Por verificar
                    new RegistroAcademicoDTO('1021400222', $periodo, 'Curso Z1', 1, 0, 11, null),
                    new RegistroAcademicoDTO('1021400222', $periodo, 'Curso Z2', 1, 0, 11, null),

                    // Caso 3: 11 créditos, creditosPendientesEgreso = 40 (>= 12) -> No egresante, genera alerta
                    new RegistroAcademicoDTO('1021400323', $periodo, 'Curso W1', 1, 0, 11, 40),
                ];
            }

            public function obtenerEstudiantesDocente(string $codigoDocente, string $periodo): array
            {
                return [];
            }

            public function buscarEstudiantePorCodigo(string $codigoEstudiante): ?object
            {
                return null;
            }
        };

        $service = new RiesgoAcademicoService($mockFuente);
        $periodo = '2026-CRED';
        $service->detectarYActualizarAlertas($periodo);

        $idEst1 = DB::table('core.estudiante')->where('codigo_universitario', '1021400121')->value('id_estudiante');

        // Estudiante 1021400121 con 11 créditos y egresante exento -> No alerta de creditos
        $this->assertDatabaseMissing('bienestar.alerta_riesgo', [
            'periodo' => $periodo,
            'curso' => 'GLOBAL',
            'regla' => 'creditos_insuficientes',
            'id_estudiante' => $idEst1,
        ]);

        // Estudiante 1021400222 -> alerta GLOBAL generada exactamente una vez
        $alertasCred222 = AlertaRiesgo::where('periodo', $periodo)
            ->where('regla', 'creditos_insuficientes')
            ->where('curso', 'GLOBAL')
            ->whereHas('estudiante', fn ($q) => $q->where('codigo_universitario', '1021400222'))
            ->get();

        $this->assertCount(1, $alertasCred222);
        $this->assertEquals('por_verificar', $alertasCred222->first()->detalles['estado_egreso']);

        // Estudiante 1021400323 con 11 créditos y no egresante -> alerta GLOBAL generada
        $alertasCred323 = AlertaRiesgo::where('periodo', $periodo)
            ->where('regla', 'creditos_insuficientes')
            ->where('curso', 'GLOBAL')
            ->whereHas('estudiante', fn ($q) => $q->where('codigo_universitario', '1021400323'))
            ->get();

        $this->assertCount(1, $alertasCred323);
        $this->assertEquals('no_egresante', $alertasCred323->first()->detalles['estado_egreso']);
    }

    public function test_alert_reopening_only_from_resuelta_automaticamente_never_atendida_or_descartada(): void
    {
        $periodo = '2026-REOPEN';

        $mockActivo = new class implements FuenteAcademicaInterface
        {
            public function obtenerRegistrosPeriodo(string $periodo): array
            {
                return [
                    new RegistroAcademicoDTO('1021400222', $periodo, 'Estructura de Datos', 3, 0, 16, 50),
                ];
            }

            public function obtenerEstudiantesDocente(string $codigoDocente, string $periodo): array
            {
                return [];
            }

            public function buscarEstudiantePorCodigo(string $codigoEstudiante): ?object
            {
                return null;
            }
        };

        $mockLimpio = new class implements FuenteAcademicaInterface
        {
            public function obtenerRegistrosPeriodo(string $periodo): array
            {
                return [];
            }

            public function obtenerEstudiantesDocente(string $codigoDocente, string $periodo): array
            {
                return [];
            }

            public function buscarEstudiantePorCodigo(string $codigoEstudiante): ?object
            {
                return null;
            }
        };

        $serviceActivo = new RiesgoAcademicoService($mockActivo);
        $serviceLimpio = new RiesgoAcademicoService($mockLimpio);

        // 1. Detección inicial -> crea alerta en 'pendiente'
        $serviceActivo->detectarYActualizarAlertas($periodo);
        $alerta = AlertaRiesgo::where('periodo', $periodo)->where('regla', 'tercera_matricula')->firstOrFail();
        $this->assertEquals('pendiente', $alerta->estado);

        // 2. Condición cesa -> se resuelve automáticamente
        $serviceLimpio->detectarYActualizarAlertas($periodo);
        $alerta->refresh();
        $this->assertEquals('resuelta_automaticamente', $alerta->estado);

        // 3. Condición reaparece -> se reabre a 'pendiente'
        $serviceActivo->detectarYActualizarAlertas($periodo);
        $alerta->refresh();
        $this->assertEquals('pendiente', $alerta->estado);

        // 4. Bienestar atiende la alerta -> estado 'atendida'
        $alerta->update(['estado' => 'atendida']);

        // 5. Corridas posteriores con condición presente -> NUNCA se reabre
        $serviceActivo->detectarYActualizarAlertas($periodo);
        $alerta->refresh();
        $this->assertEquals('atendida', $alerta->estado);

        // 6. Si estuviera 'descartada' -> NUNCA se reabre
        $alerta->update(['estado' => 'descartada']);
        $serviceActivo->detectarYActualizarAlertas($periodo);
        $alerta->refresh();
        $this->assertEquals('descartada', $alerta->estado);
    }

    public function test_manual_alerts_remain_intact_and_are_never_modified_by_detection_service(): void
    {
        $idEstudiante = DB::table('core.estudiante')->value('id_estudiante');

        // Crear alerta manual
        $alertaManual = AlertaRiesgo::create([
            'id_estudiante' => $idEstudiante,
            'periodo' => '2026-I',
            'curso' => 'GLOBAL',
            'regla' => 'alerta_manual',
            'origen' => 'manual_bienestar',
            'prioridad' => 'alta',
            'estado' => 'pendiente',
            'motivo_reporte' => 'Alerta manual registrada por secretaria en atención presencial',
            'descripcion_reporte' => encrypt('Detalle reservado de bienestar'),
            'fecha_deteccion' => now(),
        ]);

        // Simular ejecución del servicio con fuente limpia
        $mockLimpio = new class implements FuenteAcademicaInterface
        {
            public function obtenerRegistrosPeriodo(string $periodo): array
            {
                return [];
            }

            public function obtenerEstudiantesDocente(string $codigoDocente, string $periodo): array
            {
                return [];
            }

            public function buscarEstudiantePorCodigo(string $codigoEstudiante): ?object
            {
                return null;
            }
        };

        $service = new RiesgoAcademicoService($mockLimpio);
        $service->detectarYActualizarAlertas('2026-I');

        $alertaManual->refresh();
        // Permanece en su estado original, no se resuelve automáticamente
        $this->assertEquals('pendiente', $alertaManual->estado);
        $this->assertEquals('manual_bienestar', $alertaManual->origen);
        $this->assertNull($alertaManual->fecha_resolucion);
    }

    public function test_detection_command_is_idempotent(): void
    {
        // Ejecución 1 del comando
        $exitCode1 = Artisan::call('bienestar:detectar-riesgo', ['--periodo' => '2026-I']);
        $this->assertEquals(0, $exitCode1);
        $conteoInicial = AlertaRiesgo::where('periodo', '2026-I')->count();

        // Ejecución 2 del comando (idempotente: mismo estado y conteo)
        $exitCode2 = Artisan::call('bienestar:detectar-riesgo', ['--periodo' => '2026-I']);
        $this->assertEquals(0, $exitCode2);
        $conteoPosterior = AlertaRiesgo::where('periodo', '2026-I')->count();

        $this->assertEquals($conteoInicial, $conteoPosterior);
    }
}
