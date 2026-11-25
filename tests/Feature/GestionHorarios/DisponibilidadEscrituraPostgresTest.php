<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorComplementosTransaccional;
use App\Modules\GestionHorarios\Contracts\ProveedorIdentidadDocente;
use Tests\Support\GestionHorarios\Engine\EjecutorPythonPruebas;
use Tests\Support\GestionHorarios\Engine\EnsambladorAcademicoPrueba;
use Tests\Support\GestionHorarios\Engine\ProteccionPropuestaPrueba;
use Tests\Support\GestionHorarios\Engine\ValidarResultadoFicticio;
use App\Modules\GestionHorarios\Services\InstantaneaGeneracion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Feature\GestionHorarios\soporte\EditorDisponibilidadFicticio;
use Tests\Feature\GestionHorarios\soporte\GestionDisponibilidadFicticia;
use Tests\Feature\GestionHorarios\soporte\ProveedorComplementosPostgresFicticio;
use Tests\TestCase;

require_once __DIR__.'/soporte/GestionDisponibilidadFicticia.php';
require_once __DIR__.'/soporte/EditorDisponibilidadFicticio.php';
require_once __DIR__.'/soporte/ProveedorComplementosPostgresFicticio.php';

final class DisponibilidadEscrituraPostgresTest extends TestCase
{
    public function test_edicion_y_motor_sin_confirmacion_en_entorno_ficticio(): void
    {
        if (getenv('HORARIOS_27B2_POSTGRES_AISLADO') !== '1') {
            $this->markTestSkipped('Requiere PostgreSQL 17 aislado.');
        }
        $clave = getenv('HORARIOS_27B2_PASSWORD');
        $python = getenv('HORARIOS_PYTHON_AISLADO');
        if (! is_string($clave) || $clave === '' || ! is_string($python) || ! is_file($python)) {
            throw new RuntimeException('Falta la credencial o Python aislado.');
        }
        config(['database.default' => 'gh_27b2', 'database.connections.gh_27b2' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55471, 'database' => 'horarios_27b2_test', 'username' => 'horarios_test', 'password' => $clave, 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public']]);
        $db = DB::connection();
        $id = $db->selectOne("select current_database() as base,current_user as usuario,inet_server_port() as puerto,current_setting('server_version_num') as version");
        if ($id->base !== 'horarios_27b2_test' || $id->usuario !== 'horarios_test' || (int) $id->puerto !== 5432 || (int) $id->version < 170000 || (int) $id->version >= 180000 || $db->selectOne("select exists(select 1 from pg_namespace where nspname in ('core','gestion_horarios','prueba')) as existe")->existe) {
            throw new RuntimeException('Base de prueba vacía y aislada no verificada.');
        }
        $db->unprepared(ltrim(file_get_contents(__DIR__.'/soporte/instantanea_27b2.sql'), "\xEF\xBB\xBF"));
        $db->unprepared("ALTER TABLE gestion_horarios.declaracion_disponibilidad ADD COLUMN version int NOT NULL DEFAULT 0, ADD COLUMN preferencia text NOT NULL DEFAULT 'SIN_PREFERENCIA';
            CREATE SEQUENCE prueba.declaracion_seq START 10000; CREATE SEQUENCE prueba.disponibilidad_seq START 10000;
            CREATE TABLE prueba.disp_actor(usuario_id bigint primary key, rol text not null, escuela_id bigint not null);
            CREATE TABLE prueba.disp_periodo(periodo bigint primary key, abierto boolean not null, dias jsonb not null, inicio time not null, fin time not null, receso_inicio time not null, receso_fin time not null, sabado_regular boolean not null, sabado_repeticion boolean not null);
            CREATE TABLE prueba.disp_jornada(periodo bigint not null, dia text not null, bloque bigint not null, UNIQUE(periodo,dia,bloque));
            CREATE TABLE prueba.disp_historial(periodo bigint not null, docente bigint not null, version int not null, actor bigint not null, rol_actor text not null, preferencia text not null, franjas jsonb not null, UNIQUE(periodo,docente,version));
            INSERT INTO prueba.disp_actor VALUES(300,'SECRETARIA',7),(400,'DIRECTOR',8);
            INSERT INTO prueba.disp_periodo VALUES(1,true,'[\"LUNES\",\"MARTES\",\"MIERCOLES\",\"JUEVES\",\"VIERNES\",\"SABADO\"]','07:00','20:00','13:00','14:00',false,true);
            INSERT INTO prueba.disp_jornada VALUES(1,'LUNES',91),(1,'LUNES',93),(1,'LUNES',95);
            UPDATE core.plan_estudios SET id_escuela=8 WHERE id_plan=20;
            DELETE FROM gestion_horarios.disponibilidad_docente WHERE id_docente=41;
            DELETE FROM gestion_horarios.declaracion_disponibilidad WHERE id_docente=41;
            INSERT INTO gestion_horarios.bloque_horario VALUES(101,1,'09:00','10:00'),(102,1,'10:00','11:00'),(103,1,'13:00','14:00'),(104,1,'09:30','10:30')");
        $identidad = new class implements ProveedorIdentidadDocente
        {
            public function docenteId(User $usuario): ?int
            {
                return match ($usuario->id) {
                    100 => 41, 200 => 42, default => null
                };
            }
        };
        $this->app->instance(ProveedorIdentidadDocente::class, $identidad);
        $docente = (new User)->forceFill(['id' => 100, 'name' => 'Docente A ficticio']);
        $otroDocente = (new User)->forceFill(['id' => 200, 'name' => 'Docente B ficticio']);
        $secretaria = (new User)->forceFill(['id' => 300, 'name' => 'Secretaría A ficticia']);
        $otroAdmin = (new User)->forceFill(['id' => 400, 'name' => 'Dirección B ficticia']);
        $servicio = new GestionDisponibilidadFicticia($db, $identidad);
        $seleccion = Livewire::actingAs($docente)->test(EditorDisponibilidadFicticio::class);
        $seleccion->call('alternar', 'LUNES', 91)->assertSet('seleccion', [['dia' => 'LUNES', 'bloque' => 91]])->call('alternar', 'LUNES', 91)->assertSet('seleccion', [])->call('seleccionarJornada', 'LUNES');
        self::assertEqualsCanonicalizing([91, 93, 95], array_column($seleccion->get('seleccion'), 'bloque'));
        self::assertNotContains(103, array_column($seleccion->get('seleccion'), 'bloque'), 'El receso no forma parte de la jornada.');
        $seleccion->call('cancelar')->assertSet('seleccion', [])->assertSee('Cambios descartados');
        self::assertSame([41], $servicio->docentesPendientes($secretaria, 1));
        $editor = Livewire::actingAs($docente)->test(EditorDisponibilidadFicticio::class);
        $editor->call('alternar', 'LUNES', 91)->set('preferencia', 'MANANA')->call('guardar')->assertHasNoErrors()->assertSet('version', 1)->assertSee('Declaración guardada');
        self::assertSame([], $servicio->docentesPendientes($secretaria, 1));
        self::assertNotContains(41, $servicio->docentesPendientes($otroAdmin, 1));
        self::assertFalse(method_exists(EditorDisponibilidadFicticio::class, 'confirmar'));
        Livewire::actingAs($docente)->test(EditorDisponibilidadFicticio::class)->set('version', 0)->set('seleccion', [['dia' => 'LUNES', 'bloque' => 91]])->call('guardar')->assertHasErrors(['version']);
        try {
            $servicio->guardar($otroAdmin, 1, 41, 1, 'MANANA', [['dia' => 'LUNES', 'bloque' => 91]]);
            self::fail('Otra escuela no puede modificar al docente.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }
        $db->table('gestion_horarios.oferta_curso')->where('id_oferta', 22)->update(['tipo_oferta' => 'REPETICION']);
        self::assertSame(1, $servicio->guardar($otroDocente, 1, null, 0, 'TARDE', [['dia' => 'SABADO', 'bloque' => 91]])['version']);
        $db->table('prueba.disp_periodo')->where('periodo', 1)->update(['sabado_repeticion' => false]);
        try {
            $servicio->guardar($otroDocente, 1, null, 1, 'TARDE', [['dia' => 'SABADO', 'bloque' => 91]]);
            self::fail('El sábado de repetición sin autorización debe rechazarse.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }
        $db->table('prueba.disp_periodo')->where('periodo', 1)->update(['sabado_repeticion' => true]);
        $servicio->guardar($otroDocente, 1, null, 1, 'SIN_PREFERENCIA', [['dia' => 'LUNES', 'bloque' => 93]]);
        self::assertSame('DECLARADA', $servicio->guardar($otroAdmin, 1, 42, 2, 'MANANA', [['dia' => 'LUNES', 'bloque' => 93]])['estado']);
        $servicio->guardar($otroAdmin, 1, 42, 3, 'SIN_PREFERENCIA', [['dia' => 'LUNES', 'bloque' => 93]]);
        $db->table('gestion_horarios.oferta_curso')->where('id_oferta', 22)->update(['tipo_oferta' => 'REGULAR']);
        try {
            $servicio->guardar($docente, 1, 42, 0, 'MANANA', []);
            self::fail('El docente no puede modificar otro identificador.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }
        Livewire::actingAs($secretaria)->test(EditorDisponibilidadFicticio::class)->set('docente', 41)->set('version', 1)->set('preferencia', 'MANANA')->set('seleccion', [['dia' => 'LUNES', 'bloque' => 91]])->call('guardar')->assertHasNoErrors()->assertSet('version', 2)->assertSee('Declaración guardada');
        self::assertSame('SECRETARIA', $db->table('prueba.disp_historial')->where('docente', 41)->where('version', 2)->value('rol_actor'));
        $snapshot = new InstantaneaGeneracion($db);
        $antes = $snapshot->construir(1);
        $servicio->guardar($docente, 1, null, 2, 'TARDE', []);
        self::assertSame('DOCENTE', $db->table('prueba.disp_historial')->where('docente', 41)->where('version', 3)->value('rol_actor'));
        self::assertSame(0, $db->table('gestion_horarios.disponibilidad_docente')->where('id_docente', 41)->count());
        self::assertNotSame($antes['huella_sha256'], $snapshot->construir(1)['huella_sha256']);
        foreach ([[['dia' => 'LUNES', 'bloque' => 103]], [['dia' => 'SABADO', 'bloque' => 91]], [['dia' => 'LUNES', 'bloque' => 101], ['dia' => 'LUNES', 'bloque' => 104]]] as $invalida) {
            try {
                $servicio->guardar($docente, 1, null, 3, 'TARDE', $invalida);
                self::fail('Una franja no autorizada debe rechazarse.');
            } catch (ValidationException) {
                self::assertTrue(true);
            }
        }
        $db->table('prueba.disp_periodo')->where('periodo', 1)->update(['abierto' => false]);
        try {
            $servicio->guardar($docente, 1, null, 3, 'MANANA', []);
            self::fail('Periodo cerrado debe rechazar cambios.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }
        $db->table('prueba.disp_periodo')->where('periodo', 1)->update(['abierto' => true]);
        $db->table('gestion_horarios.asignacion_docente')->where('id_asignacion', 71)->update(['estado' => 'RECHAZADA']);
        try {
            $servicio->guardar($docente, 1, null, 3, 'MANANA', []);
            self::fail('Asignación retirada no autoriza cambios.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }
        $db->table('gestion_horarios.asignacion_docente')->where('id_asignacion', 71)->update(['estado' => 'CONFIRMADA']);
        $servicio->guardar($docente, 1, null, 3, 'MANANA', [['dia' => 'LUNES', 'bloque' => 91]]);
        $servicio->guardar($secretaria, 1, 41, 4, 'MANANA', [['dia' => 'LUNES', 'bloque' => 91]]);
        self::assertSame(5, $db->table('prueba.disp_historial')->where('docente', 41)->count());
        $env = ['APP_ENV' => 'testing', 'HORARIOS_27B2_PASSWORD' => $clave];
        $a = new Process([PHP_BINARY, __DIR__.'/soporte/guardar_disponibilidad_27c2.php'], base_path(), $env, json_encode([['dia' => 'LUNES', 'bloque' => 91]], JSON_THROW_ON_ERROR), 30);
        $b = new Process([PHP_BINARY, __DIR__.'/soporte/guardar_disponibilidad_27c2.php'], base_path(), $env, json_encode([['dia' => 'LUNES', 'bloque' => 101], ['dia' => 'LUNES', 'bloque' => 102]], JSON_THROW_ON_ERROR), 30);
        $db->beginTransaction();
        try {
            $db->selectOne('select pg_advisory_xact_lock(?, ?)', [27127, 1]);
            $a->start();
            $b->start();
            $limite = microtime(true) + 20;
            do {
                $db->select('select pg_stat_clear_snapshot()');
                $esperando = (int) $db->selectOne("select count(*) as n from pg_stat_activity where datname=current_database() and usename=current_user and wait_event_type='Lock' and query like 'select pg_advisory_xact_lock%'")->n;
                if ($esperando >= 2) {
                    break;
                }
                usleep(100000);
            } while (microtime(true) < $limite);
            self::assertGreaterThanOrEqual(2, $esperando, 'Ambas ediciones deben competir por el bloqueo.');
        } finally {
            $db->rollBack();
        }
        $a->wait();
        $b->wait();
        self::assertTrue($a->isSuccessful(), $a->getErrorOutput());
        self::assertTrue($b->isSuccessful(), $b->getErrorOutput());
        $resultados = [trim($a->getOutput()), trim($b->getOutput())];
        sort($resultados);
        self::assertSame(['GUARDADA', 'VERSION_OBSOLETA'], $resultados);
        self::assertSame(6, $db->table('gestion_horarios.declaracion_disponibilidad')->where('id_docente', 41)->value('version'));
        $seleccionGanadora = $db->table('gestion_horarios.disponibilidad_docente')->where('id_docente', 41)->orderBy('id_bloque')->get()->map(fn ($f) => ['dia' => $f->dia_semana, 'bloque' => (int) $f->id_bloque])->all();
        $servicio->guardar($secretaria, 1, 41, 6, 'MANANA', $seleccionGanadora);
        self::assertSame(7, $db->table('prueba.disp_historial')->where('docente', 41)->count());
        self::assertCount(7, $servicio->historial($secretaria, 1, 41));
        try {
            $servicio->historial($otroAdmin, 1, 41);
            self::fail('Otra escuela no puede consultar el historial.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }
        // El ensamblador ficticio actual admite una sola escuela; se normaliza solo el fixture antes del solver.
        $db->table('core.plan_estudios')->where('id_plan', 20)->update(['id_escuela' => 7]);
        $base = new ProveedorComplementosPostgresFicticio($db);
        $declaradas = new class($db, $base) implements ProveedorComplementosTransaccional
        {
            public function __construct(private ConnectionInterface $db, private ProveedorComplementosTransaccional $base) {}

            public function conexion(): ConnectionInterface
            {
                return $this->db;
            }

            public function obtener(string $periodo, string $huella): array
            {
                $datos = $this->base->obtener($periodo, $huella);
                foreach ($datos['disponibilidad'] as &$disponibilidad) {
                    $disponibilidad['preferencia'] = $this->db->table('gestion_horarios.declaracion_disponibilidad')->where('id_periodo', $periodo)->where('id_docente', $disponibilidad['docente_id'])->value('preferencia');
                }
                unset($disponibilidad);
                foreach ($this->db->table('gestion_horarios.declaracion_disponibilidad')->where('id_periodo', $periodo)->where('estado', 'PRESENTADA')->get() as $declaracion) {
                    if (! in_array((string) $declaracion->id_docente, array_column($datos['disponibilidad'], 'docente_id'), true)) {
                        $datos['disponibilidad'][] = ['docente_id' => (string) $declaracion->id_docente, 'estado' => 'DECLARADA', 'franjas' => [], 'preferencia' => $declaracion->preferencia];
                    }
                }
                foreach ($this->db->table('core.docente')->pluck('id_docente') as $idDocente) {
                    if (! in_array((string) $idDocente, array_column($datos['disponibilidad'], 'docente_id'), true) && ! $this->db->table('gestion_horarios.declaracion_disponibilidad')->where('id_periodo', $periodo)->where('id_docente', $idDocente)->exists()) {
                        $datos['disponibilidad'][] = ['docente_id' => (string) $idDocente, 'estado' => 'SIN_DECLARAR', 'franjas' => []];
                    }
                }

                return $datos;
            }
        };
        $ensamblador = new EnsambladorAcademicoPrueba($db, $declaradas);
        self::assertFalse($ensamblador->construir($antes, '1')['ejecutable']);
        $servicio->guardar($docente, 1, null, 7, 'MANANA', $seleccionGanadora);
        self::assertTrue($ensamblador->construir($snapshot->construir(1), '1')['ejecutable'], 'Una edición guardada no requiere confirmación.');
        $servicio->guardar($secretaria, 1, 41, 8, 'MANANA', $seleccionGanadora);
        $preparada = $ensamblador->construir($snapshot->construir(1), '1');
        self::assertTrue($preparada['ejecutable'], json_encode($preparada['diagnosticos'], JSON_UNESCAPED_UNICODE));
        $disponibilidadMotor = collect($preparada['entrada']['disponibilidades'])->firstWhere('docente_id', '41');
        self::assertSame('MANANA', $disponibilidadMotor['preferencia']);
        $resultado = (new EjecutorPythonPruebas($python))->ejecutarExacto($preparada['entrada']);
        fwrite(STDERR, "\nDisponibilidad declarada: {$resultado['estado']}\n");
        self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE']);
        self::assertTrue((new ValidarResultadoFicticio)->validar($preparada['entrada'], $resultado)['solucion_validada']);
        $fisica = collect($resultado['sesiones'])->firstWhere('actividad_id', '61');
        self::assertSame('LUNES', $fisica['dia']);
        self::assertSame(9, $fisica['inicio']);
        self::assertSame(11, $fisica['fin']);
        $servicio->guardar($docente, 1, null, 9, 'TARDE', $seleccionGanadora);
        $preferenciaNueva = $ensamblador->construir($snapshot->construir(1), '1');
        self::assertTrue($preferenciaNueva['ejecutable']);
        self::assertNotSame($preparada['huella_academica_sha256'], $preferenciaNueva['huella_academica_sha256']);
        self::assertSame('CAMBIO_DETECTADO', (new ProteccionPropuestaPrueba($db, $declaradas))->aceptar($preparada, $resultado)['estado']);
        $huellaAnterior = $snapshot->construir(1);
        $servicio->guardar($docente, 1, null, 10, 'MANANA', []);
        $servicio->guardar($secretaria, 1, 41, 11, 'MANANA', []);
        self::assertNotSame($huellaAnterior['huella_sha256'], $snapshot->construir(1)['huella_sha256']);
        self::assertFalse($ensamblador->construir($huellaAnterior, '1')['ejecutable']);
        self::assertSame('CAMBIO_DETECTADO', (new ProteccionPropuestaPrueba($db, $declaradas))->aceptar($preparada, $resultado)['estado']);
        $sinVentanas = $ensamblador->construir($snapshot->construir(1), '1');
        self::assertTrue($sinVentanas['ejecutable'], json_encode($sinVentanas['diagnosticos'], JSON_UNESCAPED_UNICODE));
        $inviable = (new EjecutorPythonPruebas($python))->ejecutarExacto($sinVentanas['entrada']);
        fwrite(STDERR, "Disponibilidad declarada vacía: {$inviable['estado']}\n");
        self::assertSame('INFEASIBLE', $inviable['estado']);
        self::assertFalse((new ValidarResultadoFicticio)->validar($sinVentanas['entrada'], $inviable)['solucion_validada']);
        $db->table('gestion_horarios.declaracion_disponibilidad')->where('id_periodo', 1)->where('id_docente', 41)->delete();
        $ausente = $ensamblador->construir($snapshot->construir(1), '1');
        self::assertTrue($ausente['ejecutable'], json_encode($ausente['diagnosticos'], JSON_UNESCAPED_UNICODE));
        self::assertSame('SIN_DECLARAR', collect($ausente['entrada']['disponibilidades'])->firstWhere('docente_id', '41')['estado']);
        $flexible = (new EjecutorPythonPruebas($python))->ejecutarExacto($ausente['entrada']);
        fwrite(STDERR, "Disponibilidad ausente regular: {$flexible['estado']}\n");
        self::assertContains($flexible['estado'], ['OPTIMAL', 'FEASIBLE']);
        self::assertTrue((new ValidarResultadoFicticio)->validar($ausente['entrada'], $flexible)['solucion_validada']);
    }
}
