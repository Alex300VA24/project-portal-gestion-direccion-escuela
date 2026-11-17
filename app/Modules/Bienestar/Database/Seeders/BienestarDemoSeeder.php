<?php

namespace App\Modules\Bienestar\Database\Seeders;

use App\Models\User;
use App\Modules\Bienestar\Support\BienestarPermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class BienestarDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles y permisos Spatie para el módulo Bienestar
        $this->seedPermissions();

        // 2. Estructura Core mínima (facultad, escuela, departamento, periodo, modalidad, plan)
        $idsCore = $this->seedCoreStructure();

        // 3. Personas y Usuarios de las 4 cuentas del sistema
        $usuarios = $this->seedUsuariosYPersonas($idsCore);

        // 4. Catálogos propios del esquema Bienestar
        $catalogo = $this->seedCatalogosBienestar();

        // 5. Artículos normativos iniciales
        $this->seedArticulosInformativos($usuarios['secretaria']->id_usuario);

        // 6. Alertas demostrativas iniciales y un caso de prueba
        $this->seedAlertasYCasosDemo($usuarios, $catalogo, $idsCore['periodo_codigo']);
    }

    private function seedPermissions(): void
    {
        foreach (BienestarPermissions::ALL as $permName) {
            Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);
        }

        foreach (BienestarPermissions::ROLE_PERMISSIONS as $roleName => $perms) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
            $role->givePermissionTo($perms);
        }
    }

    private function seedCoreStructure(): array
    {
        $idFacultad = DB::table('core.facultad')->where('codigo', 'FAC-BIE')->value('id_facultad');
        if (! $idFacultad) {
            $idFacultad = DB::table('core.facultad')->insertGetId([
                'codigo' => 'FAC-BIE',
                'nombre' => 'Facultad de Ciencias Físicas y Matemáticas',
            ], 'id_facultad');
        }

        $idEscuela = DB::table('core.escuela_profesional')->where('codigo', 'EP-BIE')->value('id_escuela');
        if (! $idEscuela) {
            $idEscuela = DB::table('core.escuela_profesional')->insertGetId([
                'id_facultad' => $idFacultad,
                'codigo' => 'EP-BIE',
                'nombre' => 'Escuela Profesional de Informática',
                'estado' => 'ACTIVO',
            ], 'id_escuela');
        }

        $idDepartamento = DB::table('core.departamento_academico')->where('codigo', 'DEP-BIE')->value('id_departamento');
        if (! $idDepartamento) {
            $idDepartamento = DB::table('core.departamento_academico')->insertGetId([
                'id_facultad' => $idFacultad,
                'codigo' => 'DEP-BIE',
                'nombre' => 'Departamento Académico de Informática',
            ], 'id_departamento');
        }

        $idPeriodo = DB::table('core.periodo_academico')->where('codigo', '2026-I')->value('id_periodo');
        if (! $idPeriodo) {
            $idPeriodo = DB::table('core.periodo_academico')->insertGetId([
                'codigo' => '2026-I',
                'fecha_inicio' => '2026-03-15',
                'fecha_fin' => '2026-07-20',
                'estado' => 'ACTIVO',
                'anio' => 2026,
                'tipo' => 'REGULAR',
                'numero_semanas' => 16,
            ], 'id_periodo');
        }

        $idModalidad = DB::table('core.modalidad_ingreso')->where('codigo', 'MOD-BIE')->value('id_modalidad');
        if (! $idModalidad) {
            $idModalidad = DB::table('core.modalidad_ingreso')->insertGetId([
                'codigo' => 'MOD-BIE',
                'nombre' => 'Ordinario Bienestar',
                'descripcion' => 'Examen ordinario de admisión',
                'activo' => true,
            ], 'id_modalidad');
        }

        $idPlan = DB::table('core.plan_estudios')->where('codigo', 'PLAN-2020')->value('id_plan');
        if (! $idPlan) {
            $idPlan = DB::table('core.plan_estudios')->insertGetId([
                'id_escuela' => $idEscuela,
                'codigo' => 'PLAN-2020',
                'nombre' => 'Plan de Estudios 2020',
                'version' => '2020',
                'anio_aprobacion' => 2020,
                'fecha_vigencia_inicio' => '2020-01-01',
                'es_predeterminado' => true,
                'estado' => 'ACTIVO',
            ], 'id_plan');
        }

        return [
            'id_facultad' => $idFacultad,
            'id_escuela' => $idEscuela,
            'id_departamento' => $idDepartamento,
            'id_periodo' => $idPeriodo,
            'id_modalidad' => $idModalidad,
            'id_plan' => $idPlan,
            'periodo_codigo' => '2026-I',
        ];
    }

    private function seedUsuariosYPersonas(array $core): array
    {
        $accounts = [
            'director' => [
                'email' => 'director@portal.local',
                'name' => 'Director de Escuela',
                'role' => 'director_escuela',
                'dni' => '10000001',
                'nombres' => 'Carlos',
                'paterno' => 'Director',
                'materno' => 'Escuela',
                'es_docente' => true,
                'codigo_docente' => 'DOC-DIR-001',
            ],
            'secretaria' => [
                'email' => 'secretaria@portal.local',
                'name' => 'Secretaría de Bienestar',
                'role' => 'secretaria',
                'dni' => '10000002',
                'nombres' => 'Ana',
                'paterno' => 'Secretaria',
                'materno' => 'Bienestar',
                'es_docente' => false,
            ],
            'profesor' => [
                'email' => 'profesor@portal.local',
                'name' => 'Profesor Demo',
                'role' => 'profesor',
                'dni' => '10000003',
                'nombres' => 'Roberto',
                'paterno' => 'Profesor',
                'materno' => 'Docente',
                'es_docente' => true,
                'codigo_docente' => 'DOC-PRF-001',
            ],
            'alumno' => [
                'email' => 'alumno@portal.local',
                'name' => 'Alumno Demo',
                'role' => 'alumno',
                'dni' => '10000004',
                'nombres' => 'Estudiante',
                'paterno' => 'Alumno',
                'materno' => 'Demo',
                'es_estudiante' => true,
                'codigo_universitario' => '1021400121',
            ],
        ];

        $result = [];

        foreach ($accounts as $key => $data) {
            // Public User (Breeze)
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('Portal2026!'),
                ]
            );
            $user->assignRole($data['role']);

            // Persona
            $idPersona = DB::table('core.persona')->where('numero_documento', $data['dni'])->value('id_persona');
            if (! $idPersona) {
                $idPersona = DB::table('core.persona')->insertGetId([
                    'tipo_documento' => 'DNI',
                    'numero_documento' => $data['dni'],
                    'nombres' => $data['nombres'],
                    'apellido_paterno' => $data['paterno'],
                    'apellido_materno' => $data['materno'],
                    'correo_personal' => $data['email'],
                    'creado_en' => now(),
                ], 'id_persona');
            }

            // Seguridad Usuario
            $idSegUsuario = DB::table('seguridad.usuario')->where('correo_institucional', $data['email'])->value('id_usuario');
            if (! $idSegUsuario) {
                $idSegUsuario = DB::table('seguridad.usuario')->insertGetId([
                    'correo_institucional' => $data['email'],
                    'contrasena_hash' => Hash::make('Portal2026!'),
                    'estado' => 'ACTIVO',
                    'id_persona' => $idPersona,
                    'debe_cambiar_contrasena' => false,
                    'creado_en' => now(),
                ], 'id_usuario');
            }

            // Docente
            $idDocente = null;
            if (! empty($data['es_docente'])) {
                $idDocente = DB::table('core.docente')->where('id_persona', $idPersona)->value('id_docente');
                if (! $idDocente) {
                    $idDocente = DB::table('core.docente')->insertGetId([
                        'id_persona' => $idPersona,
                        'codigo_docente' => $data['codigo_docente'],
                        'id_departamento' => $core['id_departamento'],
                        'categoria' => 'ASOCIADO',
                        'condicion' => 'NOMBRADO',
                        'dedicacion' => 'TIEMPO_COMPLETO',
                        'estado' => 'ACTIVO',
                    ], 'id_docente');
                }
            }

            // Estudiante
            $idEstudiante = null;
            if (! empty($data['es_estudiante'])) {
                $idEstudiante = DB::table('core.estudiante')->where('id_persona', $idPersona)->value('id_estudiante');
                if (! $idEstudiante) {
                    $idEstudiante = DB::table('core.estudiante')->insertGetId([
                        'id_persona' => $idPersona,
                        'codigo_universitario' => $data['codigo_universitario'],
                        'id_escuela' => $core['id_escuela'],
                        'id_plan' => $core['id_plan'],
                        'id_periodo_ingreso' => $core['id_periodo'],
                        'id_modalidad_ingreso' => $core['id_modalidad'],
                        'estado' => 'REGULAR',
                    ], 'id_estudiante');
                }
            }

            $result[$key] = (object) [
                'user' => $user,
                'id_usuario' => $idSegUsuario,
                'id_persona' => $idPersona,
                'id_docente' => $idDocente,
                'id_estudiante' => $idEstudiante,
            ];
        }

        // Estudiantes adicionales para casos demostrativos
        $extraEstudiantes = [
            ['codigo' => '1021400222', 'dni' => '10000005', 'nombres' => 'Carlos', 'paterno' => 'Méndez', 'materno' => 'Soto'],
            ['codigo' => '1021400323', 'dni' => '10000006', 'nombres' => 'Lucía', 'paterno' => 'Paredes', 'materno' => 'Ruiz'],
            ['codigo' => '1021400424', 'dni' => '10000007', 'nombres' => 'Juan', 'paterno' => 'Pérez', 'materno' => 'Castro'],
        ];

        foreach ($extraEstudiantes as $extra) {
            $idPers = DB::table('core.persona')->where('numero_documento', $extra['dni'])->value('id_persona');
            if (! $idPers) {
                $idPers = DB::table('core.persona')->insertGetId([
                    'tipo_documento' => 'DNI',
                    'numero_documento' => $extra['dni'],
                    'nombres' => $extra['nombres'],
                    'apellido_paterno' => $extra['paterno'],
                    'apellido_materno' => $extra['materno'],
                    'correo_personal' => "est_{$extra['codigo']}@unt.edu.pe",
                    'creado_en' => now(),
                ], 'id_persona');
            }

            $idEst = DB::table('core.estudiante')->where('id_persona', $idPers)->value('id_estudiante');
            if (! $idEst) {
                $idEst = DB::table('core.estudiante')->insertGetId([
                    'id_persona' => $idPers,
                    'codigo_universitario' => $extra['codigo'],
                    'id_escuela' => $core['id_escuela'],
                    'id_plan' => $core['id_plan'],
                    'id_periodo_ingreso' => $core['id_periodo'],
                    'id_modalidad_ingreso' => $core['id_modalidad'],
                    'estado' => 'REGULAR',
                ], 'id_estudiante');
            }

            $result['extra_'.$extra['codigo']] = (object) [
                'id_persona' => $idPers,
                'id_estudiante' => $idEst,
                'codigo' => $extra['codigo'],
            ];
        }

        return $result;
    }

    private function seedCatalogosBienestar(): array
    {
        // Tipos de caso
        $tipoAcademico = DB::table('bienestar.tipo_caso')->where('nombre', 'Riesgo Academico')->value('id_tipo_caso');
        if (! $tipoAcademico) {
            $tipoAcademico = DB::table('bienestar.tipo_caso')->insertGetId([
                'nombre' => 'Riesgo Academico',
                'descripcion' => 'Casos derivados de reiterancia en desaprobacion o inasistencias',
            ], 'id_tipo_caso');
        }

        $tipoSocio = DB::table('bienestar.tipo_caso')->where('nombre', 'Apoyo Socioeconomico')->value('id_tipo_caso');
        if (! $tipoSocio) {
            $tipoSocio = DB::table('bienestar.tipo_caso')->insertGetId([
                'nombre' => 'Apoyo Socioeconomico',
                'descripcion' => 'Evaluacion socioeconomica y beneficios de bienestar',
            ], 'id_tipo_caso');
        }

        // Especialidades
        $espPsic = DB::table('bienestar.especialidad')->where('nombre', 'Psicologia')->value('id_especialidad');
        if (! $espPsic) {
            $espPsic = DB::table('bienestar.especialidad')->insertGetId(['nombre' => 'Psicologia'], 'id_especialidad');
        }

        $espMed = DB::table('bienestar.especialidad')->where('nombre', 'Medicina General')->value('id_especialidad');
        if (! $espMed) {
            $espMed = DB::table('bienestar.especialidad')->insertGetId(['nombre' => 'Medicina General'], 'id_especialidad');
        }

        $espTrabajo = DB::table('bienestar.especialidad')->where('nombre', 'Trabajo Social')->value('id_especialidad');
        if (! $espTrabajo) {
            $espTrabajo = DB::table('bienestar.especialidad')->insertGetId(['nombre' => 'Trabajo Social'], 'id_especialidad');
        }

        // Especialistas de catalogo externo
        $especialista1 = DB::table('bienestar.especialista')->where('registro_profesional', 'CPP-14523')->value('id_especialista');
        if (! $especialista1) {
            $especialista1 = DB::table('bienestar.especialista')->insertGetId([
                'id_especialidad' => $espPsic,
                'registro_profesional' => 'CPP-14523',
                'activo' => true,
                'nombres' => 'Silvia',
                'apellidos' => 'Vargas Mendoza',
                'contacto' => 'svargas@unt.edu.pe / 944123890',
                'institucion' => 'Dirección de Bienestar UNT',
            ], 'id_especialista');
        }

        $especialista2 = DB::table('bienestar.especialista')->where('registro_profesional', 'CMP-29841')->value('id_especialista');
        if (! $especialista2) {
            $especialista2 = DB::table('bienestar.especialista')->insertGetId([
                'id_especialidad' => $espMed,
                'registro_profesional' => 'CMP-29841',
                'activo' => true,
                'nombres' => 'Jorge',
                'apellidos' => 'Alva Castro',
                'contacto' => 'jalva@unt.edu.pe / 944567123',
                'institucion' => 'Centro Médico Universitario UNT',
            ], 'id_especialista');
        }

        // Beneficios
        DB::table('bienestar.beneficio')->updateOrInsert(
            ['nombre' => 'Comedor Universitario'],
            [
                'descripcion' => 'Ración alimentaria subvencionada para estudiantes en situación de vulnerabilidad',
                'activo' => true,
            ]
        );

        DB::table('bienestar.beneficio')->updateOrInsert(
            ['nombre' => 'Beca de Exoneración de Matrícula'],
            [
                'descripcion' => 'Subvención de tasas de matrícula por rendimiento académico o vulnerabilidad socioeconómica',
                'activo' => true,
            ]
        );

        return [
            'id_tipo_academico' => $tipoAcademico,
            'id_tipo_socio' => $tipoSocio,
            'id_especialista_psic' => $especialista1,
            'id_especialista_med' => $especialista2,
        ];
    }

    private function seedArticulosInformativos(int $autorId): void
    {
        $articulos = [
            [
                'titulo' => 'Tercera y cuarta matrícula: causales de separación',
                'resumen' => 'Conoce los efectos académicos de desaprobar una misma materia por tercera o cuarta vez.',
                'detalle' => '<p>De acuerdo con la legislación universitaria, el estudiante que desaprueba la misma materia por tercera vez es separado temporalmente de la universidad por el periodo de un año. Al retornar a sus estudios, solo puede matricularse en esa materia desaprobada para poder continuar con su plan formativo. En caso de desaprobarla por cuarta vez, procede el retiro definitivo de la universidad.</p><p class="text-muted"><small>Nota: Pendiente de verificar con el Reglamento Académico vigente de la UNT.</small></p>',
                'fuente_normativa' => 'Ley Universitaria 30220, Art. 102 [Pendiente de verificar con el Reglamento Académico vigente de la UNT]',
                'fecha_ultima_revision' => '2026-03-01',
                'estado' => 'publicado',
            ],
            [
                'titulo' => 'Carga mínima de créditos y condición de estudiante regular',
                'resumen' => 'Requisitos de matriculación crediticia semestral para conservar la regularidad estudiantil.',
                'detalle' => '<p>Para conservar la condición de estudiante regular se exige estar matriculado en un mínimo de doce (12) créditos por semestre académico, a menos que al estudiante le falten menos créditos para culminar de forma definitiva su carrera profesional.</p><p class="text-muted"><small>Nota: Pendiente de verificar con el Reglamento Académico vigente de la UNT.</small></p>',
                'fuente_normativa' => 'Ley Universitaria 30220, Art. 99.8 [Pendiente de verificar con el Reglamento Académico vigente de la UNT]',
                'fecha_ultima_revision' => '2026-03-01',
                'estado' => 'publicado',
            ],
            [
                'titulo' => 'Reserva de matrícula: requisitos y duración máxima',
                'resumen' => 'Procedimiento para solicitar reserva de matrícula por razones de trabajo, salud o causa sustentada.',
                'detalle' => '<p>Los estudiantes pueden solicitar la reserva de matrícula por razones de trabajo u otra causa debidamente sustentada. La reserva no puede exceder en ningún caso de tres (3) años académicos, sean estos consecutivos o alternos.</p><p class="text-muted"><small>Nota: Pendiente de verificar con el Reglamento Académico vigente de la UNT.</small></p>',
                'fuente_normativa' => 'Ley Universitaria 30220, Art. 100.11 [Pendiente de verificar con el Reglamento Académico vigente de la UNT]',
                'fecha_ultima_revision' => '2026-03-01',
                'estado' => 'publicado',
            ],
            [
                'titulo' => 'Régimen disciplinario y sanciones aplicables',
                'resumen' => 'Tipificación general de sanciones disciplinarias contempladas para estudiantes.',
                'detalle' => '<p>Los estudiantes que incumplan los deberes estatutarios o reglamentarios son pasibles de sanciones según la gravedad de la falta: amonestación escrita, separación hasta por dos periodos lectivos, o separación definitiva de la casa de estudios.</p><p class="text-muted"><small>Nota: Pendiente de verificar con el Reglamento Académico vigente de la UNT.</small></p>',
                'fuente_normativa' => 'Ley Universitaria 30220, Art. 101 [Pendiente de verificar con el Reglamento Académico vigente de la UNT]',
                'fecha_ultima_revision' => '2026-03-01',
                'estado' => 'publicado',
            ],
            [
                'titulo' => 'Guía de Permanencia e Inducción Estudiantil UNT',
                'resumen' => 'Límites de inasistencias (30%), escala aprobatoria mínima (14) y plazos administrativos.',
                'detalle' => '<p>Aspectos clave para la permanencia académica:</p><ul><li><strong>Inasistencias:</strong> Más del 30% de inasistencias injustificadas equivale a inhabilitación, considerándose retiro automático y consumiendo una matrícula del curso.</li><li><strong>Nota mínima:</strong> La nota mínima aprobatoria es de 13.5 (redondeada reglamentariamente a 14).</li><li><strong>Rango regular:</strong> La matrícula semestral regular comprende entre 12 y 22 créditos.</li><li><strong>Plazo de reserva:</strong> Se solicita hasta los 30 días hábiles posteriores al inicio de clases (máximo 3 periodos; no aplica a primer ciclo).</li><li><strong>Retiro de asignaturas:</strong> Se gestiona dentro de los primeros 15 días hábiles del periodo lectivo.</li></ul><p class="text-muted"><small>Nota: Pendiente de verificar con el Reglamento Académico vigente de la UNT.</small></p>',
                'fuente_normativa' => 'Material de inducción de la UNT [Pendiente de verificar con el Reglamento Académico vigente de la UNT]',
                'fecha_ultima_revision' => '2026-03-01',
                'estado' => 'publicado',
            ],
            [
                'titulo' => 'Protocolo interno de acompañamiento psicopedagógico (Borrador)',
                'resumen' => 'Lineamientos internos de atención no publicados para la comunidad externa.',
                'detalle' => '<p>Documento de trabajo interno para el equipo de Bienestar.</p>',
                'fuente_normativa' => 'Directiva Interna 2026-DBG',
                'fecha_ultima_revision' => '2026-03-10',
                'estado' => 'borrador',
            ],
        ];

        foreach ($articulos as $art) {
            DB::table('bienestar.articulo_informativo')->updateOrInsert(
                ['titulo' => $art['titulo']],
                array_merge($art, [
                    'autor_id' => $autorId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    private function seedAlertasYCasosDemo(array $usuarios, array $catalogo, string $periodo): void
    {
        // 1. Alerta automática (3ra matrícula detectada)
        $idEst1 = $usuarios['extra_1021400222']->id_estudiante;
        DB::table('bienestar.alerta_riesgo')->updateOrInsert(
            [
                'id_estudiante' => $idEst1,
                'periodo' => $periodo,
                'curso' => 'Estructura de Datos',
                'regla' => 'tercera_matricula',
            ],
            [
                'origen' => 'automatica',
                'prioridad' => 'alta',
                'estado' => 'pendiente',
                'detalles' => json_encode([
                    'veces_cursado' => 3,
                    'promedio_previo' => 10.5,
                    'observacion' => 'Estudiante cursa por tercera vez el curso según registro académico',
                ]),
                'fecha_deteccion' => now()->subDays(2),
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]
        );

        // 2. Alerta originada por reporte docente (profesor@portal.local reportó a 1021400323)
        $idEst2 = $usuarios['extra_1021400323']->id_estudiante;
        $idDocente = $usuarios['profesor']->id_docente;

        $alertaDocente = DB::table('bienestar.alerta_riesgo')
            ->where('id_estudiante', $idEst2)
            ->where('id_docente', $idDocente)
            ->where('origen', 'reporte_docente')
            ->first();

        if (! $alertaDocente) {
            // Creamos un caso de bienestar asociado
            $idCaso = DB::table('bienestar.caso_bienestar')->insertGetId([
                'id_estudiante' => $idEst2,
                'id_tipo_caso' => $catalogo['id_tipo_academico'],
                'descripcion' => encrypt('Estudiante presenta reiteradas tardanzas y dificultades de comprensión según reporte docente.'),
                'estado' => 'En_Atencion',
                'abierto_en' => now()->subDays(1),
            ], 'id_caso');

            $idAlerta = DB::table('bienestar.alerta_riesgo')->insertGetId([
                'id_estudiante' => $idEst2,
                'periodo' => $periodo,
                'curso' => 'Ingeniería de Software I',
                'regla' => 'reporte_docente',
                'origen' => 'reporte_docente',
                'prioridad' => 'media',
                'estado' => 'en_atencion',
                'id_caso' => $idCaso,
                'id_docente' => $idDocente,
                'motivo_reporte' => 'Dificultad de aprendizaje y bajo rendimiento sostenido',
                'descripcion_reporte' => encrypt('Estudiante muestra decaimiento en entrega de laboratorios y exámenes parciales desaprobados.'),
                'detalles' => json_encode(['curso' => 'Ingeniería de Software I', 'nota_primer_parcial' => 8]),
                'fecha_deteccion' => now()->subDays(1),
                'atendido_por' => $usuarios['secretaria']->id_usuario,
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1),
            ], 'id_alerta');

            // Cita de atención programada en Bienestar
            $inicioCita = now()->addDays(2)->setTime(10, 0, 0);
            DB::table('bienestar.cita')->insert([
                'id_caso' => $idCaso,
                'inicio' => $inicioCita,
                'fin' => (clone $inicioCita)->addHour(),
                'modalidad' => 'Presencial',
                'estado' => 'Programada',
                'notas_atencion' => encrypt('Cita inicial de orientación y evaluación diagnóstica'),
                'id_usuario_atencion' => $usuarios['secretaria']->id_usuario,
            ]);

            // Derivación consentida a especialista externo
            $idDerivacion = DB::table('bienestar.derivacion')->insertGetId([
                'id_caso' => $idCaso,
                'id_especialista_destino' => $catalogo['id_especialista_psic'],
                'entidad_externa' => 'Dirección de Bienestar UNT',
                'motivo' => 'Apoyo psicológico por estrés académico y técnicas de estudio',
                'estado' => 'Pendiente',
                'derivado_en' => now(),
                'consentimiento_estudiante' => true,
                'fecha_consentimiento' => now(),
            ], 'id_derivacion');

            DB::table('bienestar.seguimiento_derivacion')->insert([
                'id_derivacion' => $idDerivacion,
                'fecha' => now()->toDateString(),
                'descripcion' => encrypt('Se emite ficha de derivación y se entrega cita al estudiante.'),
                'registrado_por_usuario' => $usuarios['secretaria']->id_usuario,
            ]);
        }

        // 3. Alerta manual creada por Personal de Bienestar (origen = 'manual_bienestar')
        $idEstManual = $usuarios['extra_1021400424']->id_estudiante;
        DB::table('bienestar.alerta_riesgo')->updateOrInsert(
            [
                'id_estudiante' => $idEstManual,
                'periodo' => $periodo,
                'regla' => 'alerta_manual',
                'origen' => 'manual_bienestar',
            ],
            [
                'curso' => 'GLOBAL',
                'prioridad' => 'media',
                'estado' => 'pendiente',
                'motivo_reporte' => 'Estudiante solicitó orientación vocacional en ventanilla de Bienestar',
                'descripcion_reporte' => encrypt('Refiere dificultades de adaptación al ritmo universitario y requiere consejería.'),
                'fecha_deteccion' => now()->subDay(),
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ]
        );

        // 4. Datos demostrativos para alumno@portal.local (solicitud de apoyo y cita)
        if (! empty($usuarios['alumno']->id_estudiante)) {
            $idEstAlumno = $usuarios['alumno']->id_estudiante;
            $idBeneficioComedor = DB::table('bienestar.beneficio')->where('nombre', 'Comedor Universitario')->value('id_beneficio');

            if ($idBeneficioComedor) {
                DB::table('bienestar.solicitud_apoyo')->updateOrInsert(
                    [
                        'id_estudiante' => $idEstAlumno,
                        'id_beneficio' => $idBeneficioComedor,
                    ],
                    [
                        'motivo' => 'Solicito acceso al Comedor Universitario debido a traslado familiar desde provincia y horario continuo de clases.',
                        'estado' => 'Pendiente',
                        'solicitada_en' => now()->subDays(2),
                    ]
                );
            }

            $idCasoAlumno = DB::table('bienestar.caso_bienestar')
                ->where('id_estudiante', $idEstAlumno)
                ->value('id_caso');

            if (! $idCasoAlumno) {
                $idCasoAlumno = DB::table('bienestar.caso_bienestar')->insertGetId([
                    'id_estudiante' => $idEstAlumno,
                    'id_tipo_caso' => $catalogo['id_tipo_socio'],
                    'descripcion' => encrypt('Seguimiento inicial de ingreso y evaluación de bienestar estudiantil.'),
                    'estado' => 'En_Atencion',
                    'abierto_en' => now()->subDays(3),
                ], 'id_caso');
            }

            $citaExistente = DB::table('bienestar.cita')->where('id_caso', $idCasoAlumno)->first();
            if (! $citaExistente) {
                $inicioCita = now()->addDays(3)->setTime(11, 0, 0);
                DB::table('bienestar.cita')->insert([
                    'id_caso' => $idCasoAlumno,
                    'inicio' => $inicioCita,
                    'fin' => (clone $inicioCita)->addHour(),
                    'modalidad' => 'Presencial',
                    'estado' => 'Programada',
                    'notas_atencion' => encrypt('Sesión de orientación y verificación de documentación socioeconómica.'),
                    'id_usuario_atencion' => $usuarios['secretaria']->id_usuario,
                ]);
            }
        }
    }
}
