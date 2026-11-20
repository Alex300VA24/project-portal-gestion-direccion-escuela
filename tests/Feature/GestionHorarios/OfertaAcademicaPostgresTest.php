<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Modules\GestionHorarios\Contracts\ProveedorPermisoGestionAcademica;
use App\Modules\GestionHorarios\Demand\RepositorioDemandaPostgres;
use App\Modules\GestionHorarios\Engine\AdaptadorPreparacion;
use App\Modules\GestionHorarios\Engine\MapeoAcademicoParcial;
use App\Modules\GestionHorarios\Livewire\AmbientesAcademicos;
use App\Modules\GestionHorarios\Livewire\AsignacionDocente;
use App\Modules\GestionHorarios\Livewire\DisponibilidadDocente;
use App\Modules\GestionHorarios\Livewire\DistribucionSecciones;
use App\Modules\GestionHorarios\Livewire\HorariosRegistrados;
use App\Modules\GestionHorarios\Livewire\OfertaAcademica;
use App\Modules\GestionHorarios\Livewire\PeriodosAcademicos;
use App\Modules\GestionHorarios\Livewire\PreparacionGeneracion;
use App\Modules\GestionHorarios\Livewire\RestriccionesAcademicas;
use App\Modules\GestionHorarios\Livewire\RevisionesOferta;
use App\Modules\GestionHorarios\Repositories\ConsultaPeriodosRepository;
use App\Modules\GestionHorarios\Repositories\ConsultaRestriccionesRepository;
use App\Modules\GestionHorarios\Repositories\PoblacionAcademicaRepository;
use App\Modules\GestionHorarios\Services\ConsultaAmbientes;
use App\Modules\GestionHorarios\Services\ConsultaAsignacionDocente;
use App\Modules\GestionHorarios\Services\ConsultaDisponibilidadDocente;
use App\Modules\GestionHorarios\Services\GestionOfertaAcademica;
use App\Modules\GestionHorarios\Services\PoblacionAcademica;
use App\Modules\GestionHorarios\Services\PrevalidacionGeneracion;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\GestionHorarios\soporte\ProveedorPermisoGestionFicticio;
use Tests\TestCase;

final class OfertaAcademicaPostgresTest extends TestCase
{
    private static bool $preparado = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('HORARIOS_OFERTA_POSTGRES_AISLADO') !== '1') {
            $this->markTestSkipped('Requiere PostgreSQL 17 aislado expresamente habilitado.');
        }
        $nombreBase = getenv('HORARIOS_PREPARACION_DB') === '1' ? 'horarios_preparacion_test' : 'horarios_oferta_test';
        config(['database.default' => 'oferta_test', 'database.connections.oferta_test' => [
            'driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55440, 'database' => $nombreBase, 'username' => 'horarios_test', 'password' => getenv('HORARIOS_COLA_TEST_PASSWORD') ?: 'horarios_test', 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public', 'sslmode' => 'prefer',
        ]]);
        $db = DB::connection();
        $identidad = $db->selectOne('select current_database() as db, current_user as usuario, inet_server_port() as puerto, current_setting(\'server_version_num\') as version');
        if ($identidad->db !== $nombreBase || $identidad->usuario !== 'horarios_test' || (int) $identidad->puerto !== 5432 || (int) $identidad->version < 170000 || (int) $identidad->version >= 180000) {
            throw new RuntimeException('La prueba requiere la base PostgreSQL 17 aislada.');
        }
        if (! self::$preparado) {
            if ($db->selectOne("select exists(select 1 from pg_namespace where nspname in ('core','gestion_horarios')) as existe")->existe) {
                throw new RuntimeException('La base aislada debe estar vacía.');
            }
            (require base_path('database/migrations/0001_01_01_000000_create_users_table.php'))->up();
            (require base_path('database/migrations/2026_10_28_223424_create_permission_tables.php'))->up();
            $db->unprepared("CREATE SCHEMA core; CREATE SCHEMA gestion_horarios;
                CREATE TABLE core.periodo_academico (id_periodo BIGINT PRIMARY KEY, codigo TEXT, fecha_inicio DATE, fecha_fin DATE, anio SMALLINT, tipo TEXT, numero_semanas SMALLINT, estado TEXT);
                CREATE TABLE core.curso (id_curso BIGINT PRIMARY KEY, codigo TEXT, nombre TEXT);
                CREATE TABLE core.plan_estudios (id_plan BIGINT PRIMARY KEY, id_escuela BIGINT, codigo TEXT, nombre TEXT);
                CREATE TABLE core.curso_plan (id_curso_plan BIGINT PRIMARY KEY, id_curso BIGINT NOT NULL REFERENCES core.curso(id_curso), ciclo SMALLINT, id_plan BIGINT DEFAULT 1);
                CREATE TABLE core.persona (id_persona BIGINT PRIMARY KEY, nombres TEXT, apellido_paterno TEXT, apellido_materno TEXT, numero_documento TEXT);
                CREATE TABLE core.docente (id_docente BIGINT PRIMARY KEY, id_persona BIGINT REFERENCES core.persona(id_persona), codigo_docente TEXT, estado TEXT, horas_maximas SMALLINT);
                CREATE TABLE core.seccion (id_seccion BIGINT PRIMARY KEY, id_periodo BIGINT NOT NULL, id_curso_plan BIGINT NOT NULL, codigo TEXT, capacidad INTEGER, estado TEXT, id_docente_responsable BIGINT);
                CREATE TABLE core.ambiente (id_ambiente BIGINT PRIMARY KEY, nombre TEXT, codigo TEXT, tipo TEXT, capacidad INTEGER, pabellon TEXT, piso SMALLINT, estado TEXT);
                CREATE TABLE gestion_horarios.horario (id_horario BIGINT PRIMARY KEY, id_periodo BIGINT NOT NULL, estado TEXT, version SMALLINT, ambito TEXT);
                CREATE TABLE gestion_horarios.actividad_academica (id_actividad BIGINT PRIMARY KEY, id_seccion BIGINT NOT NULL REFERENCES core.seccion(id_seccion), tipo_actividad TEXT, dia_semana TEXT, hora_inicio TIME, hora_fin TIME);
                CREATE TABLE gestion_horarios.asignacion_docente (id_asignacion BIGINT PRIMARY KEY, id_actividad BIGINT NOT NULL REFERENCES gestion_horarios.actividad_academica(id_actividad), id_docente BIGINT NOT NULL, estado TEXT);
                CREATE TABLE gestion_horarios.detalle_horario (id_detalle BIGINT PRIMARY KEY, id_horario BIGINT NOT NULL REFERENCES gestion_horarios.horario(id_horario), id_actividad BIGINT NOT NULL REFERENCES gestion_horarios.actividad_academica(id_actividad), id_ambiente BIGINT);
                CREATE TABLE gestion_horarios.bloque_horario (id_bloque BIGINT PRIMARY KEY, id_periodo BIGINT, nombre TEXT, hora_inicio TIME, hora_fin TIME);
                CREATE TABLE gestion_horarios.declaracion_disponibilidad (id_declaracion BIGINT PRIMARY KEY, id_periodo BIGINT, id_docente BIGINT, estado TEXT, tratamiento_regular TEXT, presentado_en TIMESTAMP);
                CREATE TABLE gestion_horarios.disponibilidad_docente (id_disponibilidad BIGINT PRIMARY KEY, id_periodo BIGINT, id_docente BIGINT, id_declaracion BIGINT, dia_semana TEXT, id_bloque BIGINT);
                CREATE TABLE gestion_horarios.ambiente_bloqueo (id_bloqueo BIGINT PRIMARY KEY, id_periodo BIGINT, id_ambiente BIGINT, fecha_inicio DATE, fecha_fin DATE, dia_semana TEXT, hora_inicio TIME, hora_fin TIME, motivo TEXT);
                CREATE TABLE gestion_horarios.restriccion (id_restriccion BIGINT PRIMARY KEY, codigo TEXT, nombre TEXT, descripcion TEXT, tipo TEXT, severidad TEXT, activa BOOLEAN);
                CREATE TABLE gestion_horarios.detalle_restriccion (id_detalle BIGINT PRIMARY KEY, id_detalle_restriccion BIGINT GENERATED BY DEFAULT AS IDENTITY, id_restriccion BIGINT, ambito TEXT, id_periodo BIGINT, prioridad SMALLINT, id_ambiente BIGINT, id_docente BIGINT);
                INSERT INTO core.plan_estudios VALUES (1,1,'PLAN-A','Plan A'),(2,1,'PLAN-B','Plan B'),(3,2,'PLAN-C','Plan sin cursos');
                INSERT INTO core.periodo_academico VALUES (1,'2026-I','2026-03-01','2026-07-31',2026,'REGULAR',18,'ACTIVO'),(2,'2026-II','2026-08-01','2026-12-31',2026,'REGULAR',18,'CERRADO'),(3,'2027-I','2027-03-01','2027-07-31',2027,'REGULAR',18,'PLANIFICADO');
                INSERT INTO core.curso VALUES (1,'INF101','Algoritmos'),(2,'MAT202','Matemática'),(3,'INF303','Redes');
                INSERT INTO core.curso_plan VALUES (1,1,1),(2,2,2),(3,3,3);");
            (require base_path('app/Modules/GestionHorarios/Database/Migrations/2026_10_29_000001_create_oferta_curso_table.php'))->up();
            (require base_path('app/Modules/GestionHorarios/Database/Migrations/2026_11_16_000001_create_demanda_academica_tables.php'))->up();
            (require base_path('app/Modules/GestionHorarios/Database/Migrations/2026_11_16_000002_create_revision_oferta_table.php'))->up();
            $db->unprepared("INSERT INTO gestion_horarios.oferta_curso(id_periodo,id_curso_plan,tipo_oferta,estado) VALUES (1,1,'REGULAR','OFERTADA'),(1,2,'REPETICION','PLANIFICADA'),(2,2,'REGULAR','OFERTADA');
                INSERT INTO gestion_horarios.demanda_instantanea(id_demanda,id_periodo,version,huella,requiere_revision) OVERRIDING SYSTEM VALUE VALUES (1,1,1,repeat('a',64),false),(2,1,2,repeat('b',64),true);
                INSERT INTO gestion_horarios.demanda_curso VALUES (1,1,99,50,40),(2,1,20,10,5),(2,3,7,4,2);");
            self::$preparado = true;
        }
        $this->withoutVite();
        app()->instance(ProveedorPermisoGestionAcademica::class, new ProveedorPermisoGestionFicticio);
        app()->instance(ProveedorEscuelasAutorizadas::class, new class implements ProveedorEscuelasAutorizadas
        {
            public function escuelasVigentes(User $usuario): array
            {
                return [1, 2];
            }
        });
    }

    private function usuario(?string $rol): User
    {
        $usuario = User::factory()->create();
        if ($rol) {
            Role::findOrCreate($rol, 'web')->givePermissionTo(Permission::findOrCreate('gestion-horarios.view', 'web'));
            $usuario->assignRole($rol);
        }

        return $usuario;
    }

    public function test_acceso_director_secretaria_y_rechazo(): void
    {
        $this->get('/gestion-horarios/administracion/oferta-academica')->assertRedirect('/login');
        $this->actingAs($this->usuario(null))->get('/gestion-horarios/administracion/oferta-academica')->assertForbidden();
        foreach (['director_escuela', 'secretaria'] as $rol) {
            $this->actingAs($this->usuario($rol))->get('/gestion-horarios/administracion/oferta-academica')->assertOk()->assertSee('Oferta académica');
        }
        $this->actingAs($this->usuario(null));
        Livewire::test(OfertaAcademica::class)->assertForbidden();
    }

    public function test_sin_adscripcion_institucional_se_deniega_acceso_a_cualquier_plan(): void
    {
        app()->forgetInstance(ProveedorEscuelasAutorizadas::class);
        foreach (['director_escuela', 'secretaria', 'director_escuela', 'secretaria'] as $rol) {
            $this->actingAs($this->usuario($rol));
            foreach ([OfertaAcademica::class, DistribucionSecciones::class, AsignacionDocente::class, HorariosRegistrados::class, PreparacionGeneracion::class] as $componente) {
                Livewire::test($componente, ['plan' => '2', 'periodo' => '1'])->assertForbidden();
            }
        }
    }

    public function test_ciclos_se_obtienen_del_plan_y_se_limpian_al_cambiar(): void
    {
        DB::beginTransaction();
        try {
            DB::table('core.curso')->insert(['id_curso' => 99, 'codigo' => 'B99', 'nombre' => 'Exclusivo B']);
            DB::table('core.curso_plan')->insert(['id_curso_plan' => 99, 'id_curso' => 99, 'ciclo' => 7, 'id_plan' => 2]);
            DB::table('gestion_horarios.oferta_curso')->insert(['id_periodo' => 1, 'id_curso_plan' => 99, 'tipo_oferta' => 'REGULAR', 'estado' => 'PLANIFICADA']);
            DB::table('core.seccion')->insert(['id_seccion' => 99, 'id_periodo' => 1, 'id_curso_plan' => 99, 'codigo' => 'B']);
            DB::table('gestion_horarios.actividad_academica')->insert(['id_actividad' => 99, 'id_seccion' => 99, 'tipo_actividad' => 'TEORIA', 'dia_semana' => 'LUNES', 'hora_inicio' => '08:00', 'hora_fin' => '10:00']);
            $this->actingAs($this->usuario('director_escuela'));
            foreach ([OfertaAcademica::class, DistribucionSecciones::class] as $componente) {
                Livewire::test($componente)->set('periodo', '1')->set('plan', '1')
                    ->assertSee('PLAN-A')->assertDontSee('value="7"', false)->assertDontSee('Exclusivo B')
                    ->set('ciclo', '1')->set('plan', '2')->assertSet('ciclo', '')
                    ->assertSee('El ciclo seleccionado no existe en el plan elegido')
                    ->assertSee('value="7"', false)->assertSee('Exclusivo B')->set('plan', '3')->assertSee('El plan seleccionado no tiene ciclos registrados');
            }
            Livewire::test(AsignacionDocente::class)->assertForbidden();
        } finally {
            DB::rollBack();
        }
    }

    public function test_consulta_busqueda_filtros_y_demanda_vigente(): void
    {
        $this->actingAs($this->usuario('director_escuela'));
        Livewire::test(OfertaAcademica::class)
            ->set('plan', '1')->assertSee('Seleccione un periodo')
            ->set('periodo', '1')->assertSee('Algoritmos')->assertSee('Matemática')->assertSee('Redes')->assertSee('Sin datos')->assertSee('Sin oferta')
            ->assertSee('Versión vigente 2')->assertSee('Revisión pendiente')->assertSee('20')->assertDontSee('99')
            ->set('buscar', 'INF101')->assertSee('Algoritmos')->assertDontSee('Matemática')->assertDontSee('Redes')
            ->set('buscar', 'matemática')->assertSee('Matemática')->assertSee('Repeticion')
            ->set('buscar', '')->set('ciclo', '1')->assertSee('Algoritmos')->assertDontSee('Matemática')
            ->set('ciclo', '')->set('estado', 'PLANIFICADA')->assertSee('Matemática')->assertDontSee('Algoritmos')
            ->set('estado', '')->set('periodo', '2')->assertSee('No hay datos de demanda visibles para los filtros seleccionados')->assertSee('Matemática')->assertSee('Sin datos')
            ->set('periodo', '3')->assertSee('No se encontraron cursos');
    }

    public function test_error_de_consulta_no_aparece_como_estado_vacio(): void
    {
        $this->actingAs($this->usuario('secretaria'));
        DB::shouldReceive('connection')->once()->andThrow(new QueryException('pgsql', 'consulta', [], new RuntimeException('fallo de prueba')));
        Livewire::test(OfertaAcademica::class)->assertSee('No se pudo consultar la información')->assertDontSee('No hay periodos disponibles');
    }

    public function test_muestra_estado_vacio_si_no_hay_periodos(): void
    {
        $this->actingAs($this->usuario('secretaria'));
        DB::beginTransaction();
        try {
            DB::table('gestion_horarios.demanda_curso')->delete();
            DB::table('gestion_horarios.demanda_instantanea')->delete();
            DB::table('gestion_horarios.oferta_curso')->delete();
            DB::table('core.periodo_academico')->delete();
            Livewire::test(OfertaAcademica::class)->set('plan', '1')->assertSee('No hay periodos vinculados');
        } finally {
            DB::rollBack();
        }
    }

    public function test_gestion_livewire_valida_crea_edita_y_cierra(): void
    {
        $this->actingAs($this->usuario('director_escuela'));
        Livewire::test(OfertaAcademica::class)->set('plan', '1')->set('periodo', '3')->call('nueva')
            ->set('cursoFormulario', '999')->call('guardar')->assertHasErrors('curso')
            ->set('cursoFormulario', '1')->set('docentesFormulario', '0')->call('guardar')->assertHasErrors('docentes')
            ->set('docentesFormulario', '2')->call('guardar')->assertSee('La oferta se guardó correctamente.');
        $id = DB::table('gestion_horarios.oferta_curso')->where('id_periodo', 3)->where('id_curso_plan', 1)->value('id_oferta');
        self::assertNotNull($id);
        Livewire::test(OfertaAcademica::class)->set('plan', '1')->set('periodo', '3')->call('nueva')->set('cursoFormulario', '1')->call('guardar')->assertHasErrors('curso');
        Livewire::test(OfertaAcademica::class)->set('plan', '1')->set('periodo', '3')->call('editar', $id)->set('estadoFormulario', 'CERRADA')->call('guardar')->assertHasErrors('formulario')
            ->set('estadoFormulario', 'OFERTADA')->set('docentesFormulario', '3')->call('guardar')->assertSee('La oferta se guardó correctamente.')
            ->call('editar', $id)->set('estadoFormulario', 'CERRADA')->call('guardar')->assertSee('La oferta se guardó correctamente.');
        self::assertSame('CERRADA', DB::table('gestion_horarios.oferta_curso')->where('id_oferta', $id)->value('estado'));
    }

    public function test_cambiar_plan_cierra_el_formulario_de_la_oferta_anterior(): void
    {
        $this->actingAs($this->usuario('director_escuela'));
        $id = DB::table('gestion_horarios.oferta_curso')->where('id_periodo', 1)->where('id_curso_plan', 1)->value('id_oferta');
        Livewire::test(OfertaAcademica::class)->set('plan', '1')->set('periodo', '1')
            ->call('editar', $id)->assertSet('formularioAbierto', true)
            ->set('plan', '2')->assertSet('formularioAbierto', false)->assertSet('ofertaEditar', null);
    }

    public function test_bloquea_horarios_aprobados_y_publicados_y_registra_revision(): void
    {
        $this->actingAs($this->usuario('secretaria'));
        DB::table('core.seccion')->insert([['id_seccion' => 10, 'id_periodo' => 1, 'id_curso_plan' => 1], ['id_seccion' => 11, 'id_periodo' => 1, 'id_curso_plan' => 2]]);
        DB::table('gestion_horarios.horario')->insert([['id_horario' => 10, 'id_periodo' => 1, 'estado' => 'APROBADO'], ['id_horario' => 11, 'id_periodo' => 1, 'estado' => 'PUBLICADO']]);
        DB::table('gestion_horarios.actividad_academica')->insert([['id_actividad' => 10, 'id_seccion' => 10], ['id_actividad' => 11, 'id_seccion' => 11]]);
        DB::table('gestion_horarios.detalle_horario')->insert([['id_detalle' => 10, 'id_horario' => 10, 'id_actividad' => 10], ['id_detalle' => 11, 'id_horario' => 11, 'id_actividad' => 11]]);
        foreach ([1, 2] as $curso) {
            $oferta = DB::table('gestion_horarios.oferta_curso')->where('id_periodo', 1)->where('id_curso_plan', $curso)->first();
            $nuevoEstado = $oferta->estado === 'OFERTADA' ? 'CERRADA' : 'OFERTADA';
            Livewire::test(OfertaAcademica::class)->set('plan', '1')->set('periodo', '1')->call('editar', $oferta->id_oferta)->set('estadoFormulario', $nuevoEstado)->call('guardar')->assertHasErrors('formulario')->assertSee('Se registró una revisión pendiente');
            self::assertSame($oferta->estado, DB::table('gestion_horarios.oferta_curso')->where('id_oferta', $oferta->id_oferta)->value('estado'));
            self::assertSame(1, DB::table('gestion_horarios.revision_oferta_pendiente')->where('id_oferta', $oferta->id_oferta)->count());
        }
        self::assertSame(['APROBADO', 'PUBLICADO'], DB::table('gestion_horarios.horario')->orderBy('id_horario')->pluck('estado')->all());
    }

    public function test_escritura_no_autorizada_y_rollback(): void
    {
        $usuario = $this->usuario(null);
        try {
            (new GestionOfertaAcademica(DB::connection()))->crear($usuario, ['periodo' => 3, 'curso' => 2, 'tipo' => 'REGULAR', 'docentes' => 1]);
            self::fail('La operación debía rechazar al usuario sin rol.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->getStatusCode());
        }
        $usuario = $this->usuario('secretaria');
        DB::beginTransaction();
        try {
            (new GestionOfertaAcademica(DB::connection()))->crear($usuario, ['periodo' => 3, 'curso' => 2, 'tipo' => 'REGULAR', 'docentes' => 1]);
            self::assertTrue(DB::table('gestion_horarios.oferta_curso')->where('id_periodo', 3)->where('id_curso_plan', 2)->exists());
        } finally {
            DB::rollBack();
        }
        self::assertFalse(DB::table('gestion_horarios.oferta_curso')->where('id_periodo', 3)->where('id_curso_plan', 2)->exists());
    }

    public function test_rechaza_edicion_obsoleta_y_filtra_tipo(): void
    {
        $this->actingAs($this->usuario('director_escuela'));
        $id = DB::table('gestion_horarios.oferta_curso')->where('id_periodo', 2)->where('id_curso_plan', 2)->value('id_oferta');
        $formulario = Livewire::test(OfertaAcademica::class)->set('plan', '1')->set('periodo', '2')->call('editar', $id);
        DB::table('gestion_horarios.oferta_curso')->where('id_oferta', $id)->update(['docentes_minimos' => 5]);
        $formulario->set('docentesFormulario', '2')->call('guardar')->assertHasErrors('formulario')->assertSee('La oferta cambió');
        self::assertSame(5, DB::table('gestion_horarios.oferta_curso')->where('id_oferta', $id)->value('docentes_minimos'));
        Livewire::test(OfertaAcademica::class)->set('plan', '1')->set('periodo', '1')->set('tipoFiltro', 'REPETICION')->assertSee('Matemática')->assertDontSee('Algoritmos');
    }

    public function test_revisiones_y_periodos_exigen_roles_y_muestran_datos(): void
    {
        $oferta = DB::table('gestion_horarios.oferta_curso')->where('id_periodo', 1)->where('id_curso_plan', 1)->value('id_oferta');
        DB::table('gestion_horarios.revision_oferta_pendiente')->insert(['id_oferta' => $oferta, 'id_usuario' => 1, 'motivo' => 'Revisión de prueba']);
        DB::table('core.seccion')->insert(['id_seccion' => 20, 'id_periodo' => 1, 'id_curso_plan' => 1]);
        DB::table('gestion_horarios.horario')->insert([['id_horario' => 20, 'id_periodo' => 1, 'estado' => 'APROBADO'], ['id_horario' => 21, 'id_periodo' => 1, 'estado' => 'PUBLICADO']]);
        DB::table('gestion_horarios.actividad_academica')->insert(['id_actividad' => 20, 'id_seccion' => 20]);
        DB::table('gestion_horarios.detalle_horario')->insert([['id_detalle' => 20, 'id_horario' => 20, 'id_actividad' => 20], ['id_detalle' => 21, 'id_horario' => 21, 'id_actividad' => 20]]);
        DB::table('core.ambiente')->insert(['id_ambiente' => 1, 'nombre' => 'Laboratorio A']);
        DB::table('gestion_horarios.bloque_horario')->insert(['id_bloque' => 1, 'id_periodo' => 1, 'nombre' => 'Primer bloque', 'hora_inicio' => '08:00', 'hora_fin' => '09:00']);
        DB::table('gestion_horarios.ambiente_bloqueo')->insert(['id_bloqueo' => 1, 'id_periodo' => 1, 'id_ambiente' => 1, 'fecha_inicio' => '2026-04-01', 'fecha_fin' => '2026-04-02', 'motivo' => 'Mantenimiento']);
        DB::table('gestion_horarios.restriccion')->insert(['id_restriccion' => 1, 'nombre' => 'Cruce docente', 'severidad' => 'DURA', 'activa' => true]);
        DB::table('gestion_horarios.detalle_restriccion')->insert(['id_detalle' => 1, 'id_restriccion' => 1, 'ambito' => 'GLOBAL', 'id_periodo' => null, 'prioridad' => 1]);
        $this->get('/gestion-horarios/administracion/revisiones')->assertRedirect('/login');
        $this->get('/gestion-horarios/administracion/periodos')->assertRedirect('/login');
        $this->actingAs($this->usuario(null))->get('/gestion-horarios/administracion/revisiones')->assertForbidden();
        $this->get('/gestion-horarios/administracion/periodos')->assertForbidden();
        $this->actingAs($this->usuario('secretaria'));
        $this->get('/gestion-horarios/administracion/revisiones')->assertOk();
        $this->get('/gestion-horarios/administracion/periodos')->assertForbidden();
        Livewire::test(RevisionesOferta::class)->assertSee('Algoritmos')->assertSee('APROBADO')->assertSee('PUBLICADO')->assertSee('Ver oferta');
        $this->get('/gestion-horarios/administracion/oferta-academica?periodo=1&oferta='.$oferta)->assertOk()->assertSee('Detalle y edición de oferta')->assertSee('Algoritmos');
        Livewire::test(PeriodosAcademicos::class)->assertForbidden();
        $periodos = new ConsultaPeriodosRepository(DB::connection());
        self::assertContains('2026-I', $periodos->listar('', '')->pluck('codigo')->all());
        self::assertContains('2026-II', $periodos->listar('', 'CERRADO')->pluck('codigo')->all());
        self::assertSame(['2027-I'], $periodos->listar('2027-I', '')->pluck('codigo')->all());
        self::assertSame('2026-I', $periodos->detalle(1)->codigo);
        self::assertContains('Primer bloque', $periodos->bloques(1)->pluck('nombre')->all());
        self::assertContains('Laboratorio A', $periodos->bloqueos(1)->pluck('nombre')->all());
        self::assertContains('Cruce docente', $periodos->restricciones(1)->pluck('nombre')->all());
    }

    public function test_revisiones_y_periodos_bloquean_livewire_sin_rol_y_muestran_errores(): void
    {
        $this->actingAs($this->usuario(null));
        Livewire::test(RevisionesOferta::class)->assertForbidden();
        Livewire::test(PeriodosAcademicos::class)->assertForbidden();
        $this->actingAs($this->usuario('director_escuela'));
        Livewire::test(PeriodosAcademicos::class)->assertForbidden();
        DB::beginTransaction();
        try {
            DB::statement('ALTER TABLE gestion_horarios.revision_oferta_pendiente RENAME TO revision_temporal_prueba');
            Livewire::test(RevisionesOferta::class)->assertSee('No se pudieron consultar las revisiones')->assertDontSee('No hay revisiones registradas');
        } finally {
            DB::rollBack();
        }
        $db = \Mockery::mock(ConnectionInterface::class);
        $db->shouldReceive('table')->once()->andThrow(new QueryException('pgsql', 'consulta', [], new RuntimeException('fallo de prueba')));
        $this->expectException(QueryException::class);
        (new ConsultaPeriodosRepository($db))->listar('', '');
    }

    public function test_secciones_consulta_filtros_y_datos_incompletos_sin_distribuir(): void
    {
        $this->get('/gestion-horarios/administracion/secciones')->assertRedirect('/login');
        $this->actingAs($this->usuario(null))->get('/gestion-horarios/administracion/secciones')->assertForbidden();
        Livewire::test(DistribucionSecciones::class)->assertForbidden();
        $this->actingAs($this->usuario('director_escuela'))->get('/gestion-horarios/administracion/secciones')->assertOk();
        $this->actingAs($this->usuario('secretaria'));
        DB::beginTransaction();
        try {
            DB::table('core.seccion')->where('id_periodo', 1)->where('id_curso_plan', 1)->update(['capacidad' => 1, 'estado' => 'ACTIVA']);
            DB::table('core.persona')->insert(['id_persona' => 50, 'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'apellido_materno' => 'Rojas']);
            DB::table('core.docente')->insert(['id_docente' => 50, 'id_persona' => 50]);
            DB::table('core.seccion')->insert([
                ['id_seccion' => 50, 'id_periodo' => 1, 'id_curso_plan' => 1, 'codigo' => 'A', 'capacidad' => 3, 'estado' => 'ACTIVA', 'id_docente_responsable' => 50],
                ['id_seccion' => 51, 'id_periodo' => 1, 'id_curso_plan' => 1, 'codigo' => 'B', 'capacidad' => 4, 'estado' => 'ACTIVA', 'id_docente_responsable' => null],
                ['id_seccion' => 52, 'id_periodo' => 2, 'id_curso_plan' => 2, 'codigo' => 'R', 'capacidad' => -1, 'estado' => 'ACTIVA', 'id_docente_responsable' => null],
            ]);
            $this->get('/gestion-horarios/administracion/secciones')->assertOk();
            Livewire::test(DistribucionSecciones::class)->set('plan', '1')->set('periodo', '1')
                ->assertSee('Algoritmos')->assertDontSee('Ana Pérez Rojas')->assertSee('REGULAR')->assertSee('REPETICION')
                ->assertSee('No hay secciones registradas')->assertSee('La demanda supera esa suma')->assertSee('sin distinguir tipo ni estado')
                ->set('buscar', 'MAT202')->assertSee('Matemática')->assertDontSee('Algoritmos')
                ->set('buscar', 'R')->set('periodo', '2')->assertSee('Matemática')
                ->set('periodo', '1')
                ->set('buscar', '')->set('ciclo', '1')->assertSee('Algoritmos')->assertDontSee('Matemática')
                ->set('ciclo', '')->set('periodo', '2')->assertSee('Demanda del curso: sin datos')->assertSee('Sin capacidad válida')->assertDontSee('Ana Pérez Rojas');
            self::assertSame(3, DB::table('core.seccion')->whereIn('id_seccion', [50, 51, 52])->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_asignacion_docente_consulta_filtros_y_seguridad(): void
    {
        $this->get('/gestion-horarios/administracion/asignacion-docente')->assertRedirect('/login');
        $this->actingAs($this->usuario(null))->get('/gestion-horarios/administracion/asignacion-docente')->assertForbidden();
        Livewire::test(AsignacionDocente::class)->assertForbidden();
        foreach (['director_escuela', 'secretaria'] as $rol) {
            $this->actingAs($this->usuario($rol))->get('/gestion-horarios/administracion/asignacion-docente')->assertForbidden();
        }
        DB::beginTransaction();
        try {
            DB::table('core.persona')->insert(['id_persona' => 80, 'nombres' => 'Rosa', 'apellido_paterno' => 'Paredes', 'apellido_materno' => 'López', 'numero_documento' => '87654321']);
            DB::table('core.docente')->insert([['id_docente' => 80, 'id_persona' => 80, 'codigo_docente' => 'DOC80', 'estado' => 'ACTIVO', 'horas_maximas' => 20], ['id_docente' => 81, 'id_persona' => null, 'codigo_docente' => 'DOC81', 'estado' => 'ACTIVO', 'horas_maximas' => null]]);
            DB::table('core.seccion')->insert([['id_seccion' => 80, 'id_periodo' => 1, 'id_curso_plan' => 1, 'codigo' => 'AX'], ['id_seccion' => 81, 'id_periodo' => 1, 'id_curso_plan' => 2, 'codigo' => 'BX']]);
            DB::table('gestion_horarios.actividad_academica')->insert([
                ['id_actividad' => 80, 'id_seccion' => 80, 'tipo_actividad' => 'TEORIA', 'dia_semana' => 'LUNES', 'hora_inicio' => '08:00', 'hora_fin' => '09:00'],
                ['id_actividad' => 81, 'id_seccion' => 80, 'tipo_actividad' => 'LABORATORIO', 'dia_semana' => 'MARTES', 'hora_inicio' => '10:00', 'hora_fin' => '12:00'],
                ['id_actividad' => 82, 'id_seccion' => 81, 'tipo_actividad' => 'PRACTICA', 'dia_semana' => null, 'hora_inicio' => null, 'hora_fin' => null],
            ]);
            DB::table('gestion_horarios.asignacion_docente')->insert([
                ['id_asignacion' => 80, 'id_actividad' => 80, 'id_docente' => 80, 'estado' => 'CONFIRMADA'],
                ['id_asignacion' => 81, 'id_actividad' => 80, 'id_docente' => 80, 'estado' => 'CONFIRMADA'],
                ['id_asignacion' => 82, 'id_actividad' => 81, 'id_docente' => 80, 'estado' => 'CONFIRMADA'],
            ]);
            Livewire::test(AsignacionDocente::class)->assertForbidden();
            $servicio = new ConsultaAsignacionDocente(DB::connection());
            $datos = $servicio->consultar(1, '', '', '', 1);
            $docente = $datos['docentes']->firstWhere('id_docente', 80);
            self::assertSame(180, (int) $docente->minutos_confirmados);
            self::assertSame(2, $docente->actividades_confirmadas);
            $sinDatos = $servicio->consultar(1, '', '', '')['docentes']->firstWhere('id_docente', 81);
            self::assertNotNull($sinDatos);
            self::assertNull($sinDatos->nombres);
            self::assertTrue($datos['actividades']->firstWhere('id_actividad', 80)->duplicados);
            self::assertTrue($datos['actividades']->firstWhere('id_actividad', 82)->sin_docente_activo);
            self::assertNull($datos['actividades']->firstWhere('id_actividad', 82)->hora_inicio);
            self::assertSame(['DOC80'], $servicio->consultar(1, '87654321', '', '', 1)['docentes']->pluck('codigo_docente')->all());
            self::assertSame(['DOC80'], $servicio->consultar(1, 'Rosa Paredes', '', '', 1)['docentes']->pluck('codigo_docente')->all());
            self::assertContains('LABORATORIO', $servicio->consultar(1, 'DOC80', '', '', 1)['actividades']->pluck('tipo_actividad')->all());
            $cicloDos = $servicio->consultar(1, '', '2', '', 1)['actividades']->pluck('tipo_actividad')->all();
            self::assertContains('PRACTICA', $cicloDos);
            self::assertNotContains('LABORATORIO', $cicloDos);
            self::assertNotContains('PRACTICA', $servicio->consultar(1, '', '', 'AX', 1)['actividades']->pluck('tipo_actividad')->all());
            self::assertTrue($servicio->consultar(3, 'NOEXISTE', '', '', 1)['docentes']->isEmpty());
            self::assertTrue($servicio->consultar(3, '', '', '', 1)['actividades']->isEmpty());
        } finally {
            DB::rollBack();
        }
    }

    public function test_asignacion_docente_muestra_error_de_consulta(): void
    {
        $this->actingAs($this->usuario('secretaria'));
        Livewire::test(AsignacionDocente::class)->assertForbidden();
        $db = \Mockery::mock(ConnectionInterface::class);
        $db->shouldReceive('table')->once()->andThrow(new QueryException('pgsql', 'consulta', [], new RuntimeException('fallo de prueba')));
        $this->expectException(QueryException::class);
        (new ConsultaAsignacionDocente($db))->consultar(1, '', '', '', 1);
    }

    public function test_disponibilidad_docente_consulta_estados_y_seguridad(): void
    {
        $this->get('/gestion-horarios/administracion/disponibilidad-docente')->assertRedirect('/login');
        $this->actingAs($this->usuario(null))->get('/gestion-horarios/administracion/disponibilidad-docente')->assertForbidden();
        Livewire::test(DisponibilidadDocente::class)->assertForbidden();
        foreach (['director_escuela', 'secretaria'] as $rol) {
            $this->actingAs($this->usuario($rol))->get('/gestion-horarios/administracion/disponibilidad-docente')->assertForbidden();
        }
        DB::beginTransaction();
        try {
            DB::table('core.persona')->insert(['id_persona' => 90, 'nombres' => 'Carlos', 'apellido_paterno' => 'Soto', 'apellido_materno' => 'Vera', 'numero_documento' => '99001122']);
            DB::table('core.docente')->insert(['id_docente' => 90, 'id_persona' => 90, 'codigo_docente' => 'D90', 'estado' => 'ACTIVO']);
            DB::table('gestion_horarios.declaracion_disponibilidad')->insert([
                ['id_declaracion' => 90, 'id_periodo' => 1, 'id_docente' => 90, 'estado' => 'PRESENTADA', 'tratamiento_regular' => 'ESTRICTA', 'presentado_en' => '2026-01-01'],
                ['id_declaracion' => 91, 'id_periodo' => 2, 'id_docente' => 90, 'estado' => 'PENDIENTE', 'tratamiento_regular' => 'FLEXIBLE', 'presentado_en' => null],
                ['id_declaracion' => 92, 'id_periodo' => 3, 'id_docente' => 90, 'estado' => 'NO_PRESENTADA', 'tratamiento_regular' => 'ESTRICTA', 'presentado_en' => null],
            ]);
            DB::table('gestion_horarios.bloque_horario')->insert(['id_bloque' => 90, 'id_periodo' => 1, 'nombre' => 'Mañana 90', 'hora_inicio' => '08:00', 'hora_fin' => '09:00']);
            DB::table('gestion_horarios.disponibilidad_docente')->insert([
                ['id_disponibilidad' => 90, 'id_periodo' => 1, 'id_docente' => 90, 'id_declaracion' => 90, 'dia_semana' => 'LUNES', 'id_bloque' => 90],
                ['id_disponibilidad' => 91, 'id_periodo' => 1, 'id_docente' => 90, 'id_declaracion' => 90, 'dia_semana' => 'SABADO', 'id_bloque' => 90],
            ]);
            DB::table('core.seccion')->insert(['id_seccion' => 90, 'id_periodo' => 1, 'id_curso_plan' => 1, 'codigo' => 'Z']);
            DB::table('gestion_horarios.actividad_academica')->insert(['id_actividad' => 90, 'id_seccion' => 90, 'tipo_actividad' => 'TEORIA', 'dia_semana' => 'LUNES', 'hora_inicio' => '10:00', 'hora_fin' => '11:00']);
            DB::table('gestion_horarios.asignacion_docente')->insert(['id_asignacion' => 90, 'id_actividad' => 90, 'id_docente' => 90, 'estado' => 'CONFIRMADA']);
            DB::table('gestion_horarios.actividad_academica')->insert(['id_actividad' => 91, 'id_seccion' => 90, 'tipo_actividad' => 'PRACTICA', 'dia_semana' => 'SABADO', 'hora_inicio' => '08:00', 'hora_fin' => '09:00']);
            DB::table('gestion_horarios.asignacion_docente')->insert(['id_asignacion' => 91, 'id_actividad' => 91, 'id_docente' => 90, 'estado' => 'CONFIRMADA']);
            Livewire::test(DisponibilidadDocente::class)->assertForbidden();
            $servicio = new ConsultaDisponibilidadDocente(DB::connection());
            $datos = $servicio->consultar(1, 90, '99001122');
            self::assertSame('PRESENTADA', $datos['declaracion']->estado);
            self::assertSame('ESTRICTA', $datos['declaracion']->tratamiento_regular);
            self::assertContains('Mañana 90', $datos['bloques']->pluck('nombre')->all());
            self::assertContains('SABADO', $datos['dias']);
            self::assertSame(2, $datos['totalRegistros']);
            self::assertStringContainsString('Aparentemente fuera', $datos['actividades']->firstWhere('id_asignacion', 90)->observacion_preliminar);
            self::assertSame('PENDIENTE', $servicio->consultar(2, 90, '')['declaracion']->estado);
            self::assertSame('FLEXIBLE', $servicio->consultar(2, 90, '')['declaracion']->tratamiento_regular);
            self::assertTrue($servicio->consultar(2, 90, '')['bloques']->isEmpty());
            self::assertSame('NO_PRESENTADA', $servicio->consultar(3, 90, '')['declaracion']->estado);
        } finally {
            DB::rollBack();
        }
    }

    public function test_disponibilidad_detecta_duplicados_y_datos_inconsistentes(): void
    {
        $this->actingAs($this->usuario('secretaria'));
        DB::beginTransaction();
        try {
            DB::table('core.persona')->insert(['id_persona' => 91, 'nombres' => 'Lucía', 'apellido_paterno' => 'Luna', 'apellido_materno' => null]);
            DB::table('core.docente')->insert(['id_docente' => 91, 'id_persona' => 91, 'codigo_docente' => 'D91', 'estado' => 'ACTIVO']);
            DB::table('gestion_horarios.bloque_horario')->insert([['id_bloque' => 91, 'id_periodo' => 1, 'nombre' => 'Bloque 91', 'hora_inicio' => '08:00', 'hora_fin' => '09:00'], ['id_bloque' => 92, 'id_periodo' => 2, 'nombre' => 'Otro periodo', 'hora_inicio' => '09:00', 'hora_fin' => '10:00']]);
            DB::table('gestion_horarios.disponibilidad_docente')->insert([
                ['id_disponibilidad' => 92, 'id_periodo' => 1, 'id_docente' => 91, 'id_declaracion' => null, 'dia_semana' => 'LUNES', 'id_bloque' => 91],
                ['id_disponibilidad' => 93, 'id_periodo' => 1, 'id_docente' => 91, 'id_declaracion' => null, 'dia_semana' => 'LUNES', 'id_bloque' => 91],
                ['id_disponibilidad' => 94, 'id_periodo' => 1, 'id_docente' => 91, 'id_declaracion' => null, 'dia_semana' => 'DOMINGO', 'id_bloque' => 92],
            ]);
            Livewire::test(DisponibilidadDocente::class)->assertForbidden();
            $servicio = new ConsultaDisponibilidadDocente(DB::connection());
            $datos = $servicio->consultar(1, 91, '');
            self::assertNull($datos['declaracion']);
            self::assertContains('Hay bloques duplicados para el mismo día y horario.', $datos['avisos']);
            self::assertContains('Hay bloques registrados con día, horario o periodo inconsistente.', $datos['avisos']);
            self::assertSame(2, $datos['matriz']['91:LUNES']);
            self::assertNull($servicio->consultar(3, 91, '')['declaracion']);
            self::assertSame(0, $servicio->consultar(3, 91, '')['totalRegistros']);
        } finally {
            DB::rollBack();
        }
    }

    public function test_ambientes_consulta_seguridad_filtros_y_aislamiento(): void
    {
        $this->get('/gestion-horarios/administracion/ambientes')->assertRedirect('/login');
        $this->actingAs($this->usuario(null))->get('/gestion-horarios/administracion/ambientes')->assertForbidden();
        Livewire::test(AmbientesAcademicos::class)->assertForbidden();
        foreach (['director_escuela', 'secretaria'] as $rol) {
            $this->actingAs($this->usuario($rol))->get('/gestion-horarios/administracion/ambientes')->assertForbidden();
        }
        DB::beginTransaction();
        try {
            DB::table('core.ambiente')->insert([
                ['id_ambiente' => 100, 'codigo' => 'LAB-100', 'nombre' => 'Laboratorio de redes', 'tipo' => 'LAB', 'capacidad' => 24, 'pabellon' => 'A', 'piso' => 2, 'estado' => 'ACTIVO'],
                ['id_ambiente' => 101, 'codigo' => 'AULA-101', 'nombre' => 'Aula de prueba', 'tipo' => 'AULA', 'capacidad' => null, 'pabellon' => null, 'piso' => null, 'estado' => null],
            ]);
            DB::table('gestion_horarios.ambiente_bloqueo')->insert(['id_bloqueo' => 100, 'id_periodo' => 1, 'id_ambiente' => 100, 'fecha_inicio' => '2026-04-01', 'fecha_fin' => '2026-04-02', 'dia_semana' => 'MIERCOLES', 'hora_inicio' => '09:00', 'hora_fin' => '10:00', 'motivo' => 'Mantenimiento de prueba']);
            DB::table('core.seccion')->insert(['id_seccion' => 100, 'id_periodo' => 1, 'id_curso_plan' => 1, 'codigo' => 'T100']);
            DB::table('gestion_horarios.horario')->insert(['id_horario' => 100, 'id_periodo' => 1, 'estado' => 'BORRADOR', 'version' => 2, 'ambito' => 'GENERAL']);
            DB::table('gestion_horarios.actividad_academica')->insert(['id_actividad' => 100, 'id_seccion' => 100, 'tipo_actividad' => 'LABORATORIO', 'dia_semana' => 'LUNES', 'hora_inicio' => '08:00', 'hora_fin' => '09:00']);
            DB::table('gestion_horarios.detalle_horario')->insert(['id_detalle' => 100, 'id_horario' => 100, 'id_actividad' => 100, 'id_ambiente' => 100]);
            Livewire::test(AmbientesAcademicos::class)->assertForbidden();
            $servicio = new ConsultaAmbientes(DB::connection());
            $datos = $servicio->consultar(1, 100, 'LAB-100', '');
            self::assertSame(['Laboratorio de redes'], $datos['ambientes']->pluck('nombre')->all());
            self::assertSame(24, (int) $datos['seleccion']->capacidad);
            self::assertContains('Mantenimiento de prueba', $datos['bloqueos']->pluck('motivo')->all());
            self::assertContains('LABORATORIO', $datos['actividades']->pluck('tipo_actividad')->all());
            self::assertContains('BORRADOR', $datos['actividades']->pluck('estado_horario')->all());
            self::assertContains(2, $datos['actividades']->pluck('version')->map(fn ($v) => (int) $v)->all());
            $otroPeriodo = $servicio->consultar(2, 100, '', '');
            self::assertTrue($otroPeriodo['bloqueos']->isEmpty());
            self::assertTrue($otroPeriodo['actividades']->isEmpty());
            self::assertSame(['Aula de prueba'], $servicio->consultar(2, 101, '', 'AULA')['ambientes']->pluck('nombre')->all());
            self::assertNull($servicio->consultar(2, 101, '', 'AULA')['seleccion']->capacidad);
            self::assertNull($servicio->consultar(2, 101, '', 'AULA')['seleccion']->pabellon);
            self::assertTrue($servicio->consultar(2, null, 'NOEXISTE', '')['ambientes']->isEmpty());
        } finally {
            DB::rollBack();
        }
    }

    public function test_ambientes_no_oculta_errores_de_consulta(): void
    {
        $this->actingAs($this->usuario('secretaria'));
        Livewire::test(AmbientesAcademicos::class)->assertForbidden();
        $db = \Mockery::mock(ConnectionInterface::class);
        $db->shouldReceive('table')->once()->andThrow(new QueryException('pgsql', 'consulta', [], new RuntimeException('fallo de prueba')));
        $this->expectException(QueryException::class);
        (new ConsultaAmbientes($db))->consultar(1, 100, '', '');
    }

    public function test_restricciones_consulta_por_periodo_y_autorizacion(): void
    {
        $this->get('/gestion-horarios/administracion/restricciones')->assertRedirect('/login');
        $this->actingAs($this->usuario(null))->get('/gestion-horarios/administracion/restricciones')->assertForbidden();
        Livewire::test(RestriccionesAcademicas::class)->assertForbidden();
        foreach (['director_escuela', 'secretaria'] as $rol) {
            $this->actingAs($this->usuario($rol))->get('/gestion-horarios/administracion/restricciones')->assertForbidden();
        }
        Livewire::test(RestriccionesAcademicas::class)->assertForbidden();
        DB::beginTransaction();
        try {
            DB::table('gestion_horarios.restriccion')->insert([
                ['id_restriccion' => 201, 'codigo' => 'RG201', 'nombre' => 'Regla global', 'descripcion' => 'Descripción global', 'tipo' => 'OTRO', 'severidad' => 'DURA', 'activa' => true],
                ['id_restriccion' => 202, 'codigo' => 'RP202', 'nombre' => 'Regla periodo uno', 'descripcion' => 'Descripción uno', 'tipo' => 'OTRO', 'severidad' => 'BLANDA', 'activa' => false],
                ['id_restriccion' => 203, 'codigo' => 'RP203', 'nombre' => 'Regla periodo dos', 'descripcion' => 'Descripción dos', 'tipo' => 'OTRO', 'severidad' => 'DURA', 'activa' => true],
                ['id_restriccion' => 204, 'codigo' => 'RD204', 'nombre' => 'Regla docente', 'descripcion' => 'Descripción docente', 'tipo' => 'OTRO', 'severidad' => 'DURA', 'activa' => true],
                ['id_restriccion' => 205, 'codigo' => 'RA205', 'nombre' => 'Regla ambiente', 'descripcion' => 'Descripción ambiente', 'tipo' => 'OTRO', 'severidad' => 'DURA', 'activa' => true],
            ]);
            DB::table('core.docente')->insert(['id_docente' => 204, 'codigo_docente' => 'DOC204']);
            DB::table('core.ambiente')->insert(['id_ambiente' => 205, 'codigo' => 'LAB205', 'nombre' => 'Laboratorio 205']);
            DB::table('gestion_horarios.detalle_restriccion')->insert([
                ['id_detalle' => 201, 'id_restriccion' => 201, 'ambito' => 'GLOBAL', 'id_periodo' => null, 'prioridad' => 1, 'id_docente' => null, 'id_ambiente' => null],
                ['id_detalle' => 202, 'id_restriccion' => 202, 'ambito' => 'PERIODO', 'id_periodo' => 1, 'prioridad' => 1, 'id_docente' => null, 'id_ambiente' => null],
                ['id_detalle' => 203, 'id_restriccion' => 203, 'ambito' => 'PERIODO', 'id_periodo' => 2, 'prioridad' => 1, 'id_docente' => null, 'id_ambiente' => null],
                ['id_detalle' => 204, 'id_restriccion' => 204, 'ambito' => 'DOCENTE', 'id_periodo' => null, 'prioridad' => 1, 'id_docente' => 204, 'id_ambiente' => null],
                ['id_detalle' => 205, 'id_restriccion' => 205, 'ambito' => 'AMBIENTE', 'id_periodo' => null, 'prioridad' => 1, 'id_docente' => null, 'id_ambiente' => 205],
            ]);
            $repo = new ConsultaRestriccionesRepository(DB::connection());
            $uno = $repo->consultar(1);
            self::assertContains('Regla global', $uno->pluck('nombre')->all());
            self::assertContains('Regla periodo uno', $uno->pluck('nombre')->all());
            self::assertNotContains('Regla periodo dos', $uno->pluck('nombre')->all());
            self::assertContains('BLANDA', $uno->pluck('severidad')->all());
            self::assertContains('DOC204', $uno->pluck('codigo_docente')->all());
            self::assertContains('LAB205', $uno->pluck('codigo_ambiente')->all());
            self::assertNotContains('Regla periodo uno', $uno->where('activa', true)->pluck('nombre')->all());
            self::assertContains('Regla periodo dos', $repo->consultar(2)->pluck('nombre')->all());
            self::assertNotContains('Regla periodo uno', $repo->consultar(2)->pluck('nombre')->all());
        } finally {
            DB::rollBack();
        }
    }

    public function test_prevalidacion_reconoce_asociacion_y_detecta_inconsistencia_externa(): void
    {
        $migration = require base_path('app/Modules/GestionHorarios/Database/Migrations/2026_11_17_000003_create_oferta_seccion_table.php');
        $migration->up();
        DB::beginTransaction();
        try {
            DB::table('core.periodo_academico')->insert(['id_periodo' => 601, 'codigo' => 'P601']);
            DB::table('core.seccion')->insert(['id_seccion' => 601, 'id_periodo' => 601, 'id_curso_plan' => 1, 'codigo' => 'A', 'capacidad' => 30]);
            DB::table('gestion_horarios.bloque_horario')->insert(['id_bloque' => 601, 'id_periodo' => 601, 'hora_inicio' => '08:00', 'hora_fin' => '09:00']);
            $oferta = DB::table('gestion_horarios.oferta_curso')->insertGetId(['id_periodo' => 601, 'id_curso_plan' => 1, 'tipo_oferta' => 'REGULAR', 'estado' => 'OFERTADA'], 'id_oferta');
            $servicio = new PrevalidacionGeneracion(DB::connection());
            self::assertContains('OFERTA_SECCION_AMBIGUA', array_column($servicio->evaluar(601), 'codigo'));
            DB::table('gestion_horarios.oferta_seccion')->insert(['id_oferta' => $oferta, 'id_seccion' => 601, 'registrada_por' => 1]);
            $salida = $servicio->evaluar(601);
            self::assertNotContains('OFERTA_SECCION_AMBIGUA', array_column($salida, 'codigo'));
            self::assertNotContains('OFERTA_SECCION_INCONSISTENTE', array_column($salida, 'codigo'));
            self::assertContains('POLITICAS_PENDIENTES', array_column($salida, 'codigo'));
            DB::table('core.seccion')->where('id_seccion', 601)->update(['id_curso_plan' => 2]);
            $salida = $servicio->evaluar(601);
            self::assertContains('OFERTA_SECCION_INCONSISTENTE', array_column($salida, 'codigo'));
            $this->actingAs($this->usuario('secretaria'));
            Livewire::test(DistribucionSecciones::class)->set('plan', '1')->set('periodo', '601')
                ->assertDontSee('Hay asociaciones vigentes con referencias inexistentes o datos incompatibles')
                ->assertSee('La conciliación de referencias inconsistentes está pendiente de autorización por escuela');
        } finally {
            DB::rollBack();
            $migration->down();
        }
    }

    public function test_asociacion_livewire_para_director_secretaria_y_denegacion(): void
    {
        $migration = require base_path('app/Modules/GestionHorarios/Database/Migrations/2026_11_17_000003_create_oferta_seccion_table.php');
        $migration->up();
        try {
            DB::table('core.periodo_academico')->insert(['id_periodo' => 501, 'codigo' => 'P501', 'fecha_inicio' => '2028-01-01', 'fecha_fin' => '2028-06-01']);
            DB::table('core.seccion')->insert(['id_seccion' => 501, 'id_periodo' => 501, 'id_curso_plan' => 1, 'codigo' => 'N501', 'capacidad' => 30]);
            $oferta = DB::table('gestion_horarios.oferta_curso')->insertGetId(['id_periodo' => 501, 'id_curso_plan' => 1, 'tipo_oferta' => 'REGULAR', 'estado' => 'OFERTADA'], 'id_oferta');
            $this->actingAs($this->usuario(null));
            Livewire::test(DistribucionSecciones::class)->assertForbidden();
            foreach (['director_escuela', 'secretaria'] as $rol) {
                $this->actingAs($this->usuario($rol));
                $vigente = DB::table('gestion_horarios.oferta_seccion')->where('id_seccion', 501)->where('estado', 'VIGENTE')->value('id_oferta_seccion');
                Livewire::test(DistribucionSecciones::class)->set('plan', '1')->set('periodo', '501')->set('buscar', 'N501')
                    ->set('ofertasSeleccionadas.501', (string) $oferta)->call('asociar', 501, $vigente ? (int) $vigente : null)
                    ->assertHasNoErrors()->assertSee('Asociación registrada')->assertSee('Oferta REGULAR');
            }
            Livewire::test(DistribucionSecciones::class)->set('plan', '1')->set('periodo', '501')->set('buscar', 'N501')
                ->call('asociar', 501, null)->assertHasErrors('asociacion');
            self::assertSame(1, DB::table('gestion_horarios.oferta_seccion')->where('id_seccion', 501)->count());
        } finally {
            $migration->down();
            DB::table('core.seccion')->where('id_seccion', 501)->delete();
            DB::table('gestion_horarios.oferta_curso')->where('id_periodo', 501)->delete();
            DB::table('core.periodo_academico')->where('id_periodo', 501)->delete();
        }
    }

    public function test_mapeo_parcial_con_identificadores_consultados_en_postgresql(): void
    {
        DB::beginTransaction();
        try {
            DB::table('core.seccion')->insert(['id_seccion' => 401, 'id_periodo' => 1, 'id_curso_plan' => 1, 'codigo' => 'A', 'capacidad' => 30]);
            DB::table('gestion_horarios.actividad_academica')->insert(['id_actividad' => 401, 'id_seccion' => 401, 'tipo_actividad' => 'TEORIA', 'dia_semana' => 'LUNES', 'hora_inicio' => '08:00', 'hora_fin' => '09:00']);
            $datos = ['periodo' => (array) DB::table('core.periodo_academico')->where('id_periodo', 1)->first(['id_periodo']),
                'cursos' => [(array) DB::table('core.curso_plan')->where('id_curso_plan', 1)->first(['id_curso_plan'])],
                'ofertas' => [(array) DB::table('gestion_horarios.oferta_curso')->where('id_periodo', 1)->where('id_curso_plan', 1)->first(['id_oferta', 'id_periodo', 'id_curso_plan', 'tipo_oferta'])],
                'secciones' => [(array) DB::table('core.seccion')->where('id_seccion', 401)->first(['id_seccion', 'id_periodo', 'id_curso_plan', 'codigo', 'capacidad'])],
                'actividades' => [(array) DB::table('gestion_horarios.actividad_academica')->where('id_actividad', 401)->first(['id_actividad', 'id_seccion', 'tipo_actividad', 'hora_inicio', 'hora_fin'])],
                'asignaciones' => [], 'docentes' => [], 'declaraciones' => [], 'disponibilidades' => [], 'bloques' => [], 'ambientes' => [], 'bloqueos' => [], 'restricciones' => [], 'demanda' => null, 'horarios' => []];
            $huella = hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $entrada = (new AdaptadorPreparacion)->convertir(['contrato' => 'INSTANTANEA_INSTITUCIONAL_V1', 'huella_sha256' => $huella, 'datos' => $datos]);
            $mapeo = (new MapeoAcademicoParcial)->convertir($entrada);
            self::assertSame('401', $mapeo['secciones'][0]['id']);
            self::assertSame('401', $mapeo['actividades'][0]['id']);
            self::assertSame('T', $mapeo['actividades'][0]['tipo']);
            self::assertSame(60, $mapeo['actividades'][0]['duracion_minutos']);
            self::assertFalse($mapeo['ejecutable']);
            self::assertContains('OFERTA_NO_CONFIRMADA', array_column($mapeo['diagnosticos'], 'codigo'));
        } finally {
            DB::rollBack();
        }
    }

    public function test_calendario_parcial_y_bloques_superpuestos_en_prevalidacion(): void
    {
        $this->actingAs($this->usuario('secretaria'));
        DB::beginTransaction();
        try {
            DB::table('core.periodo_academico')->insert(['id_periodo' => 701, 'codigo' => 'P701', 'fecha_inicio' => '2028-03-01', 'fecha_fin' => '2028-07-01']);
            DB::table('gestion_horarios.bloque_horario')->insert([
                ['id_bloque' => 701, 'id_periodo' => 701, 'nombre' => 'Mañana 1', 'hora_inicio' => '08:00', 'hora_fin' => '10:00'],
                ['id_bloque' => 702, 'id_periodo' => 701, 'nombre' => 'Mañana 2', 'hora_inicio' => '09:00', 'hora_fin' => '11:00'],
            ]);
            $antes = DB::table('gestion_horarios.bloque_horario')->where('id_periodo', 701)->count();
            $resultados = (new PrevalidacionGeneracion(DB::connection()))->evaluar(701);
            self::assertContains('BLOQUES_SUPERPUESTOS', array_column($resultados, 'codigo'));
            self::assertContains('CALENDARIO_PARCIAL', array_column($resultados, 'codigo'));
            Livewire::test(PreparacionGeneracion::class)->assertForbidden();
            self::assertSame($antes, DB::table('gestion_horarios.bloque_horario')->where('id_periodo', 701)->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_preparacion_autorizacion_periodos_demanda_y_diagnosticos(): void
    {
        $this->get('/gestion-horarios/administracion/preparacion-generacion')->assertRedirect('/login');
        $this->actingAs($this->usuario(null))->get('/gestion-horarios/administracion/preparacion-generacion')->assertForbidden();
        Livewire::test(PreparacionGeneracion::class)->assertForbidden();
        foreach (['director_escuela', 'secretaria'] as $rol) {
            $this->actingAs($this->usuario($rol))->get('/gestion-horarios/administracion/preparacion-generacion')->assertForbidden();
        }
        Livewire::test(PreparacionGeneracion::class)->assertForbidden();
        $demanda = (new RepositorioDemandaPostgres(DB::connection()))->vigente(1);
        self::assertSame(2, (int) $demanda->version);
        self::assertTrue((bool) $demanda->requiere_revision);
        $secciones = (new PoblacionAcademicaRepository(DB::connection()))->secciones(1);
        $poblacion = (new PoblacionAcademica)->analizar(1, $secciones, (array) $demanda);
        self::assertSame('NO_IDENTIFICADAS', $poblacion['cohortes']);
        self::assertSame('NO_IDENTIFICADOS', $poblacion['subgrupos']);
        self::assertNull((new RepositorioDemandaPostgres(DB::connection()))->vigente(3));
        $prevalidacion = new PrevalidacionGeneracion(DB::connection());
        self::assertSame('PERIODO_INEXISTENTE', $prevalidacion->evaluar(999999)[0]['codigo']);
        $inicial = array_column($prevalidacion->evaluar(3), 'codigo');
        self::assertContains('SIN_BLOQUES', $inicial);
        self::assertContains('SIN_SECCIONES', $inicial);
        self::assertContains('ALCANCE', $inicial);
        DB::beginTransaction();
        try {
            DB::table('gestion_horarios.bloque_horario')->insert(['id_bloque' => 301, 'id_periodo' => 3, 'nombre' => 'Bloque 301', 'hora_inicio' => '08:00', 'hora_fin' => '09:00']);
            DB::table('core.seccion')->insert(['id_seccion' => 301, 'id_periodo' => 3, 'id_curso_plan' => 1, 'codigo' => 'P301', 'capacidad' => 30]);
            DB::table('gestion_horarios.actividad_academica')->insert(['id_actividad' => 301, 'id_seccion' => 301, 'tipo_actividad' => 'TEORIA', 'dia_semana' => 'LUNES', 'hora_inicio' => '08:00', 'hora_fin' => '09:00']);
            $antes = DB::table('gestion_horarios.actividad_academica')->where('id_actividad', 301)->first();
            $codigos = array_column($prevalidacion->evaluar(3), 'codigo');
            self::assertContains('OFERTA_SECCION_NO_COMPROBABLE', $codigos);
            self::assertContains('ACTIVIDAD_SIN_DOCENTE', $codigos);
            self::assertContains('POBLACION_NO_CONFIRMADA', $codigos);
            self::assertEquals($antes, DB::table('gestion_horarios.actividad_academica')->where('id_actividad', 301)->first());
            DB::table('core.seccion')->where('id_seccion', 301)->update(['capacidad' => null]);
            self::assertContains('CAPACIDAD_INVALIDA', array_column($prevalidacion->evaluar(3), 'codigo'));
            self::assertNotContains('CAPACIDAD_INVALIDA', array_column($prevalidacion->evaluar(2), 'codigo'));
        } finally {
            DB::rollBack();
        }
    }

    public function test_preparacion_informa_fallo_de_consulta_sin_simular_demanda_ausente(): void
    {
        $this->actingAs($this->usuario('director_escuela'));
        DB::beginTransaction();
        try {
            DB::statement('ALTER TABLE gestion_horarios.demanda_instantanea RENAME TO demanda_temporal_prueba');
            Livewire::test(PreparacionGeneracion::class)->assertForbidden();
            $this->expectException(QueryException::class);
            (new RepositorioDemandaPostgres(DB::connection()))->vigente(1);
        } finally {
            DB::rollBack();
        }
    }

    public function test_prevalidacion_es_lectura_detecta_errores_y_advertencias(): void
    {
        $this->actingAs($this->usuario('secretaria'));
        DB::beginTransaction();
        try {
            $servicio = new PrevalidacionGeneracion(DB::connection());
            self::assertSame('PERIODO_INEXISTENTE', $servicio->evaluar(999999)[0]['codigo']);
            self::assertContains('SIN_BLOQUES', array_column($servicio->evaluar(3), 'codigo'));
            DB::table('gestion_horarios.bloque_horario')->insert(['id_bloque' => 201, 'id_periodo' => 3, 'nombre' => 'Bloque 201', 'hora_inicio' => '08:00', 'hora_fin' => '09:00']);
            DB::table('core.seccion')->insert(['id_seccion' => 201, 'id_periodo' => 3, 'id_curso_plan' => 1, 'codigo' => 'P201', 'capacidad' => 30]);
            DB::table('gestion_horarios.actividad_academica')->insert(['id_actividad' => 201, 'id_seccion' => 201, 'tipo_actividad' => 'TEORIA', 'dia_semana' => 'LUNES', 'hora_inicio' => '08:00', 'hora_fin' => '09:00']);
            $antes = DB::table('core.seccion')->where('id_seccion', 201)->first();
            $capturar = true;
            $consultas = [];
            DB::listen(function ($consulta) use (&$capturar, &$consultas): void {
                if ($capturar) {
                    $consultas[] = $consulta->sql;
                }
            });
            $correcta = $servicio->evaluar(3);
            $capturar = false;
            self::assertNotContains('ERROR', array_column($correcta, 'nivel'));
            self::assertContains('OFERTA_SECCION_NO_COMPROBABLE', array_column($correcta, 'codigo'));
            foreach ($consultas as $sql) {
                self::assertMatchesRegularExpression('/^\s*select\b/i', $sql);
            }
            self::assertEquals($antes, DB::table('core.seccion')->where('id_seccion', 201)->first());
            DB::table('core.seccion')->where('id_seccion', 201)->update(['capacidad' => null]);
            DB::table('gestion_horarios.actividad_academica')->where('id_actividad', 201)->update(['hora_fin' => '07:00']);
            DB::table('gestion_horarios.asignacion_docente')->insert([
                ['id_asignacion' => 201, 'id_actividad' => 201, 'id_docente' => 999999, 'estado' => 'CONFIRMADA'],
                ['id_asignacion' => 202, 'id_actividad' => 201, 'id_docente' => 999999, 'estado' => 'CONFIRMADA'],
            ]);
            DB::table('core.seccion')->insert(['id_seccion' => 202, 'id_periodo' => 2, 'id_curso_plan' => 1, 'codigo' => 'P202', 'capacidad' => 30]);
            DB::table('gestion_horarios.actividad_academica')->insert(['id_actividad' => 202, 'id_seccion' => 202, 'tipo_actividad' => 'TEORIA', 'dia_semana' => 'LUNES', 'hora_inicio' => '08:00', 'hora_fin' => '09:00']);
            DB::table('gestion_horarios.horario')->insert(['id_horario' => 201, 'id_periodo' => 3, 'estado' => 'BORRADOR']);
            DB::table('gestion_horarios.detalle_horario')->insert(['id_detalle' => 201, 'id_horario' => 201, 'id_actividad' => 202, 'id_ambiente' => 999999]);
            $errores = array_column($servicio->evaluar(3), 'codigo');
            self::assertContains('CAPACIDAD_INVALIDA', $errores);
            self::assertContains('ACTIVIDAD_INVALIDA', $errores);
            self::assertContains('DOCENTE_INEXISTENTE', $errores);
            self::assertContains('ASIGNACIONES_DUPLICADAS', $errores);
            self::assertContains('AMBIENTE_INEXISTENTE', $errores);
            self::assertContains('PERIODO_INCONSISTENTE', $errores);
            self::assertNotContains('ACTIVIDAD_INVALIDA', array_column($servicio->evaluar(2), 'codigo'));
            Livewire::test(RestriccionesAcademicas::class)->assertForbidden();
        } finally {
            DB::rollBack();
        }
    }
}
