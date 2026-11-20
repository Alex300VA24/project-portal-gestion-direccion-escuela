<?php

namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Demand\InstantaneaDemanda;
use App\Modules\GestionHorarios\Demand\ProcesadorInstitucional;
use App\Modules\GestionHorarios\Demand\RepositorioDemandaPostgres;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/** Requiere un servidor temporal aislado, sin esquemas previos; nunca usa la base de .env. */
final class PersistenciaPostgresTest extends TestCase
{
    private static bool $preparado = false;

    private static int $periodo = 10;

    private int $idPeriodo;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('HORARIOS_POSTGRES_AISLADO') !== '1') {
            $this->markTestSkipped('Requiere PostgreSQL aislado expresamente habilitado.');
        }
        $clave = getenv('HORARIOS_PERSISTENCIA_TEST_PASSWORD');

if (! is_string($clave) || $clave === '') {
    throw new RuntimeException('Falta la contraseña de PostgreSQL aislado.');
}

config([
    'database.default' => 'horarios_test',
    'database.connections.horarios_test' => [
        'driver' => 'pgsql',
        'host' => '127.0.0.1',
        'port' => 55439,
        'database' => 'horarios_codex_test',
        'username' => 'horarios_test',
        'password' => $clave,
        'charset' => 'utf8',
        'prefix' => '',
        'search_path' => 'public',
        'sslmode' => 'prefer',
    ],
]);

$db = DB::connection();

$datos = $db->selectOne("
    SELECT
        current_database() AS db,
        current_user AS usuario,
        inet_server_port() AS puerto,
        current_setting('server_version_num') AS version
");

if (
    $datos->db !== 'horarios_codex_test' ||
    $datos->usuario !== 'horarios_test' ||
    (int) $datos->puerto !== 5432 ||
    (int) $datos->version < 170000 ||
    (int) $datos->version >= 180000
) {
    throw new RuntimeException('Conexión de prueba no autorizada.');
}
        if (! self::$preparado) {
            if ($db->selectOne("select exists(select 1 from pg_namespace where nspname in ('core', 'gestion_horarios')) as existe")->existe) {
                throw new RuntimeException('La base de prueba debe estar vacía.');
            }
            $db->unprepared('CREATE SCHEMA core; CREATE SCHEMA seguridad; CREATE SCHEMA gestion_horarios; CREATE TABLE core.periodo_academico (id_periodo BIGINT PRIMARY KEY); CREATE TABLE core.curso_plan (id_curso_plan BIGINT PRIMARY KEY); CREATE TABLE seguridad.usuario (id_usuario BIGINT PRIMARY KEY); INSERT INTO core.curso_plan VALUES (1); INSERT INTO seguridad.usuario VALUES (1)');
            $horario = require base_path('app/Modules/GestionHorarios/Database/Migrations/2026_10_29_000007_create_horario_table.php');
            $horario->up();
            $migration = require base_path('app/Modules/GestionHorarios/Database/Migrations/2026_11_16_000001_create_demanda_academica_tables.php');
            $migration->up();
            self::$preparado = true;
        }
        $this->idPeriodo = ++self::$periodo;
        $db->table('core.periodo_academico')->insert(['id_periodo' => $this->idPeriodo]);
    }

    private function instantanea(int $version, int $total = 5, ?int $periodo = null): InstantaneaDemanda
    {
        return InstantaneaDemanda::desdeArray(['periodo' => $periodo ?? $this->idPeriodo, 'version' => $version, 'cursos' => [['id_curso_plan' => 1, 'total' => $total, 'manana' => 2, 'tarde' => 1]]]);
    }

    public function test_persistencia_versiones_historia_y_proteccion(): void
    {
        $db = DB::connection();
        $db->table('gestion_horarios.horario')->insert([
            ['id_periodo' => $this->idPeriodo, 'version' => 1, 'estado' => 'APROBADO', 'id_creado_por' => 1, 'id_aprobado_por' => 1, 'aprobado_en' => '2026-01-01', 'publicado_en' => null],
            ['id_periodo' => $this->idPeriodo, 'version' => 2, 'estado' => 'PUBLICADO', 'id_creado_por' => 1, 'id_aprobado_por' => 1, 'aprobado_en' => '2026-01-01', 'publicado_en' => '2026-01-02'],
            ['id_periodo' => $this->idPeriodo, 'version' => 3, 'estado' => 'BORRADOR', 'id_creado_por' => 1, 'id_aprobado_por' => null, 'aprobado_en' => null, 'publicado_en' => null],
        ]);
        $antes = $db->table('gestion_horarios.horario')->orderBy('id_horario')->get()->toArray();
        $procesador = new ProcesadorInstitucional($db);
        self::assertSame('aplicada', $procesador->procesar($this->instantanea(1)));
        self::assertSame('duplicada', $procesador->procesar($this->instantanea(1)));
        self::assertSame('aplicada', $procesador->procesar($this->instantanea(2, 6)));
        self::assertSame('antigua', $procesador->procesar($this->instantanea(1)));
        $filas = $db->table('gestion_horarios.demanda_instantanea')->where('id_periodo', $this->idPeriodo)->orderBy('version')->get();
        self::assertCount(2, $filas);
        self::assertTrue((bool) $filas[1]->requiere_revision);
        self::assertNotNull($filas[0]->recibida_en);
        self::assertSame($this->instantanea(1)->hash(), $filas[0]->huella);
        self::assertSame(2, $db->table('gestion_horarios.demanda_curso')->whereIn('id_demanda', [$filas[0]->id_demanda, $filas[1]->id_demanda])->count());
        self::assertEquals($antes, $db->table('gestion_horarios.horario')->orderBy('id_horario')->get()->toArray());
        try {
            $procesador->procesar($this->instantanea(2, 7));
            self::fail('Se esperaba un conflicto de versión.');
        } catch (DomainException) {
            self::assertSame(2, $db->table('gestion_horarios.demanda_instantanea')->where('id_periodo', $this->idPeriodo)->count());
        }
    }

    public function test_catalogo_institucional_rechaza_identidades_desconocidas(): void
    {
        $db = DB::connection();
        $procesador = new ProcesadorInstitucional($db);
        try {
            $procesador->procesar($this->instantanea(1, 5, 999999));
            self::fail('Se esperaba rechazo del periodo.');
        } catch (DomainException) {
            self::assertSame(0, $db->table('gestion_horarios.demanda_instantanea')->where('id_periodo', 999999)->count());
        }
        $datos = ['periodo' => $this->idPeriodo, 'version' => 1, 'cursos' => [['id_curso_plan' => 999999, 'total' => 5, 'manana' => 2, 'tarde' => 1]]];
        $this->expectException(DomainException::class);
        $procesador->procesar(InstantaneaDemanda::desdeArray($datos));
    }

    public function test_horario_no_puede_referenciar_demanda_de_otro_periodo(): void
    {
        $db = DB::connection();
        (new ProcesadorInstitucional($db))->procesar($this->instantanea(1));
        $idDemanda = $db->table('gestion_horarios.demanda_instantanea')->where('id_periodo', $this->idPeriodo)->value('id_demanda');
        $otroPeriodo = $this->idPeriodo + 10000;
        $db->table('core.periodo_academico')->insert(['id_periodo' => $otroPeriodo]);
        $this->expectException(QueryException::class);
        $db->table('gestion_horarios.horario')->insert([
            'id_periodo' => $otroPeriodo, 'id_creado_por' => 1, 'id_demanda' => $idDemanda,
        ]);
    }

    public function test_fallo_revierte_instantanea_y_cursos(): void
    {
        $db = DB::connection();
        $repo = new RepositorioDemandaPostgres($db);
        try {
            $repo->conBloqueo($this->idPeriodo, function () use ($repo): void {
                $repo->guardar($this->instantanea(1));
                throw new RuntimeException('fallo simulado');
            });
            self::fail('Se esperaba el fallo.');
        } catch (RuntimeException $e) {
            self::assertSame('fallo simulado', $e->getMessage());
        }
        self::assertSame(0, $db->table('gestion_horarios.demanda_instantanea')->where('id_periodo', $this->idPeriodo)->count());
        self::assertSame(0, $db->table('gestion_horarios.demanda_curso')->whereNotIn('id_demanda', $db->table('gestion_horarios.demanda_instantanea')->select('id_demanda'))->count());
    }

    public function test_dos_procesos_serializan_actualizaciones(): void
    {
        $db = DB::connection();
        $db->beginTransaction();
        $process = null;
        try {
            $db->select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['gestion_horarios.demanda:'.$this->idPeriodo]);
            $process = proc_open([PHP_BINARY, __DIR__.'/soporte/procesar_demanda.php', (string) $this->idPeriodo], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            $pid = (int) fgets($pipes[1]);
            $bloqueado = false;
            for ($i = 0; $i < 100; $i++) {
                if ($db->selectOne('SELECT cardinality(pg_blocking_pids(?)) AS total', [$pid])->total > 0) {
                    $bloqueado = true;
                    break;
                }
                usleep(50000);
            }
            self::assertTrue($bloqueado, 'El segundo proceso debe esperar el bloqueo PostgreSQL.');
            self::assertSame('aplicada', (new ProcesadorInstitucional($db))->procesar($this->instantanea(1)));
            $db->commit();
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            self::assertSame(0, proc_close($process), $error);
            $process = null;
            self::assertSame('aplicada', trim($output));
            self::assertSame([1, 2], $db->table('gestion_horarios.demanda_instantanea')->where('id_periodo', $this->idPeriodo)->orderBy('version')->pluck('version')->map(fn ($v) => (int) $v)->all());
        } finally {
            if ($db->transactionLevel() > 0) {
                $db->rollBack();
            }
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }
    }
}
