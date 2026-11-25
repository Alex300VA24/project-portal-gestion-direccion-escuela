<?php

namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Jobs\EjecutarCpsatPrueba;
use App\Modules\GestionHorarios\Services\EjecucionesCpsatPrueba;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class ColaCpsatPostgresTest extends TestCase
{
    private static bool $preparado = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('HORARIOS_COLA_POSTGRES_AISLADO') !== '1') {
            $this->markTestSkipped('Requiere cola PostgreSQL aislada.');
        }
        $password = getenv('HORARIOS_COLA_TEST_PASSWORD');
        if (! is_string($password) || $password === '') {
            throw new RuntimeException('Falta la credencial exclusiva de prueba.');
        }
        config(['database.default' => 'cola_test', 'database.connections.cola_test' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55440, 'database' => 'horarios_cola_test', 'username' => 'horarios_test', 'password' => $password, 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public'], 'queue.connections.database.connection' => null, 'queue.connections.database.retry_after' => 90]);
        $real = DB::selectOne("select current_database() as base, current_user as usuario, current_setting('server_version_num') as version");
        if ($real->base !== 'horarios_cola_test' || $real->usuario !== 'horarios_test' || (int) $real->version < 170000 || (int) $real->version >= 180000) {
            throw new RuntimeException('Base aislada no verificada.');
        }
        if (! self::$preparado) {
            if (DB::selectOne("select exists(select 1 from pg_namespace where nspname = 'gestion_horarios') as existe")->existe) {
                throw new RuntimeException('La base de cola debe estar vacía.');
            }
            DB::statement('CREATE SCHEMA gestion_horarios');
            DB::statement('CREATE TABLE gestion_horarios.horario (id_horario bigint primary key, estado text not null)');
            DB::statement("INSERT INTO gestion_horarios.horario VALUES (1,'APROBADO'),(2,'PUBLICADO')");
            (require base_path('database/migrations/0001_01_01_000002_create_jobs_table.php'))->up();
            (require base_path('app/Modules/GestionHorarios/Database/Migrations/2026_11_17_000004_create_ejecucion_cpsat_prueba.php'))->up();
            self::$preparado = true;
        }
    }

    private function worker(?string $python = null): void
    {
        $python ??= getenv('HORARIOS_PYTHON_AISLADO') ?: '';
        $proceso = new Process([PHP_BINARY, 'artisan', 'queue:work', 'database', '--once', '--queue=gestion-horarios-prueba', '--timeout=45', '--tries=1', '--no-interaction'], base_path(), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '55440', 'DB_DATABASE' => 'horarios_cola_test', 'DB_USERNAME' => 'horarios_test', 'DB_PASSWORD' => getenv('HORARIOS_COLA_TEST_PASSWORD'), 'DB_URL' => '', 'DB_QUEUE_CONNECTION' => 'pgsql', 'DB_QUEUE_RETRY_AFTER' => '90', 'HORARIOS_PYTHON_AISLADO' => $python,
        ]);
        $proceso->setTimeout(60);
        $proceso->run();
        self::assertTrue($proceso->isSuccessful(), 'El worker de prueba no terminó correctamente.');
    }

    public function test_marca_antigua_no_puede_finalizar_intento_simulado_posterior(): void
    {
        $id = (new EjecucionesCpsatPrueba)->encolar('academico_inviable_v1');
        $primero = DB::selectOne("UPDATE gestion_horarios.ejecucion_cpsat_prueba SET estado = 'EJECUTANDO', iniciada_en = clock_timestamp() WHERE id = ? AND estado = 'EN_COLA' RETURNING iniciada_en", [$id]);
        self::assertNotNull($primero);
        $fila = DB::table('gestion_horarios.ejecucion_cpsat_prueba')->where('id', $id)->first();
        // Cambio deliberado de marca solo para probar la condición de escritura, no es recuperación.
        DB::table('gestion_horarios.ejecucion_cpsat_prueba')->where('id', $id)->update(['iniciada_en' => DB::raw("iniciada_en + interval '1 second'")]);
        $afectadas = DB::table('gestion_horarios.ejecucion_cpsat_prueba')->where('id', $id)->where('estado', 'EJECUTANDO')->where('iniciada_en', $primero->iniciada_en)->where('huella', $fila->huella)->where('escenario', $fila->escenario)->update(['estado' => 'FINALIZADO', 'solver' => 'OPTIMAL', 'validada' => true, 'finalizada_en' => now()]);
        self::assertSame(0, $afectadas);
        self::assertSame('EJECUTANDO', DB::table('gestion_horarios.ejecucion_cpsat_prueba')->where('id', $id)->value('estado'));
        DB::table('gestion_horarios.ejecucion_cpsat_prueba')->where('id', $id)->update(['estado' => 'FALLIDO']);
        DB::table('jobs')->where('queue', 'gestion-horarios-prueba')->delete();
    }

    public function test_dos_workers_independientes_procesan_entregas_duplicadas(): void
    {
        if (! is_file(getenv('HORARIOS_PYTHON_AISLADO') ?: '')) {
            $this->markTestSkipped('Se requiere Python aislado con OR-Tools.');
        }
        $id = (new EjecucionesCpsatPrueba)->encolar('academico_inviable_v1');
        Queue::connection('database')->pushOn('gestion-horarios-prueba', new EjecutarCpsatPrueba($id));
        $env = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '55440', 'DB_DATABASE' => 'horarios_cola_test', 'DB_USERNAME' => 'horarios_test', 'DB_PASSWORD' => getenv('HORARIOS_COLA_TEST_PASSWORD'), 'DB_URL' => '', 'DB_QUEUE_CONNECTION' => 'pgsql', 'DB_QUEUE_RETRY_AFTER' => '90', 'HORARIOS_PYTHON_AISLADO' => getenv('HORARIOS_PYTHON_AISLADO'),
        ];
        $comando = [PHP_BINARY, 'artisan', 'queue:work', 'database', '--once', '--queue=gestion-horarios-prueba', '--timeout=45', '--tries=1', '--no-interaction'];
        $primero = new Process($comando, base_path(), $env, null, 60);
        $segundo = new Process($comando, base_path(), $env, null, 60);
        DB::beginTransaction();
        try {
            DB::table('gestion_horarios.ejecucion_cpsat_prueba')->where('id', $id)->lockForUpdate()->first();
            $primero->start();
            $segundo->start();
            $esperaHasta = microtime(true) + 20;
            do {
                DB::select('SELECT pg_stat_clear_snapshot()');
                $bloqueados = DB::selectOne("SELECT count(*) AS total FROM pg_stat_activity WHERE datname = current_database() AND usename = current_user AND wait_event_type = 'Lock' AND query LIKE 'UPDATE gestion_horarios.ejecucion_cpsat_prueba SET estado%'")->total;
                if ((int) $bloqueados >= 2) {
                    break;
                }
                usleep(100000);
            } while (microtime(true) < $esperaHasta);
            self::assertGreaterThanOrEqual(2, (int) $bloqueados, 'Ambos workers deben alcanzar el reclamo bloqueado.');
        } finally {
            DB::rollBack();
        }
        $primero->wait();
        $segundo->wait();
        self::assertTrue($primero->isSuccessful());
        self::assertTrue($segundo->isSuccessful());
        $fila = DB::table('gestion_horarios.ejecucion_cpsat_prueba')->where('id', $id)->first();
        self::assertSame('FINALIZADO', $fila->estado);
        self::assertSame('INFEASIBLE', $fila->solver);
        self::assertNotNull($fila->iniciada_en);
        self::assertNotNull($fila->finalizada_en);
        self::assertSame(0, DB::table('jobs')->where('queue', 'gestion-horarios-prueba')->count());
        $directorioContador = getenv('HORARIOS_CONTADOR_DIRECTORIO');
        if (is_string($directorioContador) && $directorioContador !== '') {
            self::assertCount(1, glob($directorioContador.'/*.inicio') ?: [], 'Solo debe iniciarse una instancia del intérprete real.');
        }
    }

    public function test_worker_real_ejecuta_python_y_no_altera_horarios(): void
    {
        if (! is_file(getenv('HORARIOS_PYTHON_AISLADO') ?: '')) {
            $this->markTestSkipped('Se requiere Python aislado con OR-Tools.');
        }
        $antes = DB::table('gestion_horarios.horario')->orderBy('id_horario')->get()->toArray();
        $servicio = new EjecucionesCpsatPrueba;
        $id = $servicio->encolar('academico_factible_v1');
        self::assertSame('EN_COLA', DB::table('gestion_horarios.ejecucion_cpsat_prueba')->where('id', $id)->value('estado'));
        self::assertSame(1, DB::table('jobs')->where('queue', 'gestion-horarios-prueba')->count());
        $this->worker();
        $resultado = DB::table('gestion_horarios.ejecucion_cpsat_prueba')->where('id', $id)->first();
        self::assertSame('FINALIZADO', $resultado->estado);
        self::assertContains($resultado->solver, ['OPTIMAL', 'FEASIBLE']);
        self::assertTrue((bool) $resultado->validada);
        self::assertEquals($antes, DB::table('gestion_horarios.horario')->orderBy('id_horario')->get()->toArray());
        Queue::connection('database')->pushOn('gestion-horarios-prueba', new EjecutarCpsatPrueba($id));
        $this->worker();
        self::assertEquals($resultado, DB::table('gestion_horarios.ejecucion_cpsat_prueba')->where('id', $id)->first());
    }

    public function test_inviable_es_finalizado_y_cancelacion_previa_no_ejecuta(): void
    {
        if (! is_file(getenv('HORARIOS_PYTHON_AISLADO') ?: '')) {
            $this->markTestSkipped('Se requiere Python aislado con OR-Tools.');
        }
        $servicio = new EjecucionesCpsatPrueba;
        $id = $servicio->encolar('academico_inviable_v1');
        $this->worker();
        $fila = DB::table('gestion_horarios.ejecucion_cpsat_prueba')->where('id', $id)->first();
        self::assertSame('FINALIZADO', $fila->estado);
        self::assertSame('INFEASIBLE', $fila->solver);
        self::assertFalse((bool) $fila->validada);
        $cancelada = $servicio->encolar('academico_inviable_v1');
        self::assertTrue($servicio->cancelarPendiente($cancelada));
        $this->worker();
        self::assertSame('CANCELADO', DB::table('gestion_horarios.ejecucion_cpsat_prueba')->where('id', $cancelada)->value('estado'));
    }

    public function test_python_ausente_y_worker_abandonado_no_se_confunden_con_inviabilidad(): void
    {
        $servicio = new EjecucionesCpsatPrueba;
        $id = $servicio->encolar('academico_factible_v1');
        $this->worker('C:\\ruta-inexistente\\python.exe');
        $fila = DB::table('gestion_horarios.ejecucion_cpsat_prueba')->where('id', $id)->first();
        self::assertSame('FALLIDO', $fila->estado);
        self::assertNull($fila->solver);
        $abandonado = $servicio->encolar('academico_factible_v1');
        DB::table('gestion_horarios.ejecucion_cpsat_prueba')->where('id', $abandonado)->update(['estado' => 'EJECUTANDO', 'iniciada_en' => now()->subMinutes(3)]);
        self::assertContains($abandonado, $servicio->posiblementeAbandonadas());
        self::assertFalse($servicio->cancelarPendiente($abandonado));
        $this->worker();
        self::assertSame('EJECUTANDO', DB::table('gestion_horarios.ejecucion_cpsat_prueba')->where('id', $abandonado)->value('estado'));
    }

    public function test_huella_alterada_falla_sin_ejecutar_motor(): void
    {
        $id = (new EjecucionesCpsatPrueba)->encolar('academico_inviable_v1');
        DB::table('gestion_horarios.ejecucion_cpsat_prueba')->where('id', $id)->update(['huella' => str_repeat('0', 64)]);
        $this->worker();
        $resultado = DB::table('gestion_horarios.ejecucion_cpsat_prueba')->where('id', $id)->first();
        self::assertSame('FALLIDO', $resultado->estado);
        self::assertSame('ENTRADA_O_RESULTADO_INVALIDO', $resultado->error_codigo);
        self::assertNull($resultado->solver);
    }

    public function test_rechaza_escenarios_institucionales_y_duplicados(): void
    {
        $servicio = new EjecucionesCpsatPrueba;
        try {
            $servicio->encolar('2026-I');
            self::fail('Debe rechazar un periodo institucional.');
        } catch (\DomainException) {
            self::assertTrue(true);
        }
        $this->expectException(QueryException::class);
        $servicio->encolar('academico_factible_v1');
    }

    public function test_migracion_se_revierte_solo_en_transaccion_desechable(): void
    {
        $migration = require base_path('app/Modules/GestionHorarios/Database/Migrations/2026_11_17_000004_create_ejecucion_cpsat_prueba.php');
        DB::beginTransaction();
        try {
            $migration->down();
            self::assertFalse(DB::getSchemaBuilder()->hasTable('gestion_horarios.ejecucion_cpsat_prueba'));
        } finally {
            DB::rollBack();
        }
        self::assertTrue(DB::getSchemaBuilder()->hasTable('gestion_horarios.ejecucion_cpsat_prueba'));
    }
}
