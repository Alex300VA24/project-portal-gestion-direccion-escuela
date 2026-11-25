<?php

namespace Tests\Feature\SeguimientoEstudiante;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesPrematriculaAcademicData;
use Tests\TestCase;

final class RecordatoriosTutoriaTest extends TestCase
{
    use CreatesPrematriculaAcademicData;
    use DatabaseTransactions;

    public function test_database_worker_recovers_an_expired_reservation_and_does_not_duplicate_reminders(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL con las migraciones modulares.');
        }

        config(['queue.default' => 'database', 'queue.connections.database.retry_after' => 90]);
        $this->travelTo(Carbon::parse('2026-11-19 08:00', 'America/Lima'));
        $this->createPrematriculaAcademicData();
        $cuenta = User::factory()->create();
        DB::table('seguimiento_estudiante.identidad_cuenta')->insert(['id_usuario' => $cuenta->getKey(), 'id_persona' => 1001]);
        DB::table('core.docente')->insert([
            'id_docente' => 101, 'id_persona' => 1002, 'id_departamento' => 30, 'codigo_docente' => 'D-101', 'estado' => 'Activo',
        ]);
        $asignacion = DB::table('seguimiento_estudiante.tutor_asignacion')->insertGetId([
            'id_estudiante' => 1001, 'id_docente' => 101, 'id_periodo' => 202602, 'fecha_asignacion' => '2026-11-01',
        ], 'id_asignacion');
        $tipo = DB::table('seguimiento_estudiante.tipo_atencion')->insertGetId(['nombre' => 'Tutoría'], 'id_tipo_atencion');
        foreach ([
            ['Programada', '2026-11-20T14:00:00-05:00'],
            ['Programada', '2026-11-21T14:00:00-05:00'],
            ['Cancelada', '2026-11-20T15:00:00-05:00'],
            ['Programada', '2026-11-19T07:00:00-05:00'],
        ] as [$estado, $fecha]) {
            $solicitud = DB::table('seguimiento_estudiante.solicitud_estudiante')->insertGetId([
                'id_estudiante' => 1001, 'id_periodo' => 202602, 'tipo' => 'Tutoria',
                'asunto' => 'Solicitud de tutoría', 'estado' => $estado,
            ], 'id_solicitud');
            DB::table('seguimiento_estudiante.atencion')->insert([
                'id_asignacion' => $asignacion, 'id_tipo_atencion' => $tipo, 'id_solicitud' => $solicitud,
                'fecha_hora' => $fecha, 'duracion_minutos' => 30,
            ]);
        }

        $comando = 'seguimiento-estudiante:recordatorios';
        $worker = ['connection' => 'database', '--queue' => 'seguimiento-estudiante', '--once' => true];
        $this->artisan($comando, ['--days' => 31])->assertExitCode(1);
        $this->artisan($comando)->assertExitCode(0);
        $this->assertDatabaseCount('jobs', 1);
        DB::table('jobs')->where('queue', 'seguimiento-estudiante')
            ->update(['attempts' => 1, 'reserved_at' => now()->timestamp - 91]);
        $this->artisan('queue:work', $worker)->assertExitCode(0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('seguimiento_estudiante.tutoria_aviso', 1);
        $this->assertDatabaseHas('seguimiento_estudiante.tutoria_aviso', [
            'id_usuario' => $cuenta->getKey(),
            'mensaje' => 'Tienes una cita de Tutoría próxima. Consulta la fecha y el ambiente en tu expediente.',
        ]);

        $this->artisan($comando)->assertExitCode(0);
        $this->artisan('queue:work', $worker)->assertExitCode(0);
        $this->assertDatabaseCount('seguimiento_estudiante.tutoria_aviso', 1);

        DB::table('seguimiento_estudiante.atencion')->where('fecha_hora', '2026-11-20T14:00:00-05:00')
            ->update(['fecha_hora' => '2026-11-20T15:00:00-05:00']);
        $this->artisan($comando)->assertExitCode(0);
        $this->artisan('queue:work', $worker)->assertExitCode(0);
        $this->assertDatabaseCount('seguimiento_estudiante.tutoria_aviso', 2);
    }
}
