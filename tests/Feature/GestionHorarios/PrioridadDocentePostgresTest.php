<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Engine\EjecutorPythonPruebas;
use App\Modules\GestionHorarios\Engine\EnsambladorAcademicoPrueba;
use App\Modules\GestionHorarios\Engine\ProteccionPropuestaPrueba;
use App\Modules\GestionHorarios\Engine\ValidarResultadoFicticio;
use App\Modules\GestionHorarios\Services\InstantaneaGeneracion;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Feature\GestionHorarios\soporte\EditorPrioridadFicticio;
use Tests\Feature\GestionHorarios\soporte\PrioridadDocenteFicticia;
use Tests\Feature\GestionHorarios\soporte\ProveedorComplementosPostgresFicticio;
use Tests\Feature\GestionHorarios\soporte\ProveedorPrioridadFicticio;
use Tests\TestCase;

require_once __DIR__.'/soporte/PrioridadDocenteFicticia.php';
require_once __DIR__.'/soporte/EditorPrioridadFicticio.php';
require_once __DIR__.'/soporte/ProveedorComplementosPostgresFicticio.php';
require_once __DIR__.'/soporte/ProveedorPrioridadFicticio.php';

final class PrioridadDocentePostgresTest extends TestCase
{
    public function test_edicion_concurrencia_y_captura_versionada_en_entorno_aislado(): void
    {
        if (getenv('HORARIOS_27B2_POSTGRES_AISLADO') !== '1') {
            $this->markTestSkipped('Requiere PostgreSQL aislado.');
        }
        $clave = getenv('HORARIOS_27B2_PASSWORD');
        $python = getenv('HORARIOS_PYTHON_AISLADO');
        if (! is_string($clave) || $clave === '' || ! is_string($python) || ! is_file($python)) {
            throw new RuntimeException('Falta la credencial o Python aislado.');
        }
        config(['database.default' => 'gh_27b2', 'database.connections.gh_27b2' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55471, 'database' => 'horarios_27b2_test', 'username' => 'horarios_test', 'password' => $clave, 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public']]);
        $db = DB::connection();
        $identidad = $db->selectOne("select current_database() as base,current_user as usuario,inet_server_port() as puerto,current_setting('server_version_num') as version");
        if ($identidad->base !== 'horarios_27b2_test' || $identidad->usuario !== 'horarios_test' || (int) $identidad->puerto !== 5432 || (int) $identidad->version < 170000 || (int) $identidad->version >= 180000 || $db->selectOne("select exists(select 1 from pg_namespace where nspname in ('core','gestion_horarios','prueba')) as existe")->existe) {
            throw new RuntimeException('Base aislada vacía no verificada.');
        }
        $db->unprepared(ltrim(file_get_contents(__DIR__.'/soporte/instantanea_27b2.sql'), "\xEF\xBB\xBF"));
        $db->unprepared("CREATE TABLE prueba.prioridad_actor(usuario_id bigint primary key, escuela bigint, rol text, activo boolean); CREATE TABLE prueba.prioridad_config(escuela bigint primary key, version integer NOT NULL); CREATE TABLE prueba.prioridad_catalogo(escuela bigint, docente bigint, nombre text, activo boolean, PRIMARY KEY(escuela,docente)); CREATE TABLE prueba.prioridad_orden(escuela bigint, docente bigint, posicion integer CHECK(posicion>0), PRIMARY KEY(escuela,docente), UNIQUE(escuela,posicion)); CREATE TABLE prueba.prioridad_historial(escuela bigint,version int,actor bigint,anterior jsonb,nuevo jsonb,PRIMARY KEY(escuela,version)); INSERT INTO prueba.prioridad_actor VALUES (100,7,'DIRECTOR',true),(200,8,'SECRETARIA',true),(300,7,'DIRECTOR',false); INSERT INTO prueba.prioridad_config VALUES (7,0),(8,0); INSERT INTO prueba.prioridad_catalogo VALUES (7,41,'Docente Alfa',true),(7,42,'Docente Beta',true),(7,43,'Docente Gamma',true),(8,44,'Docente Otra Escuela',true)");
        $actor = (new User)->forceFill(['id' => 100]);
        $otro = (new User)->forceFill(['id' => 200]);
        $editor = Livewire::actingAs($actor)->test(EditorPrioridadFicticio::class)->assertSee('Docente Alfa')->assertDontSee('Docente Otra Escuela');
        $editor->set('buscar', 'Beta')->assertSee('Docente Beta')->assertDontSee('Docente Alfa')->set('buscar', '');
        $editor->call('agregar', 41)->call('agregar', 42)->call('agregar', 43)->assertSet('cola', [41, 42, 43]);
        $editor->call('mover', 43, 0)->assertSet('cola', [43, 41, 42])->call('mover', 43, 1)->assertSet('cola', [41, 43, 42]);
        $editor->call('retirar', 43)->assertSet('cola', [41, 42])->set('periodo', '999')->call('guardar')->assertHasNoErrors()->assertSet('version', 1);
        self::assertSame([41, 42], (new PrioridadDocenteFicticia($db))->consultar($actor)['cola']);
        self::assertSame([], (new PrioridadDocenteFicticia($db))->consultar($otro)['cola']);
        Livewire::actingAs($actor)->test(EditorPrioridadFicticio::class)->set('cola', [44])->call('guardar')->assertHasErrors(['cola']);
        Livewire::actingAs($actor)->test(EditorPrioridadFicticio::class)->set('cola', [41, 41])->call('guardar')->assertHasErrors(['cola']);
        Livewire::actingAs($actor)->test(EditorPrioridadFicticio::class)->set('version', 0)->call('guardar')->assertHasErrors(['version']);
        Livewire::actingAs((new User)->forceFill(['id' => 300]))->test(EditorPrioridadFicticio::class)->assertForbidden();
        $revocado = Livewire::actingAs($actor)->test(EditorPrioridadFicticio::class);
        $db->table('prueba.prioridad_actor')->where('usuario_id', 100)->update(['activo' => false]);
        $revocado->call('guardar')->assertForbidden();
        $db->table('prueba.prioridad_actor')->where('usuario_id', 100)->update(['activo' => true]);
        $env = ['APP_ENV' => 'testing', 'HORARIOS_27B2_PASSWORD' => $clave];
        $a = new Process([PHP_BINARY, __DIR__.'/soporte/guardar_prioridad_27c4.php'], base_path(), $env, json_encode(['version' => 1, 'cola' => [42, 41]], JSON_THROW_ON_ERROR), 30);
        $b = new Process([PHP_BINARY, __DIR__.'/soporte/guardar_prioridad_27c4.php'], base_path(), $env, json_encode(['version' => 1, 'cola' => [41, 43]], JSON_THROW_ON_ERROR), 30);
        $db->beginTransaction();
        try {
            $db->table('prueba.prioridad_config')->where('escuela', 7)->lockForUpdate()->first();
            $a->start();
            $b->start();
            $limite = microtime(true) + 20;
            do {
                $db->select('select pg_stat_clear_snapshot()');
                $esperando = (int) $db->selectOne("select count(*) as n from pg_stat_activity where datname=current_database() and usename=current_user and wait_event_type='Lock' and query like '%prioridad_config%' and query ilike '%for update%'")->n;
                if ($esperando >= 2) {
                    break;
                }
                usleep(100000);
            } while (microtime(true) < $limite);
            self::assertGreaterThanOrEqual(2, $esperando);
        } finally {
            $db->rollBack();
        }
        $a->wait();
        $b->wait();
        self::assertTrue($a->isSuccessful(), $a->getErrorOutput());
        self::assertTrue($b->isSuccessful(), $b->getErrorOutput());
        $salidas = [trim($a->getOutput()), trim($b->getOutput())];
        sort($salidas);
        self::assertSame(['GUARDADA', 'VERSION_OBSOLETA'], $salidas);
        self::assertSame(2, $db->table('prueba.prioridad_historial')->count());
        self::assertSame([100, 100], $db->table('prueba.prioridad_historial')->orderBy('version')->pluck('actor')->map(fn ($x) => (int) $x)->all());
        $proveedor = new ProveedorPrioridadFicticio($db, new ProveedorComplementosPostgresFicticio($db));
        $snapshot = new InstantaneaGeneracion($db);
        $ensamblador = new EnsambladorAcademicoPrueba($db, $proveedor);
        $antes = $ensamblador->construir($snapshot->construir(1), '1');
        self::assertTrue($antes['ejecutable'], json_encode($antes['diagnosticos']));
        self::assertSame(2, $antes['entrada']['prioridad_docente']['version']);
        $resultado = (new EjecutorPythonPruebas($python))->ejecutarExacto($antes['entrada']);
        self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE']);
        self::assertTrue((new ValidarResultadoFicticio)->validar($antes['entrada'], $resultado)['solucion_validada']);
        $orden = (new PrioridadDocenteFicticia($db))->consultar($actor)['cola'];
        (new PrioridadDocenteFicticia($db))->guardar($actor, 2, array_reverse($orden));
        $despues = $ensamblador->construir($snapshot->construir(1), '1');
        self::assertTrue($despues['ejecutable'], json_encode($despues['diagnosticos']));
        self::assertSame(3, $despues['entrada']['prioridad_docente']['version']);
        self::assertNotSame($antes['huella_academica_sha256'], $despues['huella_academica_sha256']);
        self::assertSame('CAMBIO_DETECTADO', (new ProteccionPropuestaPrueba($db, $proveedor))->aceptar($antes, $resultado)['estado']);
        $resultadoDespues = (new EjecutorPythonPruebas($python))->ejecutarExacto($despues['entrada']);
        self::assertContains($resultadoDespues['estado'], ['OPTIMAL', 'FEASIBLE']);
        self::assertTrue((new ValidarResultadoFicticio)->validar($despues['entrada'], $resultadoDespues)['solucion_validada']);
        self::assertSame(3, $resultadoDespues['prioridad_docente']['version']);
    }
}
