<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Livewire\HorariosRegistrados;
use App\Modules\GestionHorarios\Services\ConsultaHorarios;
use App\Modules\GestionHorarios\Services\ExportacionHorarios;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class ConsultaHorariosPostgresTest extends TestCase
{
    public function test_consulta_filtra_y_conserva_sesiones_simultaneas(): void
    {
        if (getenv('HORARIOS_COLA_POSTGRES_AISLADO') !== '1' || ! getenv('HORARIOS_COLA_TEST_PASSWORD')) {
            $this->markTestSkipped('Requiere PostgreSQL 17 aislado.');
        }
        config(['database.default' => 'horarios_test', 'database.connections.horarios_test' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55440, 'database' => 'horarios_cola_test', 'username' => 'horarios_test', 'password' => getenv('HORARIOS_COLA_TEST_PASSWORD'), 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public']]);
        $db = DB::connection();
        $real = $db->selectOne("select current_database() as db,current_user as usuario,current_setting('server_version_num') as version");
        if ($real->db !== 'horarios_cola_test' || $real->usuario !== 'horarios_test' || (int) $real->version < 170000 || (int) $real->version >= 180000 || $db->selectOne("select exists(select 1 from pg_namespace where nspname in ('core','gestion_horarios')) as existe")->existe) {
            throw new RuntimeException('La base aislada debe estar vacía.');
        }
        $db->beginTransaction();
        try {
            (require base_path('database/migrations/0001_01_01_000000_create_users_table.php'))->up();
            (require base_path('database/migrations/2026_10_28_223424_create_permission_tables.php'))->up();
            $db->unprepared("CREATE SCHEMA core; CREATE SCHEMA gestion_horarios;
                CREATE TABLE core.periodo_academico (id_periodo bigint PRIMARY KEY, codigo text, fecha_inicio date);
                CREATE TABLE core.curso (id_curso bigint, codigo text, nombre text);
                CREATE TABLE core.facultad (id_facultad bigint, nombre text);
                CREATE TABLE core.escuela_profesional (id_escuela bigint, id_facultad bigint, nombre text);
                CREATE TABLE core.plan_estudios (id_plan bigint, id_escuela bigint, nombre text);
                CREATE TABLE core.curso_plan (id_curso_plan bigint, id_curso bigint, ciclo int, id_plan bigint);
                CREATE TABLE core.seccion (id_seccion bigint, id_periodo bigint, id_curso_plan bigint, codigo text);
                CREATE TABLE core.ambiente (id_ambiente bigint, nombre text);
                CREATE TABLE core.persona (id_persona bigint, nombres text, apellido_paterno text, apellido_materno text);
                CREATE TABLE core.docente (id_docente bigint, id_persona bigint);
                CREATE TABLE gestion_horarios.horario (id_horario bigint, id_periodo bigint, ambito text, version int, estado text);
                CREATE TABLE gestion_horarios.actividad_academica (id_actividad bigint, id_seccion bigint, tipo_actividad text, dia_semana text, hora_inicio time, hora_fin time);
                CREATE TABLE gestion_horarios.detalle_horario (id_detalle bigint, id_horario bigint, id_actividad bigint, id_ambiente bigint);
                CREATE TABLE gestion_horarios.asignacion_docente (id_asignacion bigint, id_actividad bigint, id_docente bigint, estado text);
                INSERT INTO core.periodo_academico VALUES (1,'P1','2026-01-01'),(2,'P2','2026-08-01');
                INSERT INTO core.facultad VALUES (1,'Facultad ficticia');
                INSERT INTO core.escuela_profesional VALUES (1,1,'Escuela ficticia');
                INSERT INTO core.plan_estudios VALUES (1,1,'Plan ficticio');
                INSERT INTO core.curso VALUES (1,'C1','Curso uno de análisis avanzado con un nombre académico considerablemente extenso'),(2,'C2','Curso dos');
                INSERT INTO core.curso_plan VALUES (1,1,1,1),(2,2,2,1);
                INSERT INTO core.seccion VALUES (1,1,1,'A'),(2,1,2,'B'),(3,2,1,'A');
                INSERT INTO core.ambiente VALUES (1,'Aula 1');
                INSERT INTO core.persona VALUES (1,'Ana','Docente',''); INSERT INTO core.docente VALUES (1,1);
                INSERT INTO gestion_horarios.horario VALUES (1,1,'GENERAL',1,'BORRADOR'),(2,2,'GENERAL',1,'PUBLICADO');
                INSERT INTO gestion_horarios.actividad_academica VALUES (1,1,'TEORIA','LUNES','08:00','10:00'),(2,2,'LABORATORIO','LUNES','08:00','10:00'),(3,3,'PRACTICA','MARTES','08:00','10:00');
                INSERT INTO gestion_horarios.detalle_horario VALUES (1,1,1,1),(2,1,2,1),(3,2,3,1);
                INSERT INTO gestion_horarios.asignacion_docente VALUES (1,1,1,'CONFIRMADA');");
	    (require base_path(
    'app/Modules/GestionHorarios/Database/Migrations/2026_10_29_000004_create_bloque_horario_table.php'
))->up();

$db->unprepared("
    INSERT INTO gestion_horarios.bloque_horario
        (id_periodo, nombre, hora_inicio, hora_fin, orden)
    VALUES
        (1, 'Bloque 1', '08:00', '09:00', 1),
        (1, 'Bloque 2', '09:00', '10:00', 2);
");
            $servicio = new ConsultaHorarios($db);
            $datos = $servicio->consultar(1, 1, '', '');
            self::assertCount(2, $datos['sesiones']);
            self::assertCount(1, $datos['docentes']->get(1));
            $vista = view('modules.gestion-horarios.horarios-registrados', $datos + ['errorConsulta' => false, 'errorDescarga' => ''])->render();
            self::assertStringContainsString('Curso uno', $vista);
            self::assertStringContainsString('Curso dos', $vista);
            self::assertStringContainsString('Ana Docente', $vista);
            self::assertCount(1, $servicio->consultar(1, 1, '2', 'B')['sesiones']);
            self::assertCount(0, $servicio->consultar(1, 2, '', '')['sesiones']);
            self::assertCount(0, $servicio->consultar(99, 1, '', '')['sesiones']);
            $exportador = new ExportacionHorarios($db);
            $filtrado = $exportador->consultar(1, 1, '2', 'B');
	    self::assertCount(2, $filtrado['bloquesPeriodo']);
            self::assertStringStartsWith('%PDF-', $exportador->pdf($filtrado));
            self::assertStringStartsWith('PK', $exportador->xlsx($filtrado));
            $muestras = getenv('HORARIOS_MUESTRAS_SALIDA');
            if (is_string($muestras) && is_dir($muestras)) {
                $completo = $exportador->consultar(1, 1);
                file_put_contents($muestras.DIRECTORY_SEPARATOR.'horarios-ficticios.pdf', $exportador->pdf($completo));
                file_put_contents($muestras.DIRECTORY_SEPARATOR.'horarios-ficticios.xlsx', $exportador->xlsx($completo));
            }
            foreach (['director_escuela', 'secretaria'] as $rol) {
                Role::findOrCreate($rol, 'web')->givePermissionTo(Permission::findOrCreate('gestion-horarios.view', 'web'));
                $usuario = User::factory()->create();
                $usuario->assignRole($rol);
                // Ni un permiso de lectura ni el rol acreditan el ámbito de una descarga global.
                Livewire::actingAs($usuario)->test(HorariosRegistrados::class)->assertForbidden();
            }
        } finally {
            $db->rollBack();
        }
    }
}
