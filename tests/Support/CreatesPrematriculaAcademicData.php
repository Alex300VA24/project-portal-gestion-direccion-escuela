<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

trait CreatesPrematriculaAcademicData
{
    protected function createPrematriculaAcademicData(): void
    {
        DB::table('core.pais')->insert([
            'id_pais' => 1,
            'nombre' => 'Perú',
        ]);
        DB::table('core.ubigeo_departamento')->insert([
            'id_departamento' => 1,
            'nombre' => 'La Libertad',
        ]);
        DB::table('core.ubigeo_provincia')->insert([
            'id_provincia' => 1,
            'id_departamento' => 1,
            'nombre' => 'Trujillo',
        ]);
        DB::table('core.ubigeo_distrito')->insert([
            'id_distrito' => 1,
            'id_provincia' => 1,
            'nombre' => 'Trujillo',
        ]);
        DB::table('core.persona')->insert([
            [
                'id_persona' => 1001,
                'tipo_documento' => 'DNI',
                'numero_documento' => '70000001',
                'nombres' => 'Estudiante',
                'apellido_paterno' => 'Prueba',
                'id_pais_nacionalidad' => 1,
                'id_distrito_domicilio' => 1,
            ],
            [
                'id_persona' => 1002,
                'tipo_documento' => 'DNI',
                'numero_documento' => '70000002',
                'nombres' => 'Revisor',
                'apellido_paterno' => 'Prueba',
                'id_pais_nacionalidad' => 1,
                'id_distrito_domicilio' => 1,
            ],
        ]);
        DB::table('core.facultad')->insert([
            'id_facultad' => 10,
            'codigo' => 'FAC-ING',
            'nombre' => 'Facultad de Ingeniería',
        ]);
        DB::table('core.escuela_profesional')->insert([
            'id_escuela' => 20,
            'id_facultad' => 10,
            'codigo' => 'EP-INF',
            'nombre' => 'Ingeniería Informática',
            'estado' => 'Activo',
        ]);
        DB::table('core.departamento_academico')->insert([
            'id_departamento' => 30,
            'id_facultad' => 10,
            'codigo' => 'DA-INF',
            'nombre' => 'Informática',
        ]);
        DB::table('core.periodo_academico')->insert([
            'id_periodo' => 202602,
            'codigo' => '2026-II',
            'anio' => 2026,
            'tipo' => 'II',
            'fecha_inicio' => '2026-08-01',
            'fecha_fin' => '2026-12-20',
            'numero_semanas' => 17,
            'estado' => 'Activo',
        ]);
        DB::table('core.modalidad_ingreso')->insert([
            'id_modalidad' => 40,
            'codigo' => 'ORD',
            'nombre' => 'Ordinario',
            'activo' => true,
        ]);
        DB::table('core.plan_estudios')->insert([
            'id_plan' => 50,
            'id_escuela' => 20,
            'codigo' => 'PLAN-2026',
            'nombre' => 'Plan de estudios 2026',
            'version' => '2026',
            'anio_aprobacion' => 2026,
            'fecha_vigencia_inicio' => '2026-01-01',
            'es_predeterminado' => true,
            'estado' => 'Vigente',
        ]);
        DB::table('core.area_formacion')->insert([
            'id_area' => 60,
            'codigo' => 'ESP',
            'nombre' => 'Especialidad',
        ]);
        DB::table('core.curso')->insert([
            [
                'id_curso' => 701,
                'codigo' => 'CP-501',
                'nombre' => 'Fundamentos de Programación',
                'creditos' => 3,
                'estado' => 'Activo',
            ],
            [
                'id_curso' => 702,
                'codigo' => 'CP-502',
                'nombre' => 'Programación Avanzada',
                'creditos' => 4,
                'estado' => 'Activo',
            ],
        ]);
        DB::table('core.curso_plan')->insert([
            [
                'id_curso_plan' => 501,
                'id_plan' => 50,
                'id_curso' => 701,
                'ciclo' => 1,
                'tipo' => 'Obligatorio',
                'id_area' => 60,
                'creditos_requeridos' => 0,
            ],
            [
                'id_curso_plan' => 502,
                'id_plan' => 50,
                'id_curso' => 702,
                'ciclo' => 2,
                'tipo' => 'Obligatorio',
                'id_area' => 60,
                'creditos_requeridos' => 3,
            ],
        ]);
        DB::table('core.estudiante')->insert([
            'id_estudiante' => 1001,
            'id_persona' => 1001,
            'codigo_universitario' => 'E2026001',
            'id_escuela' => 20,
            'id_plan' => 50,
            'id_periodo_ingreso' => 202602,
            'id_modalidad_ingreso' => 40,
            'estado' => 'Activo',
        ]);
        DB::table('core.seccion')->insert([
            'id_seccion' => 901,
            'id_periodo' => 202602,
            'id_curso_plan' => 501,
            'codigo' => 'A',
            'capacidad' => 30,
            'id_docente_responsable' => null,
            'estado' => 'Activa',
        ]);
        DB::table('seguridad.usuario')->insert([
            'id_usuario' => 801,
            'id_persona' => 1002,
            'correo_institucional' => 'revisor@example.test',
            'contrasena_hash' => '$2y$12$prueba.funcional.sin.uso.real',
            'debe_cambiar_contrasena' => false,
            'estado' => 'Activo',
        ]);
    }
}
