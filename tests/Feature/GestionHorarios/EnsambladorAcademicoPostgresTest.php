<?php

namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Contracts\ProveedorComplementosAcademicos;
use App\Modules\GestionHorarios\Contracts\ProveedorComplementosTransaccional;
use Tests\Support\GestionHorarios\Engine\EjecutorPythonPruebas;
use Tests\Support\GestionHorarios\Engine\EnsambladorAcademicoPrueba;
use Tests\Support\GestionHorarios\Engine\ValidarResultadoFicticio;
use App\Modules\GestionHorarios\Engine\VerificarEvidenciaMotor;
use App\Modules\GestionHorarios\Services\InstantaneaGeneracion;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Tests\Feature\GestionHorarios\soporte\ProveedorComplementosPostgresFicticio;
use Tests\TestCase;

require_once __DIR__.'/soporte/ProveedorComplementosPostgresFicticio.php';

final class EnsambladorAcademicoPostgresTest extends TestCase
{
    public function test_no_se_acepta_una_conexion_ajena_al_entorno_aislado(): void
    {
        $proveedor = new class implements ProveedorComplementosAcademicos
        {
            public function obtener(string $periodo, string $huella): array
            {
                throw new RuntimeException('El proveedor no debe invocarse.');
            }
        };
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('El ensamblador ficticio requiere el entorno aislado de pruebas.');
        (new EnsambladorAcademicoPrueba(DB::connection('sqlite'), $proveedor))->construir([]);
    }

    public function test_registros_persistidos_producen_solicitudes_ficticias_verificadas(): void
    {
        if (getenv('HORARIOS_27B2_POSTGRES_AISLADO') !== '1') {
            $this->markTestSkipped('Requiere PostgreSQL 17 aislado de la etapa 27B.2.');
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
            throw new RuntimeException('Base aislada y vacía no verificada.');
        }
        $sql = file_get_contents(__DIR__.'/soporte/instantanea_27b2.sql');
        $db->unprepared(ltrim($sql, "\xEF\xBB\xBF"));
        $snapshot = new InstantaneaGeneracion($db);
        $proveedor = new ProveedorComplementosPostgresFicticio($db);
        $ensamblador = new EnsambladorAcademicoPrueba($db, $proveedor);
        $motor = new EjecutorPythonPruebas($python);
        $datos = $snapshot->construir(1);
        $registrosAntes = $db->table('gestion_horarios.actividad_academica')->orderBy('id_actividad')->get()->toArray();
        $huellaAlterada = $datos;
        $huellaAlterada['huella_sha256'] = str_repeat('0', 64);
        $rechazada = $ensamblador->construir($huellaAlterada, '1');
        self::assertFalse($rechazada['ejecutable']);
        self::assertSame('INSTANTANEA_INCOMPATIBLE', $rechazada['diagnosticos'][0]['codigo']);
        $preparada = $ensamblador->construir($datos, '1');
        self::assertTrue($preparada['ejecutable'], json_encode($preparada['diagnosticos'], JSON_UNESCAPED_UNICODE));
        self::assertSame('HORARIOS_MOTOR_V1', $preparada['entrada']['contrato']);
        self::assertSame(['10', '20'], $preparada['entrada']['ambito_ficticio']['planes']);
        self::assertSame(['61', '63', '64', '65'], array_column($preparada['entrada']['actividades'], 'id'));
        self::assertSame('F61', $preparada['entrada']['actividades'][0]['sesion_fisica_id']);
        self::assertSame(['111', '112'], $preparada['entrada']['actividades'][0]['secciones_participantes']);
        self::assertSame(0, $db->transactionLevel(), 'La transacción termina antes de ejecutar CP-SAT.');
        $resultado = $motor->ejecutar($preparada['entrada'], 5);
        self::assertSame('OPTIMAL', $resultado['estado'], json_encode($resultado, JSON_UNESCAPED_UNICODE));
        (new VerificarEvidenciaMotor)->validar($preparada['entrada'], $preparada['huella_sha256'], $resultado);
        self::assertTrue((new ValidarResultadoFicticio)->validar($preparada['entrada'], $resultado)['solucion_validada']);
        self::assertCount(1, array_filter($resultado['sesiones'], fn ($s) => ($s['sesion_fisica_id'] ?? null) === 'F61'));
        self::assertEquals($registrosAntes, $db->table('gestion_horarios.actividad_academica')->orderBy('id_actividad')->get()->toArray());
        self::assertSame($preparada['huella_academica_sha256'], $ensamblador->construir($datos, '1')['huella_academica_sha256']);

        $otra = new PDO('pgsql:host=127.0.0.1;port=55471;dbname=horarios_27b2_test', 'horarios_test', $clave, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $escuchando = true;
        $cambio = false;
        DB::listen(function ($consulta) use ($otra, &$escuchando, &$cambio): void {
            if ($escuchando && ! $cambio && str_contains($consulta->sql, 'periodo_academico') && str_starts_with(strtolower(ltrim($consulta->sql)), 'select')) {
                $otra->beginTransaction();
                $otra->exec("UPDATE gestion_horarios.oferta_curso SET estado='PLANIFICADA' WHERE id_oferta=11");
                $otra->exec("UPDATE prueba.meta SET version='2' WHERE periodo='1'");
                $otra->commit();
                $cambio = true;
            }
        });
        try {
            $durante = $ensamblador->construir($datos, '1');
        } finally {
            $escuchando = false;
        }
        self::assertTrue($cambio);
        self::assertTrue($durante['ejecutable'], json_encode($durante['diagnosticos']));
        self::assertSame('1', $durante['procedencia']['version']);
        self::assertSame('2', $db->table('prueba.meta')->value('version'));
        self::assertSame($preparada['huella_academica_sha256'], $durante['huella_academica_sha256']);
        $posterior = $snapshot->construir(1);
        self::assertNotSame($datos['huella_sha256'], $posterior['huella_sha256']);
        self::assertSame('INSTANTANEA_DESACTUALIZADA', $ensamblador->construir($datos, '1')['diagnosticos'][0]['codigo']);
        self::assertSame('VERSION_INCOMPATIBLE', $ensamblador->construir($posterior, '1')['diagnosticos'][0]['codigo']);
        $nueva = $ensamblador->construir($posterior, '2');
        self::assertTrue($nueva['ejecutable']);
        self::assertNotSame($preparada['huella_academica_sha256'], $nueva['huella_academica_sha256']);
        $otra->exec("UPDATE gestion_horarios.oferta_curso SET estado='OFERTADA' WHERE id_oferta=11");
        $otra->exec("UPDATE prueba.meta SET version='1' WHERE periodo='1'");

        $antesDocente = $snapshot->construir(1);
        $otra->exec('UPDATE gestion_horarios.asignacion_docente SET id_docente=42 WHERE id_asignacion=71');
        $despuesDocente = $snapshot->construir(1);
        self::assertNotSame($antesDocente['huella_sha256'], $despuesDocente['huella_sha256']);
        $otra->exec('UPDATE gestion_horarios.asignacion_docente SET id_docente=41 WHERE id_asignacion=71');
        $otra->exec("INSERT INTO gestion_horarios.demanda_instantanea VALUES (1,1,1,repeat('a',64),'2026-01-01')");
        $demanda1 = $snapshot->construir(1);
        $preparacionDemanda1 = $ensamblador->construir($demanda1, '1');
        self::assertTrue($preparacionDemanda1['ejecutable']);
        $otra->exec("INSERT INTO gestion_horarios.demanda_instantanea VALUES (2,1,2,repeat('b',64),'2026-01-02')");
        $demanda2 = $snapshot->construir(1);
        $preparacionDemanda2 = $ensamblador->construir($demanda2, '1');
        self::assertTrue($preparacionDemanda2['ejecutable']);
        self::assertSame(1, (int) $demanda1['datos']['demanda']['version']);
        self::assertSame(2, (int) $demanda2['datos']['demanda']['version']);
        self::assertNotSame($demanda1['huella_sha256'], $demanda2['huella_sha256']);
        self::assertNotSame($preparacionDemanda1['huella_academica_sha256'], $preparacionDemanda2['huella_academica_sha256']);
        self::assertSame('INSTANTANEA_DESACTUALIZADA', $ensamblador->construir($demanda1, '1')['diagnosticos'][0]['codigo']);
        $otra->exec('DELETE FROM gestion_horarios.demanda_instantanea WHERE id_demanda IN (1,2)');

        $complementos = $proveedor->obtener('1', $datos['huella_sha256']);
        $versionAlterada = $complementos;
        $versionAlterada['versiones']['participantes'] = '2';
        $versionFalsa = new class($versionAlterada, $db) implements ProveedorComplementosTransaccional
        {
            public function __construct(private readonly array $datos, private readonly ConnectionInterface $db) {}

            public function conexion(): ConnectionInterface
            {
                return $this->db;
            }

            public function obtener(string $periodo, string $huella): array
            {
                return $this->datos;
            }
        };
        $rechazada = (new EnsambladorAcademicoPrueba($db, $versionFalsa))->construir($datos, '1');
        self::assertFalse($rechazada['ejecutable']);
        self::assertContains('VERSION_INCOMPATIBLE', array_column($rechazada['diagnosticos'], 'codigo'));

        $sinGarantia = new class implements ProveedorComplementosAcademicos
        {
            public function obtener(string $periodo, string $huella): array
            {
                return [];
            }
        };
        self::assertSame('FUENTE_SIN_CONSISTENCIA', (new EnsambladorAcademicoPrueba($db, $sinGarantia))->construir($datos, '1')['diagnosticos'][0]['codigo']);

        $intentoEscritura = new class($db) implements ProveedorComplementosTransaccional
        {
            public function __construct(private readonly ConnectionInterface $db) {}

            public function conexion(): ConnectionInterface
            {
                return $this->db;
            }

            public function obtener(string $periodo, string $huella): array
            {
                $this->db->table('prueba.meta')->update(['version' => 'ALTERADA']);

                return [];
            }
        };
        self::assertSame('FUENTE_NO_DISPONIBLE', (new EnsambladorAcademicoPrueba($db, $intentoEscritura))->construir($datos, '1')['diagnosticos'][0]['codigo']);
        self::assertSame('1', $db->table('prueba.meta')->value('version'));
        self::assertSame(0, $db->transactionLevel());

        $ordenInvertido = array_reverse($complementos, true);
        $ordenInvertido['poblacion'] = array_reverse($ordenInvertido['poblacion'], true);
        $ordenInvertido['disponibilidad'] = array_reverse($ordenInvertido['disponibilidad']);
        $reordenado = new class($ordenInvertido, $db) implements ProveedorComplementosTransaccional
        {
            public function __construct(private readonly array $datos, private readonly ConnectionInterface $db) {}

            public function conexion(): ConnectionInterface
            {
                return $this->db;
            }

            public function obtener(string $periodo, string $huella): array
            {
                return $this->datos;
            }
        };
        self::assertSame($preparada['huella_academica_sha256'], (new EnsambladorAcademicoPrueba($db, $reordenado))->construir($datos, '1')['huella_academica_sha256']);
        $casos = [];
        $x = $complementos;
        unset($x['calendario']);
        $casos[] = $x;
        $x = $complementos;
        unset($x['poblacion']['111']);
        $casos[] = $x;
        $x = $complementos;
        unset($x['sesiones_fisicas'][0]['id']);
        $casos[] = $x;
        $x = $complementos;
        $x['disponibilidad'][0]['franjas'][0]['inicio'] = 8;
        $casos[] = $x;
        $x = $complementos;
        $x['restricciones']['estado'] = 'SIN_VALIDAR';
        $casos[] = $x;
        $x = $complementos;
        $x['escuela_id'] = 'OTRA';
        $casos[] = $x;
        $x = $complementos;
        $x['versiones']['participantes'] = '2';
        $casos[] = $x;
        $x = $complementos;
        $x['consistencia'] = 'DESCONOCIDA';
        $casos[] = $x;
        $x = $complementos;
        $x['particiones']['64'] = '';
        $casos[] = $x;
        $x = $complementos;
        $x['participantes_actividad']['64'][] = 'F6';
        $casos[] = $x;
        $x = $complementos;
        $x['sesiones_fisicas'][0]['participantes'][1]['seccion_id'] = '222';
        $casos[] = $x;
        $x = $complementos;
        $x['patrones']['61'] = [60];
        $casos[] = $x;
        $x = $complementos;
        $x['calendario']['calendario']['dias'] = [];
        $casos[] = $x;
        $x = $complementos;
        unset($x['disponibilidad']);
        $casos[] = $x;
        foreach ($casos as $caso) {
            $falso = new class($caso, $db) implements ProveedorComplementosTransaccional
            {
                public function __construct(private readonly array $datos, private readonly ConnectionInterface $db) {}

                public function conexion(): ConnectionInterface
                {
                    return $this->db;
                }

                public function obtener(string $periodo, string $huella): array
                {
                    return $this->datos;
                }
            };
            $rechazada = (new EnsambladorAcademicoPrueba($db, $falso))->construir($datos, '1');
            self::assertFalse($rechazada['ejecutable']);
            self::assertNull($rechazada['entrada']);
            self::assertNotEmpty($rechazada['diagnosticos']);
        }
        $db->table('gestion_horarios.asignacion_docente')->where('id_asignacion', 71)->delete();
        $sinDocente = $ensamblador->construir($snapshot->construir(1), '1');
        self::assertFalse($sinDocente['ejecutable']);
        self::assertNull($sinDocente['entrada']);
        $db->table('gestion_horarios.asignacion_docente')->insert(['id_asignacion' => 71, 'id_actividad' => 61, 'id_docente' => 41, 'estado' => 'CONFIRMADA', 'id_apertura' => null]);
        $db->table('gestion_horarios.oferta_seccion')->where('id_oferta_seccion', 1)->delete();
        $sinOferta = $ensamblador->construir($snapshot->construir(1), '1');
        self::assertFalse($sinOferta['ejecutable']);
        self::assertNull($sinOferta['entrada']);
        $db->table('gestion_horarios.oferta_seccion')->insert(['id_oferta_seccion' => 1, 'id_oferta' => 11, 'id_seccion' => 111, 'estado' => 'VIGENTE']);
        $db->table('core.ambiente')->where('id_ambiente', 51)->update(['capacidad' => 2]);
        $sinCapacidad = $ensamblador->construir($snapshot->construir(1), '1');
        self::assertFalse($sinCapacidad['ejecutable']);
        self::assertNull($sinCapacidad['entrada']);
        $db->table('core.ambiente')->where('id_ambiente', 51)->update(['capacidad' => 30]);
        $db->table('prueba.fisica_participante')->delete();
        $db->table('prueba.fisica')->delete();
        $db->table('prueba.participante_actividad')->where('id_actividad', 61)->where('estudiante_id', 'F3')->delete();
        $db->table('gestion_horarios.oferta_seccion')->where('id_seccion', 112)->delete();
        $db->table('core.seccion')->where('id_seccion', 112)->delete();
        $ordinaria = $ensamblador->construir($snapshot->construir(1), '1');
        self::assertTrue($ordinaria['ejecutable'], json_encode($ordinaria['diagnosticos'], JSON_UNESCAPED_UNICODE));
        $resultado = $motor->ejecutar($ordinaria['entrada'], 5);
        self::assertSame('OPTIMAL', $resultado['estado']);
        self::assertTrue((new ValidarResultadoFicticio)->validar($ordinaria['entrada'], $resultado)['solucion_validada']);
        self::assertCount(4, $resultado['sesiones']);
    }
}
