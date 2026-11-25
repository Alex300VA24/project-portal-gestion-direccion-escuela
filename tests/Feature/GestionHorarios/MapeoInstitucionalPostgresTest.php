<?php

namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Engine\AdaptadorPreparacion;
use App\Modules\GestionHorarios\Engine\MapeoAcademicoParcial;
use App\Modules\GestionHorarios\Services\InstantaneaGeneracion;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class MapeoInstitucionalPostgresTest extends TestCase
{
    public function test_instantanea_persistida_con_dos_planes_genera_mapeo_no_ejecutable(): void
    {
        if (getenv('HORARIOS_27B1_POSTGRES_AISLADO') !== '1') {
            $this->markTestSkipped('Requiere PostgreSQL aislado de la etapa 27B.1.');
        }
        $clave = getenv('HORARIOS_27B1_PASSWORD');
        if (! is_string($clave) || $clave === '') {
            throw new RuntimeException('Falta la credencial exclusiva de prueba.');
        }
        config(['database.default' => 'gh_27b1', 'database.connections.gh_27b1' => [
            'driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55470, 'database' => 'horarios_27b1_test', 'username' => 'horarios_test', 'password' => $clave, 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public',
        ]]);
        $db = DB::connection();
        $id = $db->selectOne("select current_database() as base,current_user as usuario,inet_server_port() as puerto,current_setting('server_version_num') as version");
        if ($id->base !== 'horarios_27b1_test' || $id->usuario !== 'horarios_test' || (int) $id->puerto !== 5432 || (int) $id->version < 170000 || (int) $id->version >= 180000 || $db->selectOne("select exists(select 1 from pg_namespace where nspname in ('core','gestion_horarios')) as existe")->existe) {
            throw new RuntimeException('Base PostgreSQL aislada y vacía no verificada.');
        }
        $db->unprepared(<<<'SQL'
CREATE SCHEMA core;
CREATE SCHEMA gestion_horarios;
CREATE TABLE core.periodo_academico(id_periodo bigint primary key,codigo text,fecha_inicio date,fecha_fin date,estado text);
CREATE TABLE core.plan_estudios(id_plan bigint primary key,id_escuela bigint);
CREATE TABLE core.curso(id_curso bigint primary key,codigo text,nombre text,horas_teoria int,horas_practica int,horas_laboratorio int);
CREATE TABLE core.curso_plan(id_curso_plan bigint primary key,id_curso bigint,id_plan bigint,ciclo int);
CREATE TABLE core.seccion(id_seccion bigint primary key,id_periodo bigint,id_curso_plan bigint,codigo text,capacidad int,id_docente_responsable bigint,estado text);
CREATE TABLE core.docente(id_docente bigint primary key,estado text,horas_maximas int);
CREATE TABLE core.ambiente(id_ambiente bigint primary key,codigo text,nombre text,tipo text,capacidad int,estado text);
CREATE TABLE gestion_horarios.oferta_curso(id_oferta bigint primary key,id_periodo bigint,id_curso_plan bigint,tipo_oferta text,docentes_minimos int,estado text);
CREATE TABLE gestion_horarios.oferta_seccion(id_oferta_seccion bigint primary key,id_oferta bigint,id_seccion bigint,estado text);
CREATE TABLE gestion_horarios.actividad_academica(id_actividad bigint primary key,id_seccion bigint,tipo_actividad text,dia_semana text,hora_inicio time,hora_fin time,minutos_sesion int);
CREATE TABLE gestion_horarios.asignacion_docente(id_asignacion bigint primary key,id_actividad bigint,id_docente bigint,estado text,id_apertura bigint);
CREATE TABLE gestion_horarios.declaracion_disponibilidad(id_declaracion bigint primary key,id_periodo bigint,id_docente bigint,estado text,tratamiento_regular text);
CREATE TABLE gestion_horarios.disponibilidad_docente(id_disponibilidad bigint primary key,id_periodo bigint,id_docente bigint,id_declaracion bigint,dia_semana text,id_bloque bigint);
CREATE TABLE gestion_horarios.bloque_horario(id_bloque bigint primary key,id_periodo bigint,hora_inicio time,hora_fin time);
CREATE TABLE gestion_horarios.ambiente_bloqueo(id_bloqueo bigint primary key,id_periodo bigint,id_ambiente bigint,fecha_inicio date,fecha_fin date,dia_semana text,hora_inicio time,hora_fin time);
CREATE TABLE gestion_horarios.restriccion(id_restriccion bigint primary key,codigo text,tipo text,severidad text,activa boolean);
CREATE TABLE gestion_horarios.detalle_restriccion(id_detalle_restriccion bigint primary key,id_restriccion bigint,ambito text,id_periodo bigint,id_docente bigint,id_ambiente bigint,prioridad int);
CREATE TABLE gestion_horarios.demanda_instantanea(id_demanda bigint primary key,id_periodo bigint,version int,huella text,recibida_en timestamp);
CREATE TABLE gestion_horarios.demanda_curso(id_demanda bigint,id_curso_plan bigint,total int,manana int,tarde int);
CREATE TABLE gestion_horarios.horario(id_horario bigint primary key,id_periodo bigint,estado text,version int,id_demanda bigint);
INSERT INTO core.periodo_academico VALUES(1,'PERIODO-PRUEBA','2026-03-01','2026-07-30','ACTIVO');
INSERT INTO core.plan_estudios VALUES(10,7),(20,7);
INSERT INTO core.curso VALUES(100,'CURSO-A','Curso A',2,0,0),(200,'CURSO-B','Curso B',0,2,0),(300,'SIN-SECCION','Curso sin sección',2,0,0);
INSERT INTO core.curso_plan VALUES(101,100,10,1),(202,200,20,3),(303,300,20,5);
INSERT INTO gestion_horarios.oferta_curso VALUES(11,1,101,'REGULAR',1,'OFERTADA'),(22,1,202,'REGULAR',1,'OFERTADA'),(33,1,303,'REGULAR',1,'PLANIFICADA');
INSERT INTO core.seccion VALUES(111,1,101,'A',30,NULL,'ACTIVA'),(112,1,101,'B',30,NULL,'ACTIVA'),(222,1,202,'A',25,NULL,'ACTIVA');
INSERT INTO gestion_horarios.oferta_seccion VALUES(1,11,111,'VIGENTE'),(2,11,112,'VIGENTE'),(3,22,222,'VIGENTE');
INSERT INTO core.docente VALUES(41,'ACTIVO',20);
INSERT INTO core.ambiente VALUES(51,'A51','Aula 51','AULA',30,'ACTIVO'),(52,'L52','Laboratorio 52','LAB',15,'ACTIVO');
INSERT INTO gestion_horarios.actividad_academica VALUES(61,111,'TEORIA','LUNES','09:00','10:00',60),(62,112,'TEORIA','MARTES','09:00','10:00',60),(63,222,'PRACTICA','LUNES','10:00','11:00',60);
INSERT INTO gestion_horarios.asignacion_docente VALUES(71,61,41,'CONFIRMADA',NULL);
INSERT INTO gestion_horarios.declaracion_disponibilidad VALUES(81,1,41,'PRESENTADA','ESTRICTA');
INSERT INTO gestion_horarios.bloque_horario VALUES(91,1,'09:00','10:00');
INSERT INTO gestion_horarios.disponibilidad_docente VALUES(92,1,41,81,'LUNES',91);
SQL);
        $antes = $db->table('gestion_horarios.actividad_academica')->orderBy('id_actividad')->get()->toArray();
        $instantanea = (new InstantaneaGeneracion($db))->construir(1);
        $preparacion = (new AdaptadorPreparacion)->convertir($instantanea);
        $mapeo = (new MapeoAcademicoParcial)->convertir($preparacion);
        self::assertSame('MAPEO_ACADEMICO_PARCIAL_V1', $mapeo['contrato']);
        self::assertFalse($mapeo['ejecutable']);
        self::assertSame(['10', '20'], array_column($mapeo['planes'], 'id'));
        self::assertSame(['7', '7'], array_column($mapeo['planes'], 'escuela_id'));
        self::assertSame(['111', '112', '222'], array_column($mapeo['secciones'], 'id'));
        self::assertSame(['11', '11', '22'], array_column($mapeo['secciones'], 'oferta_id'));
        self::assertSame(['10', '10', '20'], array_column($mapeo['secciones'], 'plan_id'));
        self::assertSame(['101', '101', '202'], array_column($mapeo['secciones'], 'curso_plan_id'));
        self::assertSame(['61', '62', '63'], array_column($mapeo['actividades'], 'id'));
        self::assertSame(['41'], $mapeo['actividades'][0]['docentes_ids']);
        self::assertSame([], $mapeo['actividades'][1]['docentes_ids']);
        self::assertContains('OFERTA_SIN_SECCION_VIGENTE', array_column($mapeo['diagnosticos'], 'codigo'));
        self::assertContains('DOCENTE_CONFIRMADO_AUSENTE', array_column($mapeo['diagnosticos'], 'codigo'));
        self::assertContains('SESION_FISICA_COMPARTIDA_SIN_FUENTE', array_column($mapeo['diagnosticos'], 'codigo'));
        $diagnosticos = array_column($mapeo['clasificacion_diagnosticos'], 'categoria', 'codigo');
        self::assertSame('AUSENTE', $diagnosticos['DOCENTE_CONFIRMADO_AUSENTE']);
        self::assertSame('NO_SOPORTADA', $diagnosticos['SESION_FISICA_COMPARTIDA_SIN_FUENTE']);
        self::assertSame('AUSENTE', $diagnosticos['CURSO_SIN_SECCION']);
        self::assertSame($instantanea, (new InstantaneaGeneracion($db))->construir(1));
        self::assertEquals($antes, $db->table('gestion_horarios.actividad_academica')->orderBy('id_actividad')->get()->toArray());
        try {
            (new AdaptadorPreparacion)->paraMotor($preparacion);
            self::fail('Los datos institucionales incompletos no deben llegar al motor.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
    }
}
