<?php

namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Engine\AdaptadorPreparacion;
use App\Modules\GestionHorarios\Services\InstantaneaGeneracion;
use DomainException;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Tests\TestCase;

final class InstantaneaPostgresTest extends TestCase
{
private static bool $preparado = false;

protected function setUp(): void
{
    parent::setUp();

    // 1. Comprobar que la prueba PostgreSQL esté habilitada.
    if (getenv('HORARIOS_INSTANTANEA_POSTGRES_AISLADO') !== '1') {
        $this->markTestSkipped(
            'Requiere PostgreSQL 17 aislado para instantáneas.'
        );
    }

    // 2. Configurar la conexión PostgreSQL aislada.
    config([
        'database.default' => 'instantanea_test',
        'database.connections.instantanea_test' => [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 55440,
            'database' => 'horarios_instantanea_test',
            'username' => 'horarios_test',
            'password' => 'horarios_test',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],
    ]);

    $db = DB::connection('instantanea_test');

    // 3. Verificar la identidad de la base de datos.
    $id = $db->selectOne("
        SELECT
            current_database() AS db,
            current_user AS usuario,
            current_setting('server_version_num') AS version
    ");

    if (
        $id->db !== 'horarios_instantanea_test' ||
        $id->usuario !== 'horarios_test' ||
        (int) $id->version < 170000 ||
        (int) $id->version >= 180000
    ) {
        throw new RuntimeException(
            'Conexión de pruebas no autorizada.'
        );
    }

    // 4. Preparar los datos ficticios una sola vez.
    if (! self::$preparado) {

        // Comprobar que la base esté vacía.
        $existe = $db->selectOne("
            SELECT EXISTS (
                SELECT 1
                FROM pg_namespace
                WHERE nspname IN (
                    'core',
                    'gestion_horarios',
                    'prueba'
                )
            ) AS existe
        ");

        if ($existe->existe) {
            throw new RuntimeException(
                'La base de instantáneas debe estar vacía.'
            );
        }

        // Leer el fixture ficticio.
        $sql = file_get_contents(
            __DIR__.'/soporte/instantanea_27b2.sql'
        );

        if ($sql === false) {
            throw new RuntimeException(
                'No se encontró el fixture de instantáneas.'
            );
        }

        // Preparar los datos de forma transaccional.
        $db->transaction(function () use ($db, $sql): void {

            // Cargar los datos ficticios.
            $db->unprepared(
                ltrim($sql, "\xEF\xBB\xBF")
            );

            // Ajustar la primera actividad a 60 minutos.
            $db->statement("
                UPDATE gestion_horarios.actividad_academica
                SET
                    hora_fin = '10:00',
                    minutos_sesion = 60
                WHERE id_actividad = 61
            ");

            // Crear una secuencia para la prueba de concurrencia.
            $db->statement("
                CREATE SEQUENCE core.ambiente_instantanea_seq
                START WITH 1000
            ");

            // Permitir la inserción de ambientes sin ID explícito.
            $db->statement("
                ALTER TABLE core.ambiente
                ALTER COLUMN id_ambiente
                SET DEFAULT nextval(
                    'core.ambiente_instantanea_seq'
                )
            ");
        });

        self::$preparado = true;
    }
}

    public function test_instantanea_real_es_determinista_y_no_modifica_horarios(): void
    {
        $db = DB::connection();
        $antes = $db->table('gestion_horarios.horario')->orderBy('id_horario')->get()->toArray();
        $servicio = new InstantaneaGeneracion($db);
        $uno = $servicio->construir(1);
        $dos = $servicio->construir(1);
        self::assertSame($uno, $dos);
        self::assertEquals($antes, $db->table('gestion_horarios.horario')->orderBy('id_horario')->get()->toArray());
        $entrada = (new AdaptadorPreparacion)->convertir($uno);
        self::assertSame('HORARIOS_PREPARACION_V1', $entrada['contrato']);
        self::assertFalse($entrada['listo_para_motor']);
        self::assertNotEmpty($entrada['datos']['actividades']);
        self::assertSame(60, $entrada['datos']['actividades'][0]['duracion_minutos']);
        self::assertStringNotContainsString('99009900', json_encode($entrada, JSON_THROW_ON_ERROR));
    }

    public function test_periodo_inexistente_no_produce_instantanea(): void
    {
        $this->expectException(DomainException::class);
        (new InstantaneaGeneracion(DB::connection()))->construir(9999999);
    }

    public function test_lecturas_concurrentes_no_mezclan_estados(): void
    {
        $pdo = new PDO('pgsql:host=127.0.0.1;port=55440;dbname=horarios_instantanea_test', 'horarios_test', 'horarios_test', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $insertado = false;
        $escuchar = true;
        DB::listen(function ($query) use ($pdo, &$insertado, &$escuchar): void {
            if ($escuchar && ! $insertado && str_contains($query->sql, 'periodo_academico') && str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $pdo->exec("INSERT INTO core.ambiente (codigo, nombre, tipo, capacidad, estado) VALUES ('SNAP-RACE', 'Ambiente de prueba', 'AULA', 12, 'Activo')");
                $insertado = true;
            }
        });
        try {
            $snapshot = (new InstantaneaGeneracion(DB::connection()))->construir(1);
            $escuchar = false;
            self::assertTrue($insertado);
            $nuevoId = $pdo->query("SELECT id_ambiente FROM core.ambiente WHERE codigo = 'SNAP-RACE'")->fetchColumn();
            self::assertNotFalse($nuevoId);
            self::assertNotContains((int) $nuevoId, array_map('intval', array_column($snapshot['datos']['ambientes'], 'id_ambiente')));
            self::assertContains((int) $nuevoId, array_map('intval', array_column((new InstantaneaGeneracion(DB::connection()))->construir(1)['datos']['ambientes'], 'id_ambiente')));
        } finally {
            $escuchar = false;
            $pdo->exec("DELETE FROM core.ambiente WHERE codigo = 'SNAP-RACE'");
        }
    }
}
