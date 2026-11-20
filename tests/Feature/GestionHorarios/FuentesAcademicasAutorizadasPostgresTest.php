<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Modules\GestionHorarios\Contracts\ProveedorPermisoGestionAcademica;
use App\Modules\GestionHorarios\Demand\IdentidadCursoPlanInstitucional;
use App\Modules\GestionHorarios\Livewire\DistribucionSecciones;
use App\Modules\GestionHorarios\Livewire\OfertaAcademica;
use App\Modules\GestionHorarios\Livewire\RevisionesOferta;
use App\Modules\GestionHorarios\Repositories\ConsultaAsignacionDocenteRepository;
use App\Modules\GestionHorarios\Repositories\ConsultaRevisionesRepository;
use App\Modules\GestionHorarios\Repositories\ConsultaSeccionesRepository;
use App\Modules\GestionHorarios\Repositories\OfertaAcademicaRepository;
use App\Modules\GestionHorarios\Services\AmbitoEscrituraAcademica;
use App\Modules\GestionHorarios\Services\FuentesAcademicasAutorizadas;
use App\Modules\GestionHorarios\Services\GestionOfertaAcademica;
use App\Modules\GestionHorarios\Services\GestionOfertaSeccion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\GestionHorarios\soporte\ProveedorPermisoGestionFicticio;
use Tests\TestCase;

final class FuentesAcademicasAutorizadasPostgresTest extends TestCase
{
    public function test_lectura_por_plan_rechaza_otras_escuelas_y_no_produce_entrada_para_motor(): void
    {
        if (getenv('HORARIOS_27B2_POSTGRES_AISLADO') !== '1') {
            $this->markTestSkipped('Requiere PostgreSQL 17 aislado.');
        }
        $clave = getenv('HORARIOS_27B2_PASSWORD');
        if (! is_string($clave) || $clave === '') {
            throw new RuntimeException('Falta la credencial aislada.');
        }
        config(['database.default' => 'gh_27b2', 'database.connections.gh_27b2' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55471, 'database' => 'horarios_27b2_test', 'username' => 'horarios_test', 'password' => $clave, 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public']]);
        $db = DB::connection();
        $identidad = $db->selectOne("select current_database() as base,current_user as usuario,inet_server_port() as puerto,current_setting('server_version_num') as version");
        if ($identidad->base !== 'horarios_27b2_test' || $identidad->usuario !== 'horarios_test' || (int) $identidad->puerto !== 5432 || (int) $identidad->version < 170000 || (int) $identidad->version >= 180000 || $db->selectOne("select exists(select 1 from pg_namespace where nspname in ('core','gestion_horarios','prueba')) as existe")->existe) {
            throw new RuntimeException('Base aislada vacía no verificada.');
        }
        $db->unprepared(ltrim(file_get_contents(__DIR__.'/soporte/instantanea_27b2.sql'), "\xEF\xBB\xBF"));
        $db->unprepared("INSERT INTO core.plan_estudios VALUES (30,8); INSERT INTO core.curso VALUES (400,'CURSO-A','Curso ajeno',2,0,0); INSERT INTO core.curso_plan VALUES (404,400,30,1); INSERT INTO core.seccion VALUES (444,1,404,'A',25,NULL,'ACTIVA'); INSERT INTO gestion_horarios.oferta_curso VALUES (44,1,404,'REGULAR',1,'OFERTADA'); INSERT INTO gestion_horarios.oferta_seccion VALUES (5,44,444,'VIGENTE'); INSERT INTO gestion_horarios.actividad_academica VALUES (66,444,'TEORIA','LUNES','09:00','11:00',120); INSERT INTO gestion_horarios.asignacion_docente VALUES (75,66,42,'CONFIRMADA',NULL)");
        $db->unprepared("INSERT INTO core.periodo_academico VALUES (2,'PERIODO-B','2027-03-01','2027-07-30','ACTIVO'); INSERT INTO gestion_horarios.oferta_curso VALUES (45,2,404,'REGULAR',1,'OFERTADA'); ALTER TABLE gestion_horarios.oferta_seccion ADD COLUMN registrada_en timestamp, ADD COLUMN cerrada_en timestamp");
        $periodos = new OfertaAcademicaRepository($db);
        self::assertSame([], $periodos->periodosParaPlan(0)->all());
        self::assertSame(['1'], $periodos->periodosParaPlan(10)->pluck('id_periodo')->map(fn ($id) => (string) $id)->all());
        self::assertSame(['2', '1'], $periodos->periodosParaPlan(30)->pluck('id_periodo')->map(fn ($id) => (string) $id)->all());
        $db->unprepared("CREATE TABLE core.persona (id_persona bigint PRIMARY KEY,nombres text,apellido_paterno text,apellido_materno text,numero_documento text); ALTER TABLE core.docente ADD COLUMN id_persona bigint; ALTER TABLE core.docente ADD COLUMN codigo_docente text; INSERT INTO core.persona VALUES (41,'Alfa','A','A','1'),(42,'Beta','B','B','2'),(43,'Gamma','C','C','3'),(44,'Delta','D','D','4'); UPDATE core.docente SET id_persona=id_docente,codigo_docente='D'||id_docente");
        $asignaciones = new ConsultaAsignacionDocenteRepository($db);
        self::assertSame([], $asignaciones->docentes('', 1, 0)->all());
        self::assertSame(['41'], $asignaciones->docentes('', 1, 10)->pluck('id_docente')->map(fn ($id) => (string) $id)->all());
        self::assertSame(['42'], $asignaciones->docentes('', 1, 30)->pluck('id_docente')->map(fn ($id) => (string) $id)->all());
        self::assertSame(['61'], $asignaciones->cargaConfirmada(1, 10)->pluck('id_actividad')->map(fn ($id) => (string) $id)->all());
        $db->unprepared("CREATE TABLE gestion_horarios.revision_oferta_pendiente (id_revision bigint primary key,id_oferta bigint,motivo text,creada_en timestamp); CREATE TABLE gestion_horarios.detalle_horario (id_horario bigint,id_actividad bigint); INSERT INTO gestion_horarios.revision_oferta_pendiente VALUES (1,11,'Revisión A','2026-11-01'),(2,44,'Revisión B','2026-11-02'); INSERT INTO gestion_horarios.horario VALUES (101,1,'BORRADOR',1,NULL),(102,1,'APROBADO',2,NULL); INSERT INTO gestion_horarios.detalle_horario VALUES (101,61),(102,66)");
        $revisiones = new ConsultaRevisionesRepository($db);
        self::assertSame([], $revisiones->pendientes([])->all());
        self::assertSame([], $revisiones->pendientes(['7'])->all());
        self::assertSame([1], $revisiones->pendientes([7])->pluck('id_revision')->map(fn ($id) => (int) $id)->all());
        self::assertSame(['BORRADOR'], $revisiones->pendientes([7])->first()->estados_horario);
        self::assertSame([2], $revisiones->pendientes([8])->pluck('id_revision')->map(fn ($id) => (int) $id)->all());
        self::assertSame(['APROBADO'], $revisiones->pendientes([8])->first()->estados_horario);
        $usuario = (new class extends User
        {
            public function hasAnyRole(...$roles): bool
            {
                return true;
            }

            public function can($abilities, $arguments = []): bool
            {
                return true;
            }
        })->forceFill(['id' => 1]);
        $identidadCurso = new IdentidadCursoPlanInstitucional($db);
        self::assertTrue($identidadCurso->coincide(7, 10, 100, 101));
        self::assertFalse($identidadCurso->coincide(8, 10, 100, 101));
        self::assertFalse($identidadCurso->coincide(7, 10, 400, 404));
        $proveedor = new class implements ProveedorEscuelasAutorizadas
        {
            public function escuelasVigentes(User $usuario): array
            {
                return match ($usuario->id) {
                    1 => [7], 2 => [8], default => []
                };
            }
        };
        $consulta = new FuentesAcademicasAutorizadas($db, $proveedor);
        app()->instance(ProveedorPermisoGestionAcademica::class, new ProveedorPermisoGestionFicticio);
        $antes = $db->table('gestion_horarios.oferta_curso')->count();
        $primero = $consulta->consultar($usuario, 1, 10);
        self::assertFalse($primero['ejecutable']);
        self::assertSame('FUENTES_ACADEMICAS_AUTORIZADAS_V1', $primero['contrato']);
        self::assertSame(['101'], array_map('strval', array_column($primero['cursos'], 'id_curso_plan')));
        self::assertSame(['111', '112'], array_map('strval', array_column($primero['secciones'], 'id_seccion')));
        self::assertSame(['71'], array_map('strval', array_column($primero['asignaciones'], 'id_asignacion')));
        self::assertArrayNotHasKey('id_docente', $primero['asignaciones'][0]);
        $revocado = new class implements ProveedorEscuelasAutorizadas
        {
            private int $consultas = 0;

            public function escuelasVigentes(User $usuario): array
            {
                return ++$this->consultas === 1 ? [7] : [];
            }
        };
        try {
            (new FuentesAcademicasAutorizadas($db, $revocado))->consultar($usuario, 1, 10);
            self::fail('La revocación durante la lectura debe impedir la entrega.');
        } catch (AuthorizationException) {
            self::assertSame(0, $db->transactionLevel());
        }
        self::assertContains('SECCION_SIN_DOCENTE', array_column($primero['diagnosticos'], 'codigo'));
        $db->table('gestion_horarios.oferta_seccion')->insert(['id_oferta_seccion' => 6, 'id_oferta' => 11, 'id_seccion' => 444, 'estado' => 'VIGENTE']);
        self::assertNotContains('444', array_map('strval', array_column($consulta->consultar($usuario, 1, 10)['oferta_seccion'], 'id_seccion')));
        $db->table('gestion_horarios.oferta_seccion')->insert(['id_oferta_seccion' => 7, 'id_oferta' => 44, 'id_seccion' => 111, 'estado' => 'VIGENTE']);
        $curso = (new ConsultaSeccionesRepository($db))->cursos(1, null, '', '', 10)->first();
        self::assertArrayNotHasKey('id_docente_responsable', (array) $curso->secciones->first());
        self::assertArrayNotHasKey('nombres', (array) $curso->secciones->first());
        $idsVisibles = $curso->secciones->firstWhere('id_seccion', 111)->asociaciones->pluck('id_oferta_seccion')->map(fn ($id) => (string) $id)->all();
        self::assertNotContains('7', $idsVisibles);
        self::assertContains('1', $idsVisibles);
        $segundo = $consulta->consultar($usuario, 1, 20);
        self::assertSame(['202', '303'], array_map('strval', array_column($segundo['cursos'], 'id_curso_plan')));
        self::assertSame(['222', '333'], array_map('strval', array_column($segundo['secciones'], 'id_seccion')));
        $db->table('gestion_horarios.oferta_seccion')->where('id_oferta', 22)->update(['estado' => 'INACTIVA']);
        self::assertContains('OFERTA_SIN_SECCION', array_column($consulta->consultar($usuario, 1, 20)['diagnosticos'], 'codigo'));
        $db->statement('ALTER TABLE gestion_horarios.oferta_seccion RENAME TO oferta_seccion_aislada');
        $sinAsociacion = $consulta->consultar($usuario, 1, 10);
        self::assertNull($sinAsociacion['oferta_seccion']);
        self::assertContains('ASOCIACION_OFERTA_SECCION_NO_DISPONIBLE', array_column($sinAsociacion['diagnosticos'], 'codigo'));
        self::assertFalse($sinAsociacion['ejecutable']);
        self::assertSame($antes, $db->table('gestion_horarios.oferta_curso')->count());
        self::assertSame(0, $db->transactionLevel());
        $director = new class extends User
        {
            public function hasAnyRole(...$roles): bool
            {
                return true;
            }

            public function can($abilities, $arguments = []): bool
            {
                return true;
            }
        };
        $director->forceFill(['id' => 1]);
        $db->unprepared("ALTER TABLE core.plan_estudios ADD COLUMN codigo text, ADD COLUMN nombre text; UPDATE core.plan_estudios SET codigo='PLAN-'||id_plan,nombre='Plan '||id_plan; ALTER TABLE gestion_horarios.demanda_instantanea ADD COLUMN requiere_revision boolean NOT NULL DEFAULT false");
        app()->instance(ProveedorEscuelasAutorizadas::class, $proveedor);
        app()->forgetInstance(ProveedorPermisoGestionAcademica::class);
        Livewire::actingAs($director)->test(OfertaAcademica::class)->set('plan', '10')->set('periodo', '1')
            ->assertDontSee('Crear oferta')->call('nueva')->assertForbidden();
        app()->instance(ProveedorPermisoGestionAcademica::class, new ProveedorPermisoGestionFicticio);
        Livewire::actingAs($director)->test(OfertaAcademica::class)->set('plan', '10')->set('periodo', '1')
            ->assertSee('Curso A')->assertDontSee('Curso ajeno')
            ->set('plan', '30')->assertDontSee('Curso ajeno')->assertSee('Seleccione un plan autorizado');
        Livewire::actingAs($director)->test(OfertaAcademica::class)->set('plan', '10')->set('periodo', '1')
            ->call('comprobarPreparacion')->assertSee('No se pueden comprobar las asociaciones')
            ->assertSee('no demuestra factibilidad')->assertDontSee('Curso ajeno')
            ->set('plan', '30')->assertDontSee('No se pueden comprobar las asociaciones');
        Livewire::actingAs($director)->test(OfertaAcademica::class)->set('plan', '10')->set('periodo', '2')
            ->call('comprobarPreparacion')->assertSee('Seleccione un periodo vinculado al plan autorizado');
        $resumenLivewire = Livewire::actingAs($director)->test(OfertaAcademica::class)->set('plan', '10')->set('periodo', '1')
            ->call('comprobarPreparacion')->assertSee('No se pueden comprobar las asociaciones');
        app()->forgetInstance(ProveedorEscuelasAutorizadas::class);
        $resumenLivewire->call('$refresh')->assertForbidden();
        app()->instance(ProveedorEscuelasAutorizadas::class, $proveedor);
        $db->statement('ALTER TABLE gestion_horarios.oferta_seccion_aislada RENAME TO oferta_seccion');
        Livewire::actingAs($director)->withQueryParams(['plan' => '10', 'periodo' => '1'])
            ->test(DistribucionSecciones::class)->assertSet('plan', '10')->assertSet('periodo', '1')
            ->assertSee('Curso A')->assertDontSee('Curso ajeno');
        Livewire::actingAs($director)->withQueryParams(['plan' => '30', 'periodo' => '1'])
            ->test(DistribucionSecciones::class)->assertDontSee('Curso ajeno')->assertSee('Seleccione un plan autorizado');
        $revisionLivewire = Livewire::actingAs($director)->test(RevisionesOferta::class)->assertSee('Revisión A')->assertDontSee('Revisión B');
        $directorB = new class extends User
        {
            public function hasAnyRole(...$roles): bool
            {
                return true;
            }

            public function can($abilities, $arguments = []): bool
            {
                return true;
            }
        };
        $directorB->forceFill(['id' => 2]);
        Livewire::actingAs($directorB)->test(RevisionesOferta::class)->assertSee('Revisión B')->assertDontSee('Revisión A');
        Livewire::actingAs($directorB)->test(OfertaAcademica::class)->set('plan', '30')->set('periodo', '1')
            ->assertSee('Curso ajeno')->assertDontSee('Curso A')
            ->set('plan', '10')->assertDontSee('Curso A')->assertSee('Seleccione un plan autorizado');
        Livewire::actingAs($director);
        app()->forgetInstance(ProveedorEscuelasAutorizadas::class);
        $revisionLivewire->call('$refresh')->assertSee('verificar la escuela autorizada')->assertDontSee('Revisión A');
        $db->transaction(fn () => (new AmbitoEscrituraAcademica($db, $proveedor))->autorizarCurso($director, 101));
        self::assertSame(0, $db->transactionLevel());
        foreach ([
            fn () => (new GestionOfertaAcademica($db))->crear($director, ['periodo' => 1, 'curso' => 101, 'tipo' => 'REGULAR', 'docentes' => 1]),
            fn () => (new GestionOfertaAcademica($db, $proveedor))->actualizar($director, 44, ['estado' => 'OFERTADA', 'docentes' => 2], 'OFERTADA', 1),
            fn () => (new GestionOfertaSeccion($db, $proveedor))->asociar($director, 1, 444, 44, null),
        ] as $operacion) {
            try {
                $operacion();
                self::fail('No debe aceptarse una escritura sin escuela autorizada.');
            } catch (HttpException $e) {
                self::assertSame(403, $e->getStatusCode());
            }
        }
        self::assertSame($antes, $db->table('gestion_horarios.oferta_curso')->count());
        $db->unprepared("INSERT INTO core.curso VALUES (500,'CURSO-PRUEBA','Curso de prueba',2,0,0); INSERT INTO core.curso_plan VALUES (505,500,10,2); ALTER TABLE gestion_horarios.oferta_curso ALTER COLUMN id_oferta ADD GENERATED BY DEFAULT AS IDENTITY (START WITH 100)");
        app()->forgetInstance(ProveedorPermisoGestionAcademica::class);
        foreach ([
            fn () => (new GestionOfertaAcademica($db, $proveedor))->crear($director, ['periodo' => 1, 'curso' => 505, 'tipo' => 'REGULAR', 'docentes' => 1]),
            fn () => (new GestionOfertaAcademica($db, $proveedor))->actualizar($director, 11, ['estado' => 'OFERTADA', 'docentes' => 2], 'OFERTADA', 1),
            fn () => (new GestionOfertaSeccion($db, $proveedor))->asociar($director, 1, 111, 11, 1),
        ] as $sinPermiso) {
            try {
                $sinPermiso();
                self::fail('La adscripción no sustituye el permiso de gestión.');
            } catch (HttpException $e) {
                self::assertSame(403, $e->getStatusCode());
            }
        }
        self::assertSame($antes, $db->table('gestion_horarios.oferta_curso')->count());
        $revocacionGestion = new class implements ProveedorPermisoGestionAcademica
        {
            private int $consultas = 0;

            public function puedeGestionar(User $usuario): bool
            {
                return ++$this->consultas === 1;
            }
        };
        app()->instance(ProveedorPermisoGestionAcademica::class, $revocacionGestion);
        try {
            (new GestionOfertaAcademica($db, $proveedor))->crear($director, ['periodo' => 1, 'curso' => 505, 'tipo' => 'REGULAR', 'docentes' => 1]);
            self::fail('Una revocación observada antes de insertar debe denegarse.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->getStatusCode());
        }
        self::assertSame($antes, $db->table('gestion_horarios.oferta_curso')->count());
        app()->instance(ProveedorPermisoGestionAcademica::class, new ProveedorPermisoGestionFicticio);
        $revocacionEscuela = new class implements ProveedorEscuelasAutorizadas
        {
            private int $consultas = 0;

            public function escuelasVigentes(User $usuario): array
            {
                return ++$this->consultas === 1 ? [7] : [];
            }
        };
        try {
            (new GestionOfertaAcademica($db, $revocacionEscuela))->crear($director, ['periodo' => 1, 'curso' => 505, 'tipo' => 'REGULAR', 'docentes' => 1]);
            self::fail('Una revocación de escuela observada antes de insertar debe denegarse.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->getStatusCode());
        }
        self::assertSame($antes, $db->table('gestion_horarios.oferta_curso')->count());
        (new GestionOfertaAcademica($db, $proveedor))->crear($director, ['periodo' => 1, 'curso' => 505, 'tipo' => 'REGULAR', 'docentes' => 1]);
        self::assertSame($antes + 1, $db->table('gestion_horarios.oferta_curso')->count());
        self::assertSame('PLANIFICADA', $db->table('gestion_horarios.oferta_curso')->where('id_curso_plan', 505)->value('estado'));
        foreach ([new FuentesAcademicasAutorizadas($db), $consulta] as $i => $servicio) {
            try {
                $servicio->consultar($usuario, 1, $i ? 30 : 10);
                self::fail('Debe denegarse el acceso sin adscripción o a otro plan.');
            } catch (AuthorizationException) {
                self::assertTrue(true);
            }
        }
        try {
            $sinEscuela = clone $usuario;
            $sinEscuela->forceFill(['id' => 3]);
            $consulta->consultar($sinEscuela, 1, 10);
            self::fail('Un usuario sin escuelas no debe consultar datos.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }
        $sinRol = new class extends User
        {
            public function hasAnyRole(...$roles): bool
            {
                return false;
            }

            public function can($abilities, $arguments = []): bool
            {
                return false;
            }
        };
        $sinRol->forceFill(['id' => 1]);
        try {
            $consulta->consultar($sinRol, 1, 10);
            self::fail('Una adscripción sin rol administrativo no debe autorizar esta consulta.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }
    }
}
