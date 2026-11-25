<?php

namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Contracts\ProveedorComplementosTransaccional;
use App\Modules\GestionHorarios\Engine\EjecutorPythonPruebas;
use App\Modules\GestionHorarios\Engine\EnsambladorAcademicoPrueba;
use App\Modules\GestionHorarios\Engine\ProteccionPropuestaPrueba;
use App\Modules\GestionHorarios\Services\InstantaneaGeneracion;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Feature\GestionHorarios\soporte\ProveedorComplementosPostgresFicticio;
use Tests\TestCase;

require_once __DIR__.'/soporte/ProveedorComplementosPostgresFicticio.php';

final class ProteccionPropuestaPostgresTest extends TestCase
{
    public function test_vigencia_y_bloqueo_cooperativo_en_postgres_aislado(): void
    {
        if (getenv('HORARIOS_27B2_POSTGRES_AISLADO') !== '1') {
            $this->markTestSkipped('Requiere PostgreSQL 17 aislado.');
        }
        $clave = getenv('HORARIOS_27B2_PASSWORD');
        $python = getenv('HORARIOS_PYTHON_AISLADO');
        if (! is_string($clave) || $clave === '' || ! is_string($python) || ! is_file($python)) {
            throw new RuntimeException('Falta la credencial o el intérprete aislado.');
        }
        config(['database.default' => 'gh_27b2', 'database.connections.gh_27b2' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55471, 'database' => 'horarios_27b2_test', 'username' => 'horarios_test', 'password' => $clave, 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public']]);
        $db = DB::connection();
        $id = $db->selectOne("select current_database() as base,current_user as usuario,inet_server_port() as puerto,current_setting('server_version_num') as version");
        if ($id->base !== 'horarios_27b2_test' || $id->usuario !== 'horarios_test' || (int) $id->puerto !== 5432 || (int) $id->version < 170000 || (int) $id->version >= 180000 || $db->selectOne("select exists(select 1 from pg_namespace where nspname in ('core','gestion_horarios','prueba')) as existe")->existe) {
            throw new RuntimeException('Base de pruebas vacía y aislada no verificada.');
        }
        $db->unprepared(ltrim(file_get_contents(__DIR__.'/soporte/instantanea_27b2.sql'), "\xEF\xBB\xBF"));
        $db->unprepared('CREATE TABLE prueba.propuesta_cpsat (id uuid PRIMARY KEY, solicitud_sha256 text NOT NULL UNIQUE, academica_sha256 text NOT NULL, periodo bigint NOT NULL, escuela text NOT NULL, planes jsonb NOT NULL, versiones jsonb NOT NULL, estado_solver text NOT NULL, sesiones jsonb NOT NULL, alcance_validacion text NOT NULL, captura_finalizada_en timestamptz NOT NULL, validada_en timestamptz NOT NULL)');
        $snapshot = new InstantaneaGeneracion($db);
        $proveedor = new ProveedorComplementosPostgresFicticio($db);
        $ensamblador = new EnsambladorAcademicoPrueba($db, $proveedor);
        $proteccion = new ProteccionPropuestaPrueba($db, $proveedor);
        $preparada = $ensamblador->construir($snapshot->construir(1), '1');
        self::assertTrue($preparada['ejecutable']);
        $resultado = (new EjecutorPythonPruebas($python))->ejecutar($preparada['entrada'], $preparada['entrada']['tiempo_limite']);
        self::assertSame('OPTIMAL', $resultado['estado']);
        $otra = new PDO('pgsql:host=127.0.0.1;port=55471;dbname=horarios_27b2_test', 'horarios_test', $clave, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $otra->exec('UPDATE gestion_horarios.asignacion_docente SET id_docente=42 WHERE id_asignacion=71');
        self::assertNotSame('ACEPTADA_PROTOCOLO_FICTICIO', $proteccion->aceptar($preparada, $resultado)['estado']);
        $otra->exec('UPDATE gestion_horarios.asignacion_docente SET id_docente=41 WHERE id_asignacion=71');
        $otra->exec("UPDATE gestion_horarios.oferta_curso SET estado='PLANIFICADA' WHERE id_oferta=11");
        self::assertSame('CAMBIO_DETECTADO', $proteccion->aceptar($preparada, $resultado)['estado']);
        $otra->exec("UPDATE gestion_horarios.oferta_curso SET estado='OFERTADA' WHERE id_oferta=11");
        $otra->exec("UPDATE prueba.meta SET version='2' WHERE periodo='1'");
        self::assertSame('FUENTE_NO_VERIFICABLE', $proteccion->aceptar($preparada, $resultado)['estado']);
        $otra->exec("UPDATE prueba.meta SET version='1' WHERE periodo='1'");
        $externo = new class($db, $proveedor) implements ProveedorComplementosTransaccional
        {
            public function __construct(private ConnectionInterface $db, private ProveedorComplementosTransaccional $base) {}

            public function conexion(): ConnectionInterface
            {
                return $this->db;
            }

            public function obtener(string $periodo, string $huella): array
            {
                $datos = $this->base->obtener($periodo, $huella);
                $datos['consistencia'] = 'EXTERNA_SIN_VERSION_CONFIABLE';

                return $datos;
            }
        };
        self::assertSame('FUENTE_NO_VERIFICABLE', (new ProteccionPropuestaPrueba($db, $externo))->aceptar($preparada, $resultado)['estado']);
        $otra->exec("INSERT INTO gestion_horarios.demanda_instantanea VALUES (1,1,1,repeat('a',64),'2026-01-01')");
        self::assertSame('CAMBIO_DETECTADO', $proteccion->aceptar($preparada, $resultado)['estado']);
        $otra->exec('DELETE FROM gestion_horarios.demanda_instantanea WHERE id_demanda=1');
        $alterado = $resultado;
        $alterado['sesiones'] = [];
        self::assertSame('RESULTADO_INVALIDO', $proteccion->aceptar($preparada, $alterado)['estado']);
        $procedenciaAlterada = $preparada;
        $procedenciaAlterada['procedencia']['instantanea'] = str_repeat('0', 64);
        self::assertSame('CAMBIO_DETECTADO', $proteccion->aceptar($procedenciaAlterada, $resultado)['estado']);
        self::assertSame(0, $db->table('prueba.propuesta_cpsat')->count());

        $observado = false;
        $intentoParalelo = null;
        $activo = true;
        DB::listen(function ($consulta) use ($otra, $preparada, $resultado, &$activo, &$observado, &$intentoParalelo): void {
            if (! $activo || ! str_contains(strtolower($consulta->sql), 'insert into "prueba"."propuesta_cpsat"')) {
                return;
            }
            $hijo = new Process([PHP_BINARY, __DIR__.'/soporte/intento_propuesta_27b4.php'], base_path(), ['APP_ENV' => 'testing']);
            $hijo->setInput(json_encode(compact('preparada', 'resultado'), JSON_THROW_ON_ERROR));
            $hijo->setTimeout(30);
            $hijo->mustRun();
            $intentoParalelo = json_decode($hijo->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            $otra->beginTransaction();
            $otra->exec("SET LOCAL lock_timeout = '200ms'");
            try {
                $otra->exec('SELECT pg_advisory_xact_lock('.ProteccionPropuestaPrueba::PROTOCOLO.', 1)');
            } catch (PDOException $e) {
                $observado = $e->getCode() === '55P03';
            } finally {
                $otra->rollBack();
            }
        });
        try {
            $aceptada = $proteccion->aceptar($preparada, $resultado);
        } finally {
            $activo = false;
        }
        self::assertSame('ACEPTADA_PROTOCOLO_FICTICIO', $aceptada['estado']);
        self::assertSame('PROCESO_EN_CURSO', $intentoParalelo['estado']);
        self::assertTrue($observado, 'Otra conexión intentó el bloqueo después del INSERT y antes de COMMIT.');
        self::assertSame('DUPLICADA', $proteccion->aceptar($preparada, $resultado)['estado']);
        self::assertSame(1, $db->table('prueba.propuesta_cpsat')->count());
        $fila = $db->table('prueba.propuesta_cpsat')->first();
        self::assertSame($preparada['huella_sha256'], $fila->solicitud_sha256);
        self::assertSame($preparada['huella_academica_sha256'], $fila->academica_sha256);
        $otra->beginTransaction();
        $otra->exec('SELECT pg_advisory_xact_lock('.ProteccionPropuestaPrueba::PROTOCOLO.', 1)');
        $otra->exec("UPDATE prueba.meta SET tiempo_limite=4 WHERE periodo='1'");
        $otra->commit();
        $opcion = $ensamblador->construir($snapshot->construir(1), '1');
        self::assertTrue($opcion['ejecutable']);
        self::assertSame($preparada['huella_academica_sha256'], $opcion['huella_academica_sha256']);
        self::assertNotSame($preparada['huella_sha256'], $opcion['huella_sha256']);
        self::assertSame('CAMBIO_DETECTADO', $proteccion->aceptar($preparada, $resultado)['estado']);
        $resultadoOpcion = (new EjecutorPythonPruebas($python))->ejecutar($opcion['entrada'], 4);
        self::assertContains($resultadoOpcion['estado'], ['OPTIMAL', 'FEASIBLE']);
        self::assertSame('ACEPTADA_PROTOCOLO_FICTICIO', $proteccion->aceptar($opcion, $resultadoOpcion)['estado']);
        self::assertSame(2, $db->table('prueba.propuesta_cpsat')->count());
    }
}
