<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Modules\GestionHorarios\Services\AmbitoPlanes;
use App\Shared\Models\Core\CursoPlan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\GestionHorarios\soporte\ProveedorEscuelasFicticio;
use Tests\TestCase;

require_once __DIR__.'/soporte/ProveedorEscuelasFicticio.php';

final class PilotoEscuelasPostgresTest extends TestCase
{
    public function test_catalogo_ficticio_filtra_dos_planes_y_respeta_revocacion(): void
    {
        if (getenv('HORARIOS_PILOTO_POSTGRES_AISLADO') !== '1') {
            $this->markTestSkipped('Requiere la base PostgreSQL aislada del piloto.');
        }
        $password = getenv('HORARIOS_COLA_TEST_PASSWORD');
        if (! is_string($password) || $password === '') {
            throw new RuntimeException('Falta la credencial de prueba.');
        }
        config(['database.default' => 'piloto_test', 'database.connections.piloto_test' => [
            'driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55440, 'database' => 'horarios_piloto_test',
            'username' => 'horarios_test', 'password' => $password, 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public',
        ]]);
        $db = DB::connection();
        $identidad = $db->selectOne("select current_database() as base, current_user as usuario, inet_server_port() as puerto, current_setting('server_version_num') as version");
        if ($identidad->base !== 'horarios_piloto_test' || $identidad->usuario !== 'horarios_test' || (int) $identidad->puerto !== 5432 || (int) $identidad->version < 170000 || (int) $identidad->version >= 180000) {
            throw new RuntimeException('Identidad de base aislada no verificada.');
        }
        if ($db->selectOne("select exists(select 1 from pg_namespace where nspname = 'core') as existe")->existe) {
            throw new RuntimeException('La base ficticia debe estar vacía.');
        }
        $db->beginTransaction();
        try {
            $db->unprepared('CREATE SCHEMA core; CREATE TABLE core.plan_estudios (id_plan bigint primary key, id_escuela bigint not null, codigo text, nombre text); CREATE TABLE core.curso_plan (id_curso_plan bigint primary key, id_plan bigint not null, ciclo integer);');
            $db->table('core.plan_estudios')->insert([
                ['id_plan' => 101, 'id_escuela' => 10, 'codigo' => 'A', 'nombre' => 'Plan ficticio A'],
                ['id_plan' => 102, 'id_escuela' => 10, 'codigo' => 'B', 'nombre' => 'Plan ficticio B'],
                ['id_plan' => 201, 'id_escuela' => 20, 'codigo' => 'C', 'nombre' => 'Plan ficticio C'],
            ]);
            $db->table('core.curso_plan')->insert([
                ['id_curso_plan' => 1, 'id_plan' => 101, 'ciclo' => 1],
                ['id_curso_plan' => 2, 'id_plan' => 101, 'ciclo' => 3],
                ['id_curso_plan' => 3, 'id_plan' => 102, 'ciclo' => 2],
                ['id_curso_plan' => 4, 'id_plan' => 201, 'ciclo' => 9],
            ]);
            $proveedor = new ProveedorEscuelasFicticio([1 => [10], 2 => [10], 3 => [20], 5 => [10]]);
            $this->app->instance(ProveedorEscuelasAutorizadas::class, $proveedor);
            $ambito = $this->app->make(AmbitoPlanes::class);
            $planes = static function (int $id) use ($ambito): array {
                $usuario = new User;
                $usuario->id = $id;

                return $ambito->consultar($usuario)->orderBy('id_plan')->pluck('id_plan')->map(fn ($n) => (int) $n)->all();
            };
            self::assertSame([101, 102], $planes(1)); // Director ficticio.
            self::assertSame([101, 102], $planes(2)); // Secretaría ficticia.
            self::assertSame([201], $planes(3));
            self::assertSame([], $planes(4));
            self::assertSame([101, 102], $planes(5));
            $proveedor->revocar(5);
            self::assertSame([], $planes(5));
            self::assertSame([1, 3], CursoPlan::query()->where('id_plan', 101)->orderBy('ciclo')->pluck('ciclo')->map(fn ($n) => (int) $n)->all());
            self::assertSame([2], CursoPlan::query()->where('id_plan', 102)->orderBy('ciclo')->pluck('ciclo')->map(fn ($n) => (int) $n)->all());
            self::assertNotContains(101, $planes(3));
            $otro = new User;
            $otro->id = 3;
self::assertFalse($ambito->contiene($otro, 101));
self::assertTrue($ambito->contiene($otro, 201));

// Simular ausencia del contrato institucional de adscripción.
$this->app->forgetInstance(ProveedorEscuelasAutorizadas::class);

// Sin proveedor, el acceso debe permanecer denegado.
self::assertFalse((new AmbitoPlanes)->contiene($otro, 201));
        } finally {
            $db->rollBack();
        }
    }
}
