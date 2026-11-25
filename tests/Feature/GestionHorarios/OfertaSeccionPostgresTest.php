<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Modules\GestionHorarios\Contracts\ProveedorPermisoGestionAcademica;
use App\Modules\GestionHorarios\Services\ConciliacionOfertaSeccion;
use App\Modules\GestionHorarios\Services\GestionOfertaSeccion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PDO;
use PDOException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\GestionHorarios\soporte\ProveedorPermisoGestionFicticio;
use Tests\TestCase;

final class OfertaSeccionPostgresTest extends TestCase
{
    private static bool $preparado = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('HORARIOS_ASOCIACION_POSTGRES_AISLADO') !== '1') {
            $this->markTestSkipped('Requiere PostgreSQL 17 aislado.');
        }
        config(['database.default' => 'asociacion_test', 'database.connections.asociacion_test' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55440, 'database' => 'horarios_asociacion_test', 'username' => 'horarios_test', 'password' => 'horarios_test', 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public']]);
        $db = DB::connection();
        app()->instance(ProveedorPermisoGestionAcademica::class, new ProveedorPermisoGestionFicticio);
        $identidad = $db->selectOne("SELECT current_database() AS db, current_user AS usuario, current_setting('server_version_num') AS version");
        if ($identidad->db !== 'horarios_asociacion_test' || $identidad->usuario !== 'horarios_test' || (int) $identidad->version < 170000 || (int) $identidad->version >= 180000) {
            throw new RuntimeException('Base aislada no verificada.');
        }
        app()->instance(ProveedorEscuelasAutorizadas::class, new class implements ProveedorEscuelasAutorizadas
        {
            public function escuelasVigentes(User $usuario): array
            {
                return [7];
            }
        });
        if (! self::$preparado) {
            if ($db->selectOne("SELECT EXISTS(SELECT 1 FROM pg_namespace WHERE nspname IN ('core','gestion_horarios')) AS existe")->existe) {
                throw new RuntimeException('La base de prueba debe estar vacía.');
            }
            $db->unprepared("CREATE SCHEMA core; CREATE SCHEMA gestion_horarios;
                CREATE TABLE core.plan_estudios (id_plan BIGINT PRIMARY KEY, id_escuela BIGINT NOT NULL);
                CREATE TABLE core.curso_plan (id_curso_plan BIGINT PRIMARY KEY, id_plan BIGINT NOT NULL);
                INSERT INTO core.plan_estudios VALUES (1,7);
                INSERT INTO core.curso_plan VALUES (10,1),(11,1);
                CREATE TABLE core.seccion (id_seccion BIGINT PRIMARY KEY, id_periodo BIGINT, id_curso_plan BIGINT);
                CREATE TABLE gestion_horarios.oferta_curso (id_oferta BIGINT PRIMARY KEY, id_periodo BIGINT, id_curso_plan BIGINT, tipo_oferta TEXT, estado TEXT);
                CREATE TABLE gestion_horarios.actividad_academica (id_actividad BIGINT PRIMARY KEY);
                CREATE TABLE gestion_horarios.detalle_horario (id_detalle BIGINT PRIMARY KEY);
                CREATE TABLE gestion_horarios.horario (id_horario BIGINT PRIMARY KEY, id_periodo BIGINT, estado TEXT);
                INSERT INTO core.seccion VALUES (1,1,10),(2,1,10),(3,2,10),(4,1,11),(5,1,10),(6,1,10);
                INSERT INTO gestion_horarios.oferta_curso VALUES (1,1,10,'REGULAR','OFERTADA'),(2,1,10,'REPETICION','OFERTADA'),(3,2,10,'REGULAR','OFERTADA');");
            (require base_path('app/Modules/GestionHorarios/Database/Migrations/2026_11_17_000003_create_oferta_seccion_table.php'))->up();
            self::$preparado = true;
        }
    }

    public function test_sin_adscripcion_no_permite_escritura(): void
    {
        app()->forgetInstance(ProveedorEscuelasAutorizadas::class);
        try {
            (new GestionOfertaSeccion(DB::connection()))->asociar($this->usuario(), 1, 5, 1, null);
            self::fail('No debe escribirse sin escuela autorizada.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->getStatusCode());
            self::assertSame(0, DB::table('gestion_horarios.oferta_seccion')->where('id_seccion', 5)->count());
        }
    }

    public function test_sin_permiso_o_con_permiso_revocado_no_crea_asociaciones(): void
    {
        app()->forgetInstance(ProveedorPermisoGestionAcademica::class);
        try {
            (new GestionOfertaSeccion(DB::connection()))->asociar($this->usuario(), 1, 5, 1, null);
            self::fail('La escuela autorizada no sustituye el permiso de gestión.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->getStatusCode());
        }
        app()->instance(ProveedorPermisoGestionAcademica::class, new class implements ProveedorPermisoGestionAcademica
        {
            private int $consultas = 0;

            public function puedeGestionar(User $usuario): bool
            {
                return ++$this->consultas === 1;
            }
        });
        try {
            (new GestionOfertaSeccion(DB::connection()))->asociar($this->usuario(), 1, 5, 1, null);
            self::fail('La revocación observada antes de escribir debe denegarse.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->getStatusCode());
        }
        self::assertSame(0, DB::table('gestion_horarios.oferta_seccion')->where('id_seccion', 5)->count());
    }

    public function test_con_permiso_no_modifica_seccion_de_otra_escuela(): void
    {
        $db = DB::connection();
        $db->transaction(function () use ($db): void {
            $db->unprepared("INSERT INTO core.plan_estudios VALUES (2,8); INSERT INTO core.curso_plan VALUES (12,2); INSERT INTO core.seccion VALUES (7,1,12); INSERT INTO gestion_horarios.oferta_curso VALUES (4,1,12,'REGULAR','OFERTADA')");
            try {
                (new GestionOfertaSeccion($db))->asociar($this->usuario(), 1, 7, 4, null);
                self::fail('No debe asociar secciones de otra escuela.');
            } catch (HttpException $e) {
                self::assertSame(403, $e->getStatusCode());
            }
            self::assertSame(0, $db->table('gestion_horarios.oferta_seccion')->where('id_seccion', 7)->count());
        });
    }

    private function usuario(): User
    {
        $usuario = \Mockery::mock(User::class);
        $usuario->shouldReceive('hasAnyRole')->andReturn(true);
        $usuario->shouldReceive('can')->andReturn(true);
        $usuario->shouldReceive('getKey')->andReturn(1);

        return $usuario;
    }

    public function test_asociar_varias_secciones_cambiar_tipo_y_conservar_historial(): void
    {
        $servicio = new GestionOfertaSeccion(DB::connection());
        $servicio->asociar($this->usuario(), 1, 1, 1, null);
        $servicio->asociar($this->usuario(), 1, 2, 1, null);
        $actual = DB::table('gestion_horarios.oferta_seccion')->where('id_seccion', 1)->where('estado', 'VIGENTE')->first();
        $servicio->asociar($this->usuario(), 1, 1, 2, (int) $actual->id_oferta_seccion);
        self::assertSame(2, DB::table('gestion_horarios.oferta_seccion')->where('id_seccion', 1)->count());
        self::assertSame(1, DB::table('gestion_horarios.oferta_seccion')->where('id_seccion', 1)->where('estado', 'VIGENTE')->count());
        self::assertSame(0, DB::table('gestion_horarios.oferta_seccion')->where('id_seccion', 5)->count());
        self::assertSame('REPETICION', DB::table('gestion_horarios.oferta_seccion as os')->join('gestion_horarios.oferta_curso as o', 'o.id_oferta', '=', 'os.id_oferta')->where('os.id_seccion', 1)->where('os.estado', 'VIGENTE')->value('o.tipo_oferta'));
        $this->expectException(ValidationException::class);
        $servicio->asociar($this->usuario(), 1, 1, 1, (int) $actual->id_oferta_seccion);
    }

    public function test_rechaza_periodo_y_curso_incompatibles(): void
    {
        $servicio = new GestionOfertaSeccion(DB::connection());
        foreach ([3, 4] as $seccion) {
            try {
                $servicio->asociar($this->usuario(), 1, $seccion, 1, null);
                self::fail('Debe rechazar una referencia incompatible.');
            } catch (ValidationException) {
                self::assertSame(0, DB::table('gestion_horarios.oferta_seccion')->where('id_seccion', $seccion)->count());
            }
        }
    }

    public function test_protege_horarios_aprobados_y_publicados(): void
    {
        foreach (['APROBADO', 'PUBLICADO'] as $i => $estado) {
            DB::table('gestion_horarios.horario')->insert(['id_horario' => $i + 1, 'id_periodo' => 1, 'estado' => $estado]);
            try {
                (new GestionOfertaSeccion(DB::connection()))->asociar($this->usuario(), 1, 5, 1, null);
                self::fail('Debe proteger el horario.');
            } catch (ValidationException) {
                self::assertSame(0, DB::table('gestion_horarios.oferta_seccion')->where('id_seccion', 5)->count());
            } finally {
                DB::table('gestion_horarios.horario')->where('id_horario', $i + 1)->delete();
            }
        }
    }

    public function test_fallo_al_crear_nueva_asociacion_revierte_el_cierre(): void
    {
        $db = DB::connection();
        $actual = $db->table('gestion_horarios.oferta_seccion')->where('id_seccion', 2)->where('estado', 'VIGENTE')->first();
        $db->statement('ALTER TABLE gestion_horarios.oferta_seccion ADD CONSTRAINT rechazo_prueba CHECK (id_oferta <> 2) NOT VALID');
        try {
            try {
                (new GestionOfertaSeccion($db))->asociar($this->usuario(), 1, 2, 2, (int) $actual->id_oferta_seccion);
                self::fail('El registro nuevo debía fallar.');
            } catch (QueryException) {
                self::assertSame('VIGENTE', $db->table('gestion_horarios.oferta_seccion')->where('id_oferta_seccion', $actual->id_oferta_seccion)->value('estado'));
                self::assertSame(1, $db->table('gestion_horarios.oferta_seccion')->where('id_seccion', 2)->count());
            }
        } finally {
            $db->statement('ALTER TABLE gestion_horarios.oferta_seccion DROP CONSTRAINT rechazo_prueba');
        }
    }

    public function test_indice_unico_y_rollback_en_conexiones_concurrentes(): void
    {
        $pdo1 = new PDO('pgsql:host=127.0.0.1;port=55440;dbname=horarios_asociacion_test', 'horarios_test', 'horarios_test', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo2 = new PDO('pgsql:host=127.0.0.1;port=55440;dbname=horarios_asociacion_test', 'horarios_test', 'horarios_test', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo1->beginTransaction();
        try {
            $pdo1->exec('INSERT INTO gestion_horarios.oferta_seccion(id_oferta,id_seccion,registrada_por) VALUES (1,6,1)');
            $pdo2->exec("SET lock_timeout = '250ms'");
            try {
                $pdo2->exec('INSERT INTO gestion_horarios.oferta_seccion(id_oferta,id_seccion,registrada_por) VALUES (2,6,1)');
                self::fail('La inserción concurrente debió esperar.');
            } catch (PDOException $e) {
                self::assertSame('55P03', $e->getCode());
            }
        } finally {
            $pdo1->rollBack();
        }
        self::assertSame(0, DB::table('gestion_horarios.oferta_seccion')->where('id_seccion', 6)->count());
        $pdo2->exec('INSERT INTO gestion_horarios.oferta_seccion(id_oferta,id_seccion,registrada_por) VALUES (2,6,1)');
        self::assertSame(1, DB::table('gestion_horarios.oferta_seccion')->where('id_seccion', 6)->count());
        try {
            $pdo2->exec('INSERT INTO gestion_horarios.oferta_seccion(id_oferta,id_seccion,registrada_por) VALUES (1,6,1)');
            self::fail('La asociación duplicada debía rechazarse.');
        } catch (PDOException $e) {
            self::assertSame('23505', $e->getCode());
        }
    }

    public function test_conciliacion_detecta_referencias_y_cambios_externos_sin_escribir(): void
    {
        $db = DB::connection();
        $db->beginTransaction();
        try {
            $db->unprepared("INSERT INTO core.seccion VALUES (31,31,10),(32,31,10),(33,31,10),(34,31,10),(35,31,10),(36,31,10);
                INSERT INTO gestion_horarios.oferta_curso VALUES (31,31,10,'REGULAR','OFERTADA'),(32,32,10,'REGULAR','OFERTADA'),(33,31,11,'REPETICION','OFERTADA'),(34,31,10,'REGULAR','OFERTADA');");
            $db->statement('ALTER TABLE gestion_horarios.oferta_seccion DISABLE TRIGGER ALL');
            $db->unprepared('INSERT INTO gestion_horarios.oferta_seccion (id_oferta,id_seccion,registrada_por) VALUES (31,31,1),(32,32,1),(33,33,1),(31,99999,1),(99999,34,1);');
            $db->statement('ALTER TABLE gestion_horarios.oferta_seccion ENABLE TRIGGER ALL');
            $db->table('gestion_horarios.oferta_seccion')->where('id_seccion', 31)->update(['estado' => 'CERRADA', 'cerrada_por' => 1, 'cerrada_en' => $db->raw('clock_timestamp()'), 'motivo_cierre' => 'Prueba']);
            $db->table('gestion_horarios.oferta_seccion')->insert(['id_oferta' => 31, 'id_seccion' => 31, 'registrada_por' => 1]);
            $antes = $db->table('gestion_horarios.oferta_seccion')->count();
            $resultado = (new ConciliacionOfertaSeccion($db))->evaluar(31);
            self::assertTrue($resultado['comprobable']);
            self::assertContains(35, array_map('intval', $resultado['secciones_sin_vigente']));
            self::assertContains(34, array_map('intval', $resultado['ofertas_sin_secciones_validas']));
            $causas = array_merge(...array_column($resultado['asociaciones'], 'causas'));
            foreach (['SECCION_INEXISTENTE', 'OFERTA_INEXISTENTE', 'PERIODO_INCOMPATIBLE', 'CURSO_INCOMPATIBLE'] as $causa) {
                self::assertContains($causa, $causas);
            }
            self::assertContains('CERRADA', array_column($resultado['asociaciones'], 'estado'));
            self::assertCount(1, array_filter($resultado['asociaciones'], fn ($a) => $a['id_seccion'] === '31' && $a['estado'] === 'VIGENTE' && ! $a['causas']));
            $db->table('core.seccion')->where('id_seccion', 31)->update(['id_periodo' => 32]);
            $cambiado = (new ConciliacionOfertaSeccion($db))->evaluar(31);
            self::assertContains('PERIODO_INCOMPATIBLE', array_merge(...array_column($cambiado['asociaciones'], 'causas')));
            $db->table('core.seccion')->where('id_seccion', 31)->update(['id_periodo' => 31]);
            $db->table('core.seccion')->where('id_seccion', 31)->update(['id_curso_plan' => 11]);
            $modificado = (new ConciliacionOfertaSeccion($db))->evaluar(31);
            self::assertContains('CURSO_INCOMPATIBLE', array_merge(...array_column($modificado['asociaciones'], 'causas')));
            $db->table('core.seccion')->where('id_seccion', 31)->delete();
            $eliminado = (new ConciliacionOfertaSeccion($db))->evaluar(31);
            self::assertContains('SECCION_INEXISTENTE', array_merge(...array_column($eliminado['asociaciones'], 'causas')));
            self::assertSame($antes, $db->table('gestion_horarios.oferta_seccion')->count());
        } finally {
            $db->rollBack();
        }
    }
}
