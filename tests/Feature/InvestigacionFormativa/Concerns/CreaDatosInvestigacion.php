<?php

namespace Tests\Feature\InvestigacionFormativa\Concerns;

use App\Models\User;
use App\Modules\InvestigacionFormativa\Database\Seeders\InvestigacionPermisosSeeder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait CreaDatosInvestigacion
{
    /**
     * @return array<string, mixed>
     */
    protected function crearDatosInvestigacion(): array
    {
        DB::table('core.facultad')->insert([
            'id_facultad' => 910,
            'codigo' => 'FAC-IF',
            'nombre' => 'Facultad de Ingeniería',
        ]);

        DB::table('core.escuela_profesional')->insert([
            [
                'id_escuela' => 920,
                'id_facultad' => 910,
                'codigo' => 'EP-IF-A',
                'nombre' => 'Ingeniería Informática',
                'estado' => 'Activo',
            ],
            [
                'id_escuela' => 921,
                'id_facultad' => 910,
                'codigo' => 'EP-IF-B',
                'nombre' => 'Ingeniería de Sistemas',
                'estado' => 'Activo',
            ],
        ]);

        DB::table('core.departamento_academico')->insert([
            'id_departamento' => 930,
            'id_facultad' => 910,
            'codigo' => 'DA-IF',
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
            'id_modalidad' => 940,
            'codigo' => 'ORD',
            'nombre' => 'Ordinario',
            'activo' => true,
        ]);

        DB::table('core.plan_estudios')->insert([
            [
                'id_plan' => 950,
                'id_escuela' => 920,
                'codigo' => 'PLAN-IF-A',
                'nombre' => 'Plan de estudios 2026 A',
                'version' => '2026',
                'anio_aprobacion' => 2026,
                'fecha_vigencia_inicio' => '2026-01-01',
                'es_predeterminado' => true,
                'estado' => 'Vigente',
            ],
            [
                'id_plan' => 951,
                'id_escuela' => 921,
                'codigo' => 'PLAN-IF-B',
                'nombre' => 'Plan de estudios 2026 B',
                'version' => '2026',
                'anio_aprobacion' => 2026,
                'fecha_vigencia_inicio' => '2026-01-01',
                'es_predeterminado' => true,
                'estado' => 'Vigente',
            ],
        ]);

        DB::table('core.area_formacion')->insert([
            'id_area' => 960,
            'codigo' => 'ESP',
            'nombre' => 'Especialidad',
        ]);

        DB::table('core.curso')->insert([
            [
                'id_curso' => 9701,
                'codigo' => 'IF-501',
                'nombre' => 'Investigación Formativa I',
                'creditos' => 3,
                'estado' => 'Activo',
            ],
            [
                'id_curso' => 9702,
                'codigo' => 'IF-502',
                'nombre' => 'Investigación Formativa II',
                'creditos' => 3,
                'estado' => 'Activo',
            ],
        ]);

        DB::table('core.curso_plan')->insert([
            [
                'id_curso_plan' => 9501,
                'id_plan' => 950,
                'id_curso' => 9701,
                'ciclo' => 5,
                'tipo' => 'Obligatorio',
                'id_area' => 960,
                'creditos_requeridos' => 0,
            ],
            [
                'id_curso_plan' => 9502,
                'id_plan' => 951,
                'id_curso' => 9702,
                'ciclo' => 5,
                'tipo' => 'Obligatorio',
                'id_area' => 960,
                'creditos_requeridos' => 0,
            ],
        ]);

        DB::table('core.linea_investigacion')->insert([
            [
                'id_linea' => 9801,
                'id_escuela' => 920,
                'codigo' => 'LIN-A',
                'nombre' => 'Línea A',
                'estado' => 'Activo',
            ],
            [
                'id_linea' => 9802,
                'id_escuela' => 921,
                'codigo' => 'LIN-B',
                'nombre' => 'Línea B',
                'estado' => 'Activo',
            ],
        ]);

        $personas = [
            9001 => ['DNI', '71000001', 'Directora', 'Prueba'],
            9002 => ['DNI', '71000002', 'Coordinadora', 'Prueba'],
            9003 => ['DNI', '71000003', 'Docente', 'A'],
            9004 => ['DNI', '71000004', 'Docente', 'B'],
            9005 => ['DNI', '71000005', 'Alumna', 'A'],
            9006 => ['DNI', '71000006', 'Alumno', 'B'],
            9007 => ['DNI', '71000007', 'Alumno', 'Otra'],
            9008 => ['DNI', '71000008', 'Alumno', 'Retirado'],
            9009 => ['DNI', '71000009', 'Usuario', 'Multiple'],
            9010 => ['DNI', '71000010', 'Académico', 'Inactivo'],
        ];

        foreach ($personas as $idPersona => [$tipo, $numero, $nombres, $apellido]) {
            DB::table('core.persona')->insert([
                'id_persona' => $idPersona,
                'tipo_documento' => $tipo,
                'numero_documento' => $numero,
                'nombres' => $nombres,
                'apellido_paterno' => $apellido,
            ]);
        }

        DB::table('core.docente')->insert([
            [
                'id_docente' => 9801,
                'id_persona' => 9003,
                'codigo_docente' => 'DOC-IF-A',
                'id_departamento' => 930,
                'estado' => 'Activo',
            ],
            [
                'id_docente' => 9802,
                'id_persona' => 9004,
                'codigo_docente' => 'DOC-IF-B',
                'id_departamento' => 930,
                'estado' => 'Activo',
            ],
            [
                'id_docente' => 9803,
                'id_persona' => 9009,
                'codigo_docente' => 'DOC-IF-M',
                'id_departamento' => 930,
                'estado' => 'Activo',
            ],
        ]);

        DB::table('core.estudiante')->insert([
            [
                'id_estudiante' => 9901,
                'codigo_universitario' => 'IF2026001',
                'estado' => 'Activo',
                'id_persona' => 9005,
                'id_escuela' => 920,
                'id_plan' => 950,
                'id_periodo_ingreso' => 202602,
                'id_modalidad_ingreso' => 940,
            ],
            [
                'id_estudiante' => 9902,
                'codigo_universitario' => 'IF2026002',
                'estado' => 'Activo',
                'id_persona' => 9006,
                'id_escuela' => 920,
                'id_plan' => 950,
                'id_periodo_ingreso' => 202602,
                'id_modalidad_ingreso' => 940,
            ],
            [
                'id_estudiante' => 9903,
                'codigo_universitario' => 'IF2026003',
                'estado' => 'Activo',
                'id_persona' => 9007,
                'id_escuela' => 921,
                'id_plan' => 951,
                'id_periodo_ingreso' => 202602,
                'id_modalidad_ingreso' => 940,
            ],
            [
                'id_estudiante' => 9904,
                'codigo_universitario' => 'IF2026004',
                'estado' => 'Activo',
                'id_persona' => 9008,
                'id_escuela' => 920,
                'id_plan' => 950,
                'id_periodo_ingreso' => 202602,
                'id_modalidad_ingreso' => 940,
            ],
            [
                'id_estudiante' => 9905,
                'codigo_universitario' => 'IF2026005',
                'estado' => 'Activo',
                'id_persona' => 9009,
                'id_escuela' => 920,
                'id_plan' => 950,
                'id_periodo_ingreso' => 202602,
                'id_modalidad_ingreso' => 940,
            ],
        ]);

        DB::table('core.seccion')->insert([
            [
                'id_seccion' => 9901,
                'id_periodo' => 202602,
                'id_curso_plan' => 9501,
                'codigo' => 'A',
                'capacidad' => 30,
                'id_docente_responsable' => 9801,
                'estado' => 'Activa',
            ],
            [
                'id_seccion' => 9902,
                'id_periodo' => 202602,
                'id_curso_plan' => 9502,
                'codigo' => 'B',
                'capacidad' => 30,
                'id_docente_responsable' => 9802,
                'estado' => 'Activa',
            ],
        ]);

        $usuariosAcademicos = [
            8801 => 9001,
            8802 => 9003,
            8803 => 9004,
            8804 => 9005,
            8805 => 9006,
            8806 => 9007,
            8807 => 9009,
            8808 => 9002,
            8809 => 9010,
            8810 => 9008,
        ];

        foreach ($usuariosAcademicos as $idUsuario => $idPersona) {
            DB::table('seguridad.usuario')->insert([
                'id_usuario' => $idUsuario,
                'id_persona' => $idPersona,
                'correo_institucional' => "usuario{$idUsuario}@if.test",
                'contrasena_hash' => '$2y$12$prueba.funcional.sin.uso.real',
                'debe_cambiar_contrasena' => false,
                'estado' => 'Activo',
            ]);
        }

        $users = [];

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['director_escuela', 'profesor', 'alumno', 'secretaria'] as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
        }

        $this->seed(InvestigacionPermisosSeeder::class);

        foreach ([
            'director' => ['Director IF', 'director.if@example.test', 8801],
            'docente_a' => ['Docente A', 'docente.a@example.test', 8802],
            'docente_b' => ['Docente B', 'docente.b@example.test', 8803],
            'alumno_a' => ['Alumno A', 'alumno.a@example.test', 8804],
            'alumno_b' => ['Alumno B', 'alumno.b@example.test', 8805],
            'alumno_otra_escuela' => ['Alumno Otra', 'alumno.otra@example.test', 8806],
            'multi' => ['Usuario Múltiple', 'multi.if@example.test', 8807],
            'sin_mapa' => ['Sin Mapa', 'sin.mapa@example.test', null],
            'inactivo' => ['Inactivo', 'inactivo.if@example.test', 8808],
            'academico_inactivo' => ['Académico Inactivo', 'academico.inactivo@example.test', 8809],
            'retirado' => ['Alumno Retirado', 'retirado.if@example.test', 8810],
            'secretaria' => ['Secretaría IF', 'secretaria.if@example.test', null],
        ] as $key => [$name, $email, $idUsuarioSeguridad]) {
            $users[$key] = User::factory()->create([
                'name' => $name,
                'email' => $email,
            ]);

            $roles = match ($key) {
                'director' => ['director_escuela'],
                'docente_a', 'docente_b' => ['profesor'],
                'alumno_a', 'alumno_b', 'alumno_otra_escuela', 'retirado' => ['alumno'],
                'multi' => ['profesor', 'alumno'],
                'secretaria' => ['secretaria'],
                default => [],
            };

            if ($roles !== []) {
                $users[$key]->assignRole($roles);
            }

            if ($idUsuarioSeguridad !== null) {
                DB::table('investigacion_formativa.identidad_usuario')->insert([
                    'user_id' => $users[$key]->id,
                    'id_usuario_seguridad' => $idUsuarioSeguridad,
                    'activo' => $key !== 'inactivo',
                ]);
            }
        }

        DB::table('seguridad.usuario')
            ->where('id_usuario', 8809)
            ->update(['estado' => 'Inactivo']);

        DB::table('investigacion_formativa.director_escuela')->insert([
            'user_id' => $users['director']->id,
            'id_escuela' => 920,
            'activo' => true,
        ]);

        DB::table('investigacion_formativa.participante_seccion')->insert([
            ['id_seccion' => 9901, 'id_estudiante' => 9901, 'estado' => 'activo', 'fuente' => 'test', 'referencia_origen' => 'fixture'],
            ['id_seccion' => 9901, 'id_estudiante' => 9902, 'estado' => 'activo', 'fuente' => 'test', 'referencia_origen' => 'fixture'],
            ['id_seccion' => 9901, 'id_estudiante' => 9904, 'estado' => 'retirado', 'fuente' => 'test', 'referencia_origen' => 'fixture'],
            ['id_seccion' => 9901, 'id_estudiante' => 9905, 'estado' => 'activo', 'fuente' => 'test', 'referencia_origen' => 'fixture'],
            ['id_seccion' => 9902, 'id_estudiante' => 9903, 'estado' => 'activo', 'fuente' => 'test', 'referencia_origen' => 'fixture'],
        ]);

        return [
            'users' => $users,
            'id_escuela_a' => 920,
            'id_escuela_b' => 921,
            'id_seccion_a' => 9901,
            'id_seccion_b' => 9902,
            'id_docente_a' => 9801,
            'id_docente_b' => 9802,
            'id_estudiante_a' => 9901,
            'id_estudiante_b' => 9902,
            'id_estudiante_otra_escuela' => 9903,
            'id_estudiante_retirado' => 9904,
            'id_linea_a' => 9801,
            'id_linea_b' => 9802,
        ];
    }

    protected function crearGruposInvestigacion(): void
    {
        DB::table('investigacion_formativa.grupo')->insert([
            ['id_grupo' => 9951, 'id_seccion' => 9901, 'nombre' => 'Grupo A'],
            ['id_grupo' => 9952, 'id_seccion' => 9902, 'nombre' => 'Grupo B'],
            ['id_grupo' => 9953, 'id_seccion' => 9901, 'nombre' => 'Grupo Retirado'],
            ['id_grupo' => 9954, 'id_seccion' => 9901, 'nombre' => 'Grupo Multi'],
        ]);

        DB::table('investigacion_formativa.grupo_estudiante')->insert([
            ['id_grupo' => 9951, 'id_seccion' => 9901, 'id_estudiante' => 9901, 'es_coordinador' => true],
            ['id_grupo' => 9952, 'id_seccion' => 9902, 'id_estudiante' => 9903, 'es_coordinador' => true],
            ['id_grupo' => 9953, 'id_seccion' => 9901, 'id_estudiante' => 9904, 'es_coordinador' => true],
            ['id_grupo' => 9954, 'id_seccion' => 9901, 'id_estudiante' => 9905, 'es_coordinador' => true],
        ]);
    }

    /**
     * Inserta un tipo de producto reutilizable en las pruebas.
     */
    protected function crearTipoProducto(int $idTipoProducto = 9981, string $nombre = 'Proyecto de prueba'): int
    {
        DB::table('investigacion_formativa.tipo_producto')->insertOrIgnore([
            'id_tipo_producto' => $idTipoProducto,
            'nombre' => $nombre,
        ]);

        return $idTipoProducto;
    }

    /**
     * Deja la sección habilitada y completamente configurada.
     *
     * Los plazos de las tres unidades quedan en el futuro, salvo que se indiquen
     * fechas concretas.
     *
     * @param  array<string, mixed>  $opciones
     */
    protected function configurarSeccionCompleta(int $idSeccion, array $opciones = []): void
    {
        $idDocente = match ($idSeccion) {
            9902 => 9802,
            default => 9801,
        };

        $competenciaId = $opciones['id_competencia'] ?? ($idSeccion === 9902 ? 9863 : 9861);

        if (! DB::table('investigacion_formativa.competencia_seccion')
            ->where('id_seccion', $idSeccion)
            ->exists()) {
            DB::table('investigacion_formativa.competencia_seccion')->insert([
                'id_competencia' => $competenciaId,
                'id_seccion' => $idSeccion,
                'codigo' => 'C1',
                'descripcion' => 'Diseña el problema de investigación.',
                'fuente' => 'Sílabo aprobado 2026',
                'seleccionada' => true,
            ]);
        } else {
            DB::table('investigacion_formativa.competencia_seccion')
                ->where('id_seccion', $idSeccion)
                ->update(['seleccionada' => true]);
        }

        $idRubrica = DB::table('investigacion_formativa.rubrica')->insertGetId([
            'nombre' => 'Rúbrica de integración',
            'descripcion' => 'Rúbrica usada por las pruebas de grupos y títulos.',
            'id_docente_autor' => $idDocente,
        ], 'id_rubrica');

        $idCriterio = DB::table('investigacion_formativa.criterio')->insertGetId([
            'id_rubrica' => $idRubrica,
            'descripcion' => 'Planteamiento del problema',
            'puntaje_maximo' => 4,
            'orden' => 1,
        ], 'id_criterio');

        foreach ([1, 2, 3, 4] as $nivel) {
            DB::table('investigacion_formativa.criterio_nivel')->insert([
                'id_criterio' => $idCriterio,
                'nivel' => $nivel,
                'descripcion' => "Nivel {$nivel}",
                'puntaje' => $nivel,
            ]);
        }

        $fechas = $opciones['fechas'] ?? [
            CarbonImmutable::now('America/Lima')->addDays(30)->utc()->format('Y-m-d H:i:sP'),
            CarbonImmutable::now('America/Lima')->addDays(60)->utc()->format('Y-m-d H:i:sP'),
            CarbonImmutable::now('America/Lima')->addDays(90)->utc()->format('Y-m-d H:i:sP'),
        ];

        foreach (array_values($fechas) as $indice => $fecha) {
            DB::table('investigacion_formativa.entregable')->insert([
                'id_seccion' => $idSeccion,
                'nombre' => 'Unidad '.($indice + 1),
                'fecha_limite' => $fecha,
                'orden' => $indice + 1,
                'id_rubrica' => $idRubrica,
            ]);
        }

        DB::table('investigacion_formativa.seccion_configuracion')->insertOrIgnore([
            'id_seccion' => $idSeccion,
            'habilitada' => true,
            'id_rubrica' => $idRubrica,
            'configurado_en' => now('UTC')->format('Y-m-d H:i:sP'),
            'version' => 1,
        ]);

        DB::table('investigacion_formativa.seccion_configuracion')
            ->where('id_seccion', $idSeccion)
            ->update([
                'habilitada' => true,
                'id_rubrica' => $idRubrica,
                'configurado_en' => now('UTC')->format('Y-m-d H:i:sP'),
            ]);
    }

    protected function crearCompetenciasSeccion(): void
    {
        DB::table('investigacion_formativa.competencia_seccion')->insert([
            [
                'id_competencia' => 9861,
                'id_seccion' => 9901,
                'codigo' => 'C1',
                'descripcion' => 'Diseña el problema de investigación.',
                'fuente' => 'Sílabo aprobado 2026',
                'seleccionada' => false,
            ],
            [
                'id_competencia' => 9862,
                'id_seccion' => 9901,
                'codigo' => 'C2',
                'descripcion' => 'Sustenta el marco teórico.',
                'fuente' => 'Sílabo aprobado 2026',
                'seleccionada' => false,
            ],
            [
                'id_competencia' => 9863,
                'id_seccion' => 9902,
                'codigo' => 'C1',
                'descripcion' => 'Competencia de la otra sección.',
                'fuente' => 'Sílabo aprobado 2026',
                'seleccionada' => false,
            ],
        ]);
    }

    protected function crearEntregaParaSeccion(int $idSeccion): void
    {
        $this->crearTipoProducto();

        DB::table('investigacion_formativa.grupo')->insert([
            'id_grupo' => 9971,
            'id_seccion' => $idSeccion,
            'nombre' => 'Grupo con entrega',
        ]);

        DB::table('investigacion_formativa.proyecto')->insert([
            'id_proyecto' => 9972,
            'id_grupo' => 9971,
            'id_linea' => 9801,
            'id_tipo_producto' => 9981,
            'titulo' => 'Proyecto con entrega',
            'estado' => 'Registrado',
            'version' => 1,
        ]);

        DB::table('investigacion_formativa.entregable')->insert([
            'id_entregable' => 9973,
            'id_seccion' => $idSeccion,
            'nombre' => 'Avance 1',
            'orden' => 1,
        ]);

        $idModulo = DB::table('seguridad.modulo_sistema')
            ->where('codigo', 'investigacion_formativa')
            ->value('id_modulo');

        $idArchivo = DB::table('comun.archivo')->insertGetId([
            'id_modulo' => $idModulo,
            'nombre_original' => 'avance.pdf',
            'ruta_almacenamiento' => 'investigacion-formativa/test/avance.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 10,
            'hash_sha256' => str_repeat('b', 64),
            'subido_por' => 8802,
        ], 'id_archivo');

        DB::table('investigacion_formativa.entrega')->insert([
            'id_proyecto' => 9972,
            'id_entregable' => 9973,
            'version' => 1,
            'id_archivo' => $idArchivo,
        ]);
    }
}
