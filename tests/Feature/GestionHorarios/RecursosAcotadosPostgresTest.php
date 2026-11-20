<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorAlcanceRecursosAcademicos;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Modules\GestionHorarios\Services\ConsultaRecursosAcademicosAcotados;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class RecursosAcotadosPostgresTest extends TestCase
{
    public function test_proyeccion_de_docentes_y_ambientes_no_expone_datos_de_otras_escuelas(): void
    {
        if (getenv('HORARIOS_27B2_POSTGRES_AISLADO') !== '1') {
            $this->markTestSkipped('Requiere PostgreSQL 17 aislado.');
        }
        $clave = getenv('HORARIOS_27B2_PASSWORD');
        if (! is_string($clave) || $clave === '') {
            throw new RuntimeException('Falta credencial del entorno de prueba aislado.');
        }
        config(['database.default' => 'gh_27b2', 'database.connections.gh_27b2' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55471, 'database' => 'horarios_27b2_test', 'username' => 'horarios_test', 'password' => $clave, 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public']]);
        $db = DB::connection();
        $identidad = $db->selectOne("select current_database() as base,current_user as usuario,inet_server_port() as puerto,current_setting('server_version_num') as version");
        if ($identidad->base !== 'horarios_27b2_test' || $identidad->usuario !== 'horarios_test' || (int) $identidad->puerto !== 5432 || (int) $identidad->version < 170000 || (int) $identidad->version >= 180000 || $db->selectOne("select exists(select 1 from pg_namespace where nspname in ('core','gestion_horarios','prueba')) as existe")->existe) {
            throw new RuntimeException('La base desechable no coincide con el entorno esperado o contiene esquemas previos.');
        }
        $db->unprepared(ltrim(file_get_contents(__DIR__.'/soporte/instantanea_27b2.sql'), "\xEF\xBB\xBF"));
        $db->unprepared("INSERT INTO core.plan_estudios VALUES (30,8); INSERT INTO core.curso VALUES (400,'CURSO-B-EXTERNO','Curso escuela B',2,0,0); INSERT INTO core.curso_plan VALUES (404,400,30,1); INSERT INTO core.seccion VALUES (444,1,404,'A',25,NULL,'ACTIVA'); INSERT INTO gestion_horarios.oferta_curso VALUES (44,1,404,'REGULAR',1,'OFERTADA'); INSERT INTO gestion_horarios.oferta_seccion VALUES (5,44,444,'VIGENTE'); INSERT INTO core.docente VALUES (45,'ACTIVO',20); INSERT INTO core.ambiente VALUES (53,'A53','Aula B','AULA',30,'ACTIVO'); INSERT INTO gestion_horarios.actividad_academica VALUES (66,444,'TEORIA','LUNES','09:00','11:00',120),(67,444,'PRACTICA','MARTES','09:00','11:00',120); INSERT INTO gestion_horarios.asignacion_docente VALUES (75,66,42,'CONFIRMADA',NULL),(76,67,45,'CONFIRMADA',NULL)");

        $escuelas = new class implements ProveedorEscuelasAutorizadas
        {
            public function escuelasVigentes(User $usuario): array
            {
                return match ((int) $usuario->getKey()) {
                    1 => [7], 2 => [8], default => []
                };
            }
        };
        $recursos = new class implements ProveedorAlcanceRecursosAcademicos
        {
            public function recursosVisibles(User $usuario, int $escuela, int $periodo): array
            {
                $permitidos = match ($escuela) {
                    7 => ['docentes' => [41, 42], 'ambientes' => [51, 52]],
                    8 => ['docentes' => [42, 45], 'ambientes' => [51, 53]],
                    default => ['docentes' => [], 'ambientes' => []]
                };

                return ['escuela_id' => $escuela, 'periodo_id' => $periodo] + $permitidos;
            }
        };
        $usuarioA = $this->usuario(1);
        $usuarioB = $this->usuario(2);
        $servicio = new ConsultaRecursosAcademicosAcotados($db, $escuelas, $recursos);

        $deA = $servicio->consultar($usuarioA, 7, 1, 10);
        self::assertSame('RECURSOS_ACOTADOS_27C13', $deA['contrato']);
        self::assertFalse($deA['ejecutable']);
        self::assertFalse($deA['incluye_disponibilidad_global']);
        self::assertFalse($deA['incluye_ocupacion_global']);
        self::assertSame([71], $deA['asignaciones']->pluck('id_asignacion')->map(fn ($id) => (int) $id)->all());
        self::assertSame(['CURSO-A'], $deA['asignaciones']->pluck('curso_codigo')->all());
        self::assertSame(['id_asignacion', 'id_docente', 'id_actividad', 'tipo_actividad', 'id_seccion', 'seccion', 'curso_codigo', 'curso_nombre'], array_keys((array) $deA['asignaciones']->first()));
        self::assertSame([51, 52], $deA['ambientes']->pluck('id_ambiente')->map(fn ($id) => (int) $id)->all());
        self::assertSame(['id_ambiente', 'codigo', 'nombre', 'tipo', 'capacidad', 'estado'], array_keys((array) $deA['ambientes']->first()));
        self::assertNotContains('Aula B', $deA['ambientes']->pluck('nombre')->all());

        $deAPlan20 = $servicio->consultar($usuarioA, 7, 1, 20);
        self::assertSame([72], $deAPlan20['asignaciones']->pluck('id_asignacion')->map(fn ($id) => (int) $id)->all());
        self::assertSame([42], $deAPlan20['asignaciones']->pluck('id_docente')->map(fn ($id) => (int) $id)->all());

        $deB = $servicio->consultar($usuarioB, 8, 1, 30);
        self::assertSame([75, 76], $deB['asignaciones']->pluck('id_asignacion')->map(fn ($id) => (int) $id)->all());
        self::assertSame([42, 45], $deB['asignaciones']->pluck('id_docente')->map(fn ($id) => (int) $id)->all());
        self::assertSame(['CURSO-B-EXTERNO', 'CURSO-B-EXTERNO'], $deB['asignaciones']->pluck('curso_codigo')->all());
        self::assertSame([51, 53], $deB['ambientes']->pluck('id_ambiente')->map(fn ($id) => (int) $id)->all());
        self::assertNotContains('Aula 52', $deB['ambientes']->pluck('nombre')->all());

        foreach ([
            fn () => $servicio->consultar($usuarioA, 8, 1, 30),
            fn () => $servicio->consultar($usuarioB, 7, 1, 10),
            fn () => $servicio->consultar($usuarioA, 7, 1, 30),
            fn () => $servicio->consultar($usuarioB, 8, 2, 30),
        ] as $consultaProhibida) {
            try {
                $consultaProhibida();
                self::fail('Una consulta sin ámbito autorizado no debe devolver recursos.');
            } catch (AuthorizationException) {
                self::assertTrue(true);
            }
        }

        // Las dos consultas de autorización rodean la lectura real de PostgreSQL.
        foreach (['escuela', 'docente', 'ambiente'] as $revocacion) {
            $escuelasVariables = new class($revocacion) implements ProveedorEscuelasAutorizadas
            {
                private int $consultas = 0;

                public function __construct(private readonly string $revocacion) {}

                public function escuelasVigentes(User $usuario): array
                {
                    return ++$this->consultas > 1 && $this->revocacion === 'escuela' ? [] : [7];
                }
            };
            $recursosVariables = new class($revocacion) implements ProveedorAlcanceRecursosAcademicos
            {
                private int $consultas = 0;

                public function __construct(private readonly string $revocacion) {}

                public function recursosVisibles(User $usuario, int $escuela, int $periodo): array
                {
                    $posterior = ++$this->consultas > 1;

                    return [
                        'escuela_id' => $escuela, 'periodo_id' => $periodo,
                        'docentes' => $posterior && $this->revocacion === 'docente' ? [41] : [41, 42],
                        'ambientes' => $posterior && $this->revocacion === 'ambiente' ? [51] : [51, 52],
                    ];
                }
            };
            try {
                (new ConsultaRecursosAcademicosAcotados($db, $escuelasVariables, $recursosVariables))->consultar($usuarioA, 7, 1, 10);
                self::fail("La revocación de {$revocacion} durante la lectura debe denegarse.");
            } catch (AuthorizationException) {
                self::assertTrue(true);
            }
        }

        $recursosReordenados = new class implements ProveedorAlcanceRecursosAcademicos
        {
            private int $consultas = 0;

            public function recursosVisibles(User $usuario, int $escuela, int $periodo): array
            {
                $segunda = ++$this->consultas > 1;

                return ['escuela_id' => $escuela, 'periodo_id' => $periodo,
                    'docentes' => $segunda ? [42, 41] : [41, 42],
                    'ambientes' => $segunda ? [52, 51] : [51, 52]];
            }
        };
        $reordenados = (new ConsultaRecursosAcademicosAcotados($db, $escuelas, $recursosReordenados))->consultar($usuarioA, 7, 1, 10);
        self::assertSame([71], $reordenados['asignaciones']->pluck('id_asignacion')->map(fn ($id) => (int) $id)->all());
    }

    private function usuario(int $id): User
    {
        return (new class extends User
        {
            public function hasAnyRole(...$roles): bool
            {
                return true;
            }

            public function can($abilities, $arguments = []): bool
            {
                return true;
            }
        })->forceFill(['id' => $id]);
    }
}
