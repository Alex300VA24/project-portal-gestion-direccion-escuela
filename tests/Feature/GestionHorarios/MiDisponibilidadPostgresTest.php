<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorIdentidadDocente;
use App\Modules\GestionHorarios\Livewire\MiDisponibilidad;
use App\Modules\GestionHorarios\Services\ConsultaDisponibilidadPropia;
use App\Modules\GestionHorarios\Services\InstantaneaGeneracion;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class MiDisponibilidadPostgresTest extends TestCase
{
    public function test_lectura_propia_y_estados_en_postgres_aislado(): void
    {
        if (getenv('HORARIOS_27B2_POSTGRES_AISLADO') !== '1') {
            $this->markTestSkipped('Requiere PostgreSQL 17 aislado.');
        }
        $clave = getenv('HORARIOS_27B2_PASSWORD');
        if (! is_string($clave) || $clave === '') {
            throw new RuntimeException('Falta credencial de prueba.');
        }
        config(['database.default' => 'gh_27b2', 'database.connections.gh_27b2' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55471, 'database' => 'horarios_27b2_test', 'username' => 'horarios_test', 'password' => $clave, 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public']]);
        $db = DB::connection();
        $id = $db->selectOne("select current_database() as base,current_user as usuario,inet_server_port() as puerto,current_setting('server_version_num') as version");
        if ($id->base !== 'horarios_27b2_test' || $id->usuario !== 'horarios_test' || (int) $id->puerto !== 5432 || (int) $id->version < 170000 || (int) $id->version >= 180000 || $db->selectOne("select exists(select 1 from pg_namespace where nspname in ('core','gestion_horarios','prueba')) as existe")->existe) {
            throw new RuntimeException('Base vacía y aislada no verificada.');
        }
        $db->unprepared(ltrim(file_get_contents(__DIR__.'/soporte/instantanea_27b2.sql'), "\xEF\xBB\xBF"));
        $db->unprepared("ALTER TABLE gestion_horarios.declaracion_disponibilidad ADD COLUMN presentado_en timestamptz; UPDATE gestion_horarios.declaracion_disponibilidad SET presentado_en='2026-01-01' WHERE estado='PRESENTADA'");
        $proveedor = new class implements ProveedorIdentidadDocente
        {
            public function docenteId(User $usuario): ?int
            {
                return match ($usuario->id) {
                    100 => 41, 200 => 42, default => null
                };
            }
        };
        $this->get(route('gestion-horarios.mi-disponibilidad'))->assertRedirect(route('login'));
        $this->app->instance(ProveedorIdentidadDocente::class, $proveedor);
        $u1 = (new User)->forceFill(['id' => 100, 'name' => 'Docente ficticio A']);
        $u2 = (new User)->forceFill(['id' => 200, 'name' => 'Docente ficticio B']);
        $sinVinculo = (new User)->forceFill(['id' => 300, 'name' => 'Sin identidad']);
        foreach ([$u1, $u2, $sinVinculo] as $usuario) {
            $usuario->setRelation('roles', collect([new Role(['name' => 'profesor', 'guard_name' => 'web'])]));
        }
        $consulta = new ConsultaDisponibilidadPropia($db, $proveedor);
        self::assertFalse($consulta->consultar($sinVinculo, 1)['acceso']);
        $db->transaction(function () use ($db, $consulta, $u1): void {
            $db->statement('SET TRANSACTION READ ONLY');
            self::assertTrue($consulta->consultar($u1, 1)['periodo_valido']);
        });
        $propia = $consulta->consultar($u1, 1);
        self::assertTrue($propia['periodo_valido']);
        self::assertSame('PRESENTADA_CON_FRANJAS', $propia['estado']);
        self::assertSame(['Curso A'], $propia['asignaciones']->pluck('nombre')->all());
        self::assertSame(['Curso B'], $consulta->consultar($u2, 1)['asignaciones']->pluck('nombre')->all());
        self::assertFalse($consulta->consultar($u1, 999)['periodo_valido']);
        $db->table('gestion_horarios.oferta_curso')->where('id_oferta', 11)->update(['id_curso_plan' => 202]);
        self::assertTrue($consulta->consultar($u1, 1)['periodos']->isEmpty());
        $db->table('gestion_horarios.oferta_curso')->where('id_oferta', 11)->update(['id_curso_plan' => 101]);
        $db->table('core.docente')->where('id_docente', 41)->update(['estado' => 'INACTIVO']);
        self::assertTrue($consulta->consultar($u1, 1)['periodos']->isEmpty());
        $db->table('core.docente')->where('id_docente', 41)->update(['estado' => 'ACTIVO']);
        Livewire::actingAs($u2)->test(MiDisponibilidad::class)->set('periodo', '1')->assertSee('Curso B')->assertDontSee('Curso A')->set('periodo', '999')->assertSee('Seleccione un periodo con asignaciones');
        self::assertFalse(property_exists(MiDisponibilidad::class, 'docente'));
        $instantanea = new InstantaneaGeneracion($db);
        $antes = $instantanea->construir(1)['huella_sha256'];
        $db->table('gestion_horarios.disponibilidad_docente')->where('id_docente', 41)->delete();
        self::assertSame('PRESENTADA_SIN_FRANJAS', $consulta->consultar($u1, 1)['estado']);
        self::assertNotSame($antes, $instantanea->construir(1)['huella_sha256']);
        $db->table('gestion_horarios.declaracion_disponibilidad')->where('id_docente', 41)->delete();
        self::assertSame('SIN_DECLARACION', $consulta->consultar($u1, 1)['estado']);
        $db->table('gestion_horarios.asignacion_docente')->where('id_docente', 41)->update(['estado' => 'RECHAZADA']);
        self::assertTrue($consulta->consultar($u1, 1)['periodos']->isEmpty());
        $this->app->forgetInstance(ProveedorIdentidadDocente::class);
        Livewire::actingAs($sinVinculo)->test(MiDisponibilidad::class)->assertSee('No se pudo verificar la vinculación docente');
    }
}
