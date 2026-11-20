<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Modules\GestionHorarios\Contracts\ProveedorPermisoGestionAcademica;
use App\Modules\GestionHorarios\Repositories\CapturaAcademicaAcotadaRepository;
use App\Modules\GestionHorarios\Services\PreparacionInstitucional;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class PreparacionInstitucionalPostgresTest extends TestCase
{
    public function test_lectura_parcial_autorizada_y_revocacion_en_base_aislada(): void
    {
        if (getenv('HORARIOS_PREPARACION_POSTGRES_AISLADO') !== '1' || ! getenv('HORARIOS_PREPARACION_PASSWORD')) {
            $this->markTestSkipped('Requiere PostgreSQL aislado exclusivo para preparación.');
        }
        config(['database.default' => 'gh_preparacion', 'database.connections.gh_preparacion' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55472, 'database' => 'horarios_preparacion_test', 'username' => 'horarios_test', 'password' => getenv('HORARIOS_PREPARACION_PASSWORD'), 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public']]);
        $db = DB::connection();
        $identidad = $db->selectOne("select current_database() as base, current_user as usuario, current_setting('server_version_num') as version, exists(select 1 from pg_namespace where nspname in ('core','gestion_horarios','prueba')) as ocupada");
        if ($identidad->base !== 'horarios_preparacion_test' || $identidad->usuario !== 'horarios_test' || (int) $identidad->version < 170000 || (int) $identidad->version >= 180000 || $identidad->ocupada) {
            throw new RuntimeException('La base de pruebas no está vacía o no tiene la identidad prevista.');
        }
        $db->unprepared(ltrim(file_get_contents(__DIR__.'/soporte/instantanea_27b2.sql'), "\xEF\xBB\xBF"));
        try {
            $db->transaction(fn () => (new CapturaAcademicaAcotadaRepository($db))->leer(1, 10, [7]));
            self::fail('La captura debe rechazar el aislamiento predeterminado.');
        } catch (\LogicException) {
            self::assertSame(0, $db->transactionLevel());
        }
        $db->table('core.plan_estudios')->insert(['id_plan' => 30, 'id_escuela' => 8]);
        $db->unprepared("INSERT INTO core.curso VALUES (400,'AJENO','Curso de otra escuela',2,0,0); INSERT INTO core.curso_plan VALUES (404,400,30,1); INSERT INTO core.seccion VALUES (444,1,404,'A',25,NULL,'ACTIVA'); INSERT INTO gestion_horarios.oferta_curso VALUES (44,1,404,'REGULAR',1,'OFERTADA'); INSERT INTO gestion_horarios.oferta_seccion VALUES (5,44,444,'VIGENTE')");
        $usuario = new class extends User
        {
            public function hasAnyRole(...$roles): bool
            {
                return true;
            }

            public function can($abilities, $arguments = []): bool
            {
                return $abilities === 'gestion-horarios.view';
            }
        };
        $permiso = new class implements ProveedorPermisoGestionAcademica
        {
            public bool $habilitado = true;

            public int $consultas = 0;

            public int $revocarDesde = PHP_INT_MAX;

            public function puedeGestionar(User $usuario): bool
            {
                return $this->habilitado && ++$this->consultas < $this->revocarDesde;
            }
        };
        app()->instance(ProveedorPermisoGestionAcademica::class, $permiso);
        $escuelas = new class implements ProveedorEscuelasAutorizadas
        {
            public int $consultas = 0;

            public int $revocarDesde = PHP_INT_MAX;

            public function escuelasVigentes(User $usuario): array
            {
                return ++$this->consultas >= $this->revocarDesde ? [] : [7];
            }
        };
        $servicio = new PreparacionInstitucional($db, $escuelas);
        $resultado = $servicio->preparar($usuario, 7, 1, 10);
        self::assertFalse($resultado['ejecutable']);
        self::assertNull($resultado['entrada']);
        self::assertSame(10, (int) $resultado['fuentes_academicas']['plan_id']);
        self::assertNotContains('AJENO', array_column($resultado['fuentes_academicas']['cursos'], 'codigo'));
        self::assertNotContains('444', array_map('strval', array_column($resultado['fuentes_academicas']['secciones'], 'id_seccion')));
        self::assertContains('CAPTURA_COMPLETA_ACOTADA_NO_DISPONIBLE', array_column($resultado['diagnosticos'], 'codigo'));
        self::assertContains('SECCION_SIN_DOCENTE', array_column($resultado['diagnosticos'], 'codigo'));
        self::assertArrayNotHasKey('id_docente', $resultado['fuentes_academicas']['asignaciones'][0]);
        $huellaInicial = $resultado['fuentes_academicas']['captura']['huella_sha256'];
        self::assertNull($resultado['fuentes_academicas']['captura']['fuentes'][0]['version']);
        self::assertSame('POSTGRESQL_MISMA_TRANSACCION_SOLO_PROYECCION_ACADEMICA', $resultado['fuentes_academicas']['captura']['garantia']);
        $otra = new \PDO('pgsql:host=127.0.0.1;port=55472;dbname=horarios_preparacion_test', 'horarios_test', getenv('HORARIOS_PREPARACION_PASSWORD'), [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $escuchar = true;
        $cambio = false;
        DB::listen(function ($consulta) use (&$escuchar, &$cambio, $otra): void {
            if ($escuchar && ! $cambio && str_contains($consulta->sql, 'periodo_academico')) {
                $otra->exec("UPDATE gestion_horarios.oferta_curso SET estado='PLANIFICADA' WHERE id_oferta=11");
                $cambio = true;
            }
        });
        try {
            $durante = $servicio->preparar($usuario, 7, 1, 10);
        } finally {
            $escuchar = false;
        }
        self::assertTrue($cambio);
        self::assertSame('OFERTADA', $durante['fuentes_academicas']['ofertas'][0]['estado']);
        self::assertSame($huellaInicial, $durante['fuentes_academicas']['captura']['huella_sha256']);
        $posterior = $servicio->preparar($usuario, 7, 1, 10);
        self::assertSame('PLANIFICADA', $posterior['fuentes_academicas']['ofertas'][0]['estado']);
        self::assertNotSame($huellaInicial, $posterior['fuentes_academicas']['captura']['huella_sha256']);
        $db->table('gestion_horarios.oferta_seccion')->insert(['id_oferta_seccion' => 6, 'id_oferta' => 11, 'id_seccion' => 444, 'estado' => 'VIGENTE']);
        $inconsistente = $servicio->preparar($usuario, 7, 1, 10);
        self::assertContains('ASOCIACION_OFERTA_SECCION_INCONSISTENTE', array_column($inconsistente['diagnosticos'], 'codigo'));
        self::assertNotSame($posterior['fuentes_academicas']['captura']['huella_sha256'], $inconsistente['fuentes_academicas']['captura']['huella_sha256']);
        self::assertFalse($inconsistente['ejecutable']);
        foreach (['escuela', 'plan_ajeno', 'permiso', 'revocacion', 'revocacion_permiso'] as $caso) {
            $escuelas->consultas = 0;
            $escuelas->revocarDesde = $caso === 'revocacion' ? 4 : PHP_INT_MAX;
            $permiso->habilitado = $caso !== 'permiso';
            $permiso->consultas = 0;
            $permiso->revocarDesde = $caso === 'revocacion_permiso' ? 2 : PHP_INT_MAX;
            try {
                $servicio->preparar($usuario, $caso === 'escuela' ? 8 : 7, 1, $caso === 'plan_ajeno' ? 30 : 10);
                self::fail('Debe denegar '.$caso);
            } catch (AuthorizationException) {
                self::assertTrue(true);
            }
        }
    }
}
