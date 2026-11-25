<?php

namespace App\Modules\Bienestar\Database\Seeders;

use App\Models\User;
use App\Modules\Bienestar\Support\BienestarPermissions;
use App\Modules\Bienestar\Support\CategoriaMotivo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

        // 3. Personas y Usuarios de las cuentas del sistema
        $usuarios = $this->seedUsuariosYPersonas($idsCore);

        // 4. Catálogos propios del esquema Bienestar
        $catalogo = $this->seedCatalogosBienestar();

        // 5. Catálogo Oficial de Procedimientos y Trámites UNT (TUPA y Convocatoria Comedor)
        $tramitesUnt = $this->seedCatalogoTramitesUnt($usuarios['bienestar']->id_usuario ?? $usuarios['secretaria']->id_usuario);

        // 6. Artículos normativos iniciales
        $this->seedArticulosInformativos($usuarios['bienestar']->id_usuario ?? $usuarios['secretaria']->id_usuario);

        // 7. Alertas demostrativas iniciales, bitácora y solicitudes en diversos estados
        $this->seedAlertasYCasosDemo($usuarios, $catalogo, $tramitesUnt, $idsCore['periodo_codigo']);
        $this->seedHistorico($usuarios, $catalogo);
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
            $role->syncPermissions($perms);
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
                'name' => 'Secretaría de Escuela',
                'role' => 'secretaria',
                'dni' => '10000002',
                'nombres' => 'Ana',
                'paterno' => 'Secretaria',
                'materno' => 'Escuela',
                'es_docente' => false,
            ],
            'bienestar' => [
                'email' => 'bienestar@portal.local',
                'name' => 'Coordinación de Bienestar UNT',
                'role' => 'bienestar',
                'dni' => '10000010',
                'nombres' => 'Elena',
                'paterno' => 'Bienestar',
                'materno' => 'Social',
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
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('Portal2026!'),
                ]
            );
            $user->syncRoles([$data['role']]);

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

        // Estudiantes adicionales
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

        $espPsic = DB::table('bienestar.especialidad')->where('nombre', 'Psicologia')->value('id_especialidad');
        if (! $espPsic) {
            $espPsic = DB::table('bienestar.especialidad')->insertGetId(['nombre' => 'Psicologia'], 'id_especialidad');
        }

        $espMed = DB::table('bienestar.especialidad')->where('nombre', 'Medicina General')->value('id_especialidad');
        if (! $espMed) {
            $espMed = DB::table('bienestar.especialidad')->insertGetId(['nombre' => 'Medicina General'], 'id_especialidad');
        }

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

        DB::table('bienestar.beneficio')->updateOrInsert(
            ['nombre' => 'Comedor Universitario'],
            [
                'descripcion' => 'Ración alimentaria subvencionada para estudiantes en situación de vulnerabilidad',
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

    /**
     * Catálogo Oficial de Procedimientos y Trámites UNT
     * Basado en TUPA y Convocatoria de Comedor (marcado como pendiente de verificar).
     */
    private function seedCatalogoTramitesUnt(int $usuarioAutorId): array
    {
        $tramites = [
            'comedor' => [
                'nombre' => 'Comedor universitario: inscripción y calificación para comedor y otras prestaciones asistenciales',
                'descripcion' => 'Inscripción y evaluación socioeconómica para acceder al servicio de ración alimentaria subvencionada del Comedor Universitario.',
                'unidad_responsable' => 'Dirección de Bienestar Universitario, Unidad de Servicios de Salud, Área de Servicio Social',
                'plazo_dias_habiles' => 3,
                'base_normativa_fuente' => 'TUPA UNT / Convocatoria Comedor Universitario 2022 [Pendiente de verificar con DBU]',
                'estado_verificacion' => 'pendiente_verificar',
                'activo' => true,
                'requisitos' => [
                    ['nombre' => 'Formato Único de Trámite (FUT) o formato de calificación', 'descripcion' => 'Llenar formato formal de trámite institucional.', 'obligatorio' => true, 'formatos' => 'pdf,jpg,png', 'max_mb' => 5],
                    ['nombre' => 'Constancia de calificación socioeconómica del SISFOH', 'descripcion' => 'Certificado oficial SISFOH acreditando nivel socioeconómico.', 'obligatorio' => true, 'formatos' => 'pdf,jpg,png', 'max_mb' => 5],
                    ['nombre' => 'Ficha de matrícula vigente', 'descripcion' => 'Documento que acredite matrícula en el semestre en curso.', 'obligatorio' => true, 'formatos' => 'pdf,jpg,png', 'max_mb' => 5],
                    ['nombre' => 'Promedio ponderado', 'descripcion' => 'Reporte o constancia de notas con promedio ponderado acumulado.', 'obligatorio' => true, 'formatos' => 'pdf,jpg,png', 'max_mb' => 5],
                    ['nombre' => 'Una fotografía tamaño carné', 'descripcion' => 'Fotografía reciente con fondo blanco.', 'obligatorio' => true, 'formatos' => 'jpg,png', 'max_mb' => 3],
                    ['nombre' => 'Comprobante de pago de la calificación', 'descripcion' => 'Voucher de pago por derecho de calificación socioeconómica.', 'obligatorio' => true, 'formatos' => 'pdf,jpg,png', 'max_mb' => 5],
                    ['nombre' => 'Otros requisitos (convocatoria enlace)', 'descripcion' => 'Documentación complementaria según bases específicas. Pendiente de completar por Bienestar.', 'obligatorio' => false, 'formatos' => 'pdf', 'max_mb' => 5],
                ],
            ],
            'reconsideracion_comedor' => [
                'nombre' => 'Reconsideración del servicio de comedor universitario (TUPA 302)',
                'descripcion' => 'Trámite de reconsideración para estudiantes no calificados en primera instancia para el comedor.',
                'unidad_responsable' => 'Dirección de Bienestar Universitario',
                'plazo_dias_habiles' => 4,
                'base_normativa_fuente' => 'TUPA UNT - Procedimiento 302 [Pendiente de verificar con DBU]',
                'estado_verificacion' => 'pendiente_verificar',
                'activo' => true,
                'requisitos' => [
                    ['nombre' => 'Solicitud dirigida al Director de Bienestar Universitario justificando el pedido', 'descripcion' => 'Carta formal de fundamentación de reconsideración.', 'obligatorio' => true, 'formatos' => 'pdf', 'max_mb' => 5],
                    ['nombre' => 'Recibo de prestaciones asistenciales', 'descripcion' => 'Recibo o comprobante previo de atención en prestaciones asistenciales.', 'obligatorio' => true, 'formatos' => 'pdf,jpg,png', 'max_mb' => 5],
                    ['nombre' => 'Promedio ponderado', 'descripcion' => 'Reporte consolidado de rendimiento académico.', 'obligatorio' => true, 'formatos' => 'pdf,jpg,png', 'max_mb' => 5],
                    ['nombre' => 'Avance curricular', 'descripcion' => 'Ficha o constancia de avance en el plan de estudios.', 'obligatorio' => true, 'formatos' => 'pdf,jpg,png', 'max_mb' => 5],
                ],
            ],
            'duplicado_carne' => [
                'nombre' => 'Duplicado de carné de comensal (TUPA 303)',
                'descripcion' => 'Reposición de carné de comensal por pérdida, deterioro o robo.',
                'unidad_responsable' => 'Dirección de Bienestar Universitario',
                'plazo_dias_habiles' => 1,
                'base_normativa_fuente' => 'TUPA UNT - Procedimiento 303 [Pendiente de verificar con DBU]',
                'estado_verificacion' => 'pendiente_verificar',
                'activo' => true,
                'requisitos' => [
                    ['nombre' => 'Solicitud dirigida al Director de Bienestar justificando el pedido', 'descripcion' => 'Documento de justificación por duplicado.', 'obligatorio' => true, 'formatos' => 'pdf', 'max_mb' => 5],
                    ['nombre' => 'Una fotografía', 'descripcion' => 'Fotografía tamaño carné reciente.', 'obligatorio' => true, 'formatos' => 'jpg,png', 'max_mb' => 3],
                    ['nombre' => 'Comprobante de pago del duplicado', 'descripcion' => 'Voucher bancario o recibo de tesorería UNT.', 'obligatorio' => true, 'formatos' => 'pdf,jpg,png', 'max_mb' => 5],
                ],
            ],
            'beca_comedor_deportistas' => [
                'nombre' => 'Beca de comedor para integrantes de grupos artísticos y deportistas calificados (TUPA 188)',
                'descripcion' => 'Beca integral de comedor para representantes oficiales de la universidad en disciplinas artísticas o deportivas.',
                'unidad_responsable' => 'Dirección de Bienestar Universitario',
                'plazo_dias_habiles' => 20,
                'base_normativa_fuente' => 'TUPA UNT - Procedimiento 188 (Silencio administrativo positivo) [Pendiente de verificar con DBU]',
                'estado_verificacion' => 'pendiente_verificar',
                'activo' => true,
                'requisitos' => [
                    ['nombre' => 'Solicitud dirigida al Director de Bienestar Universitario', 'descripcion' => 'Solicitud formal de acogimiento a beca.', 'obligatorio' => true, 'formatos' => 'pdf', 'max_mb' => 5],
                    ['nombre' => 'Constancia de pertenencia al grupo con visto bueno del jefe de unidad', 'descripcion' => 'Acreditación oficial emitida por la unidad deportiva o cultural UNT.', 'obligatorio' => true, 'formatos' => 'pdf', 'max_mb' => 5],
                ],
            ],
            'sistoc' => [
                'nombre' => 'Orientación y consejería (SISTOC) (TUPA 298)',
                'descripcion' => 'Servicio de consejería psicológica, vocacional y psicopedagógica.',
                'unidad_responsable' => 'Dirección de Bienestar Universitario - SISTOC',
                'plazo_dias_habiles' => 1,
                'base_normativa_fuente' => 'TUPA UNT - Procedimiento 298 [Pendiente de verificar con DBU]',
                'estado_verificacion' => 'pendiente_verificar',
                'activo' => true,
                'requisitos' => [
                    ['nombre' => 'Llenar el formato de orientación y consejería', 'descripcion' => 'Ficha inicial de motivos de consejería.', 'obligatorio' => true, 'formatos' => 'pdf', 'max_mb' => 5],
                ],
            ],
            'talleres_deportivos' => [
                'nombre' => 'Inscripción a talleres deportivos (TUPA 304)',
                'descripcion' => 'Inscripción en talleres de recreación, fútbol, voleibol, natación y acondicionamiento físico.',
                'unidad_responsable' => 'Recreación y Deportes',
                'plazo_dias_habiles' => 1,
                'base_normativa_fuente' => 'TUPA UNT - Procedimiento 304 [Pendiente de verificar con DBU]',
                'estado_verificacion' => 'pendiente_verificar',
                'activo' => true,
                'requisitos' => [
                    ['nombre' => 'Una fotografía para el carné', 'descripcion' => 'Foto tamaño carné.', 'obligatorio' => true, 'formatos' => 'jpg,png', 'max_mb' => 3],
                    ['nombre' => 'Comprobante de pago', 'descripcion' => 'Recibo de pago por concepto de taller deportivo.', 'obligatorio' => true, 'formatos' => 'pdf,jpg,png', 'max_mb' => 5],
                ],
            ],
        ];

        $idsCreados = [];

        foreach ($tramites as $key => $data) {
            $idTipo = DB::table('bienestar.tipo_solicitud')->where('nombre', $data['nombre'])->value('id_tipo_solicitud');
            if (! $idTipo) {
                $idTipo = DB::table('bienestar.tipo_solicitud')->insertGetId([
                    'nombre' => $data['nombre'],
                    'descripcion' => $data['descripcion'],
                    'unidad_responsable' => $data['unidad_responsable'],
                    'plazo_dias_habiles' => $data['plazo_dias_habiles'],
                    'base_normativa_fuente' => $data['base_normativa_fuente'],
                    'estado_verificacion' => $data['estado_verificacion'],
                    'activo' => $data['activo'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ], 'id_tipo_solicitud');
            }

            foreach ($data['requisitos'] as $idx => $req) {
                $idReq = DB::table('bienestar.tipo_solicitud_requisito')
                    ->where('id_tipo_solicitud', $idTipo)
                    ->where('nombre', $req['nombre'])
                    ->value('id_requisito');

                if (! $idReq) {
                    $idReq = DB::table('bienestar.tipo_solicitud_requisito')->insertGetId([
                        'id_tipo_solicitud' => $idTipo,
                        'nombre' => $req['nombre'],
                        'descripcion' => $req['descripcion'],
                        'es_obligatorio' => $req['obligatorio'],
                        'formatos_permitidos' => $req['formatos'],
                        'tamano_maximo_mb' => $req['max_mb'],
                        'orden' => $idx + 1,
                        'activo' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], 'id_requisito');
                }
            }

            $idsCreados[$key] = $idTipo;
        }

        return $idsCreados;
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

    private function seedAlertasYCasosDemo(array $usuarios, array $catalogo, array $tramitesUnt, string $periodo): void
    {
        $idEst1 = $usuarios['extra_1021400222']->id_estudiante;
        $idEst2 = $usuarios['extra_1021400323']->id_estudiante;
        $idDocente = $usuarios['profesor']->id_docente;

        // 1. Alerta automática de tercera matrícula: estado 'en_revision' para que Secretaría la procese con salvaguarda
        $this->insertarSiFalta('bienestar.alerta_riesgo',
            [
                'id_estudiante' => $idEst1,
                'periodo' => $periodo,
                'curso' => 'Estructura de Datos',
                'regla' => 'tercera_matricula',
            ],
            [
                'origen' => 'automatica',
                'prioridad' => 'alta',
                'estado' => 'en_revision',
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

        // 2. Alerta originada por reporte docente
        $this->insertarSiFalta('bienestar.alerta_riesgo',
            [
                'id_estudiante' => $idEst2,
                'id_docente' => $idDocente,
                'regla' => 'reporte_docente',
            ],
            [
                'periodo' => $periodo,
                'curso' => 'Ingeniería de Software I',
                'origen' => 'reporte_docente',
                'prioridad' => 'media',
                'estado' => 'en_revision',
                'motivo_reporte' => 'Dificultad de aprendizaje y bajo rendimiento sostenido',
                'categoria_motivo' => 'academico',
                'descripcion_reporte' => encrypt('Estudiante muestra decaimiento en entrega de laboratorios y exámenes parciales desaprobados.', false),
                'fecha_deteccion' => now()->subDay(),
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ]
        );

        // 3. Alerta descartada con motivo para bitácora
        $alertaDesc = DB::table('bienestar.alerta_riesgo')
            ->where('id_estudiante', $idEst1)
            ->where('periodo', $periodo)
            ->where('curso', 'Taller de Programación')
            ->where('regla', 'inasistencia_preventiva')
            ->first();

        if ($alertaDesc) {
            $idAlertaDesc = $alertaDesc->id_alerta;
        } else {
            $idAlertaDesc = DB::table('bienestar.alerta_riesgo')->insertGetId([
                'id_estudiante' => $idEst1,
                'periodo' => $periodo,
                'curso' => 'Taller de Programación',
                'regla' => 'inasistencia_preventiva',
                'origen' => 'automatica',
                'prioridad' => 'baja',
                'estado' => 'descartada',
                'motivo_reporte' => 'Inasistencias justificadas por motivos médicos',
                'fecha_deteccion' => now()->subDays(5),
                'fecha_resolucion' => now()->subDays(3),
                'filtrado_por' => $usuarios['secretaria']->id_usuario,
                'fecha_filtro_secretaria' => now()->subDays(3),
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(3),
            ], 'id_alerta');
        }

        DB::table('bienestar.alerta_bitacora')->updateOrInsert(
            [
                'id_alerta' => $idAlertaDesc,
                'accion' => 'descartado',
            ],
            [
                'id_usuario' => $usuarios['secretaria']->id_usuario,
                'motivo' => 'El estudiante presentó certificado médico de justificación en mesa de partes de Secretaría.',
                'creado_en' => now()->subDays(3),
            ]
        );

        // 4. Solicitudes demostrativas en los diversos estados
        $idModuloBienestar = DB::table('seguridad.modulo_sistema')->where('codigo', 'bienestar')->value('id_modulo') ?? 2;

        $crearArchivoDemo = function (string $nombre, int $idUsuario) use ($idModuloBienestar) {
            $uniqueKey = 'bienestar/solicitudes/demo_' . md5($nombre . '_' . $idUsuario) . '.pdf';
            $existente = DB::table('comun.archivo')->where('ruta_almacenamiento', $uniqueKey)->first();
            if ($existente) {
                $this->pdfDemo($uniqueKey);

                return $existente->id_archivo;
            }
            $this->pdfDemo($uniqueKey);

            return (int) DB::table('comun.archivo')->insertGetId([
                'id_modulo' => $idModuloBienestar,
                'nombre_original' => $nombre,
                'ruta_almacenamiento' => $uniqueKey,
                'tipo_mime' => 'application/pdf',
                'tamano_bytes' => 15360,
                'hash_sha256' => hash('sha256', $nombre),
                'subido_por' => $idUsuario,
                'subido_en' => now(),
            ], 'id_archivo');
        };

        if (! empty($usuarios['alumno']->id_estudiante)) {
            $idEstAlumno = $usuarios['alumno']->id_estudiante;
            $idTipoComedor = $tramitesUnt['comedor'] ?? null;

            if ($idTipoComedor) {
                // Solicitud 1: estado 'enviada'
                $solEnviada = DB::table('bienestar.solicitud_apoyo')
                    ->where('id_estudiante', $idEstAlumno)
                    ->where('id_tipo_solicitud', $idTipoComedor)
                    ->first();

                if ($solEnviada) {
                    $idSol1 = $solEnviada->id_solicitud;
                } else {
                    $idSol1 = DB::table('bienestar.solicitud_apoyo')->insertGetId([
                        'id_estudiante' => $idEstAlumno,
                        'id_tipo_solicitud' => $idTipoComedor,
                        'motivo' => 'Solicito acceso al Comedor Universitario debido a traslado familiar desde provincia y horario continuo de clases.',
                        'estado' => 'enviada',
                        'folio_externo_confirmado' => false,
                        'solicitada_en' => now()->subDays(2),
                    ], 'id_solicitud');
                }

                $reqsComedor = DB::table('bienestar.tipo_solicitud_requisito')->where('id_tipo_solicitud', $idTipoComedor)->get();
                foreach ($reqsComedor as $req) {
                    $archId = $crearArchivoDemo("doc_req_{$req->id_requisito}.pdf", $usuarios['alumno']->id_usuario);
                    $this->insertarSiFalta('bienestar.solicitud_requisito_documento',
                        [
                            'id_solicitud' => $idSol1,
                            'id_requisito' => $req->id_requisito,
                        ],
                        [
                            'id_archivo' => $archId,
                            'estado_revision' => 'pendiente',
                            'comentario_revision' => null,
                            'subido_en' => now()->subDays(2),
                            'actualizado_en' => now()->subDays(2),
                        ]
                    );
                }
            }
        }

        // Solicitud 2: estado 'observada' para estudiante 1
        if (! empty($tramitesUnt['reconsideracion_comedor']) && ! empty($idEst1)) {
            $idTipoRecon = $tramitesUnt['reconsideracion_comedor'];
            $solObs = DB::table('bienestar.solicitud_apoyo')
                ->where('id_estudiante', $idEst1)
                ->where('id_tipo_solicitud', $idTipoRecon)
                ->first();

            if ($solObs) {
                $idSol2 = $solObs->id_solicitud;
            } else {
                $idSol2 = DB::table('bienestar.solicitud_apoyo')->insertGetId([
                    'id_estudiante' => $idEst1,
                    'id_tipo_solicitud' => $idTipoRecon,
                    'motivo' => 'Solicito reconsideración de calificación socioeconómica del comedor por cambio en ingresos familiares.',
                    'estado' => 'observada',
                    'folio_externo_confirmado' => false,
                    'revisado_por_secretaria' => $usuarios['secretaria']->id_usuario,
                    'fecha_revision_secretaria' => now()->subHours(12),
                    'observacion_general_secretaria' => 'Favor de adjuntar el récord integral actualizado emitido por matrícula.',
                    'solicitada_en' => now()->subDays(3),
                ], 'id_solicitud');
            }

            $reqsRecon = DB::table('bienestar.tipo_solicitud_requisito')->where('id_tipo_solicitud', $idTipoRecon)->get();
            foreach ($reqsRecon as $idx => $req) {
                $archId = $crearArchivoDemo("recon_req_{$req->id_requisito}.pdf", $usuarios['secretaria']->id_usuario);
                $esObservado = ($idx === 0);
                $this->insertarSiFalta('bienestar.solicitud_requisito_documento',
                    [
                        'id_solicitud' => $idSol2,
                        'id_requisito' => $req->id_requisito,
                    ],
                    [
                        'id_archivo' => $archId,
                        'estado_revision' => $esObservado ? 'observado' : 'conforme',
                        'comentario_revision' => $esObservado ? 'El documento adjunto está ilegible en la firma de secretaría académica.' : 'Documento verificado.',
                        'revisado_por' => $usuarios['secretaria']->id_usuario,
                        'revisado_en' => now()->subHours(12),
                        'subido_en' => now()->subDays(3),
                        'actualizado_en' => now()->subHours(12),
                    ]
                );
            }
        }

        // Solicitud 3: estado 'remitida' con folio externo verificado para estudiante 2
        if (! empty($tramitesUnt['duplicado_carne']) && ! empty($idEst2)) {
            $idTipoDup = $tramitesUnt['duplicado_carne'];
            $solRem = DB::table('bienestar.solicitud_apoyo')
                ->where('id_estudiante', $idEst2)
                ->where('id_tipo_solicitud', $idTipoDup)
                ->first();

            if ($solRem) {
                $idSol3 = $solRem->id_solicitud;
            } else {
                $idSol3 = DB::table('bienestar.solicitud_apoyo')->insertGetId([
                    'id_estudiante' => $idEst2,
                    'id_tipo_solicitud' => $idTipoDup,
                    'motivo' => 'Extravío de carné de comedor en el campus universitario.',
                    'estado' => 'remitida',
                    'folio_externo_confirmado' => true,
                    'revisado_por_secretaria' => $usuarios['secretaria']->id_usuario,
                    'fecha_revision_secretaria' => now()->subHours(6),
                    'fecha_remision_bienestar' => now()->subHours(6),
                    'solicitada_en' => now()->subDays(4),
                ], 'id_solicitud');
            }

            $reqsDup = DB::table('bienestar.tipo_solicitud_requisito')->where('id_tipo_solicitud', $idTipoDup)->get();
            foreach ($reqsDup as $req) {
                $archId = $crearArchivoDemo("dup_req_{$req->id_requisito}.pdf", $usuarios['secretaria']->id_usuario);
                $this->insertarSiFalta('bienestar.solicitud_requisito_documento',
                    [
                        'id_solicitud' => $idSol3,
                        'id_requisito' => $req->id_requisito,
                    ],
                    [
                        'id_archivo' => $archId,
                        'estado_revision' => 'conforme',
                        'comentario_revision' => 'Conforme y verificado contra voucher físico.',
                        'revisado_por' => $usuarios['secretaria']->id_usuario,
                        'revisado_en' => now()->subHours(6),
                        'subido_en' => now()->subDays(4),
                        'actualizado_en' => now()->subHours(6),
                    ]
                );
            }
        }
    }

    /** Reejecutar el seeder nunca pisa lo que ya se trabajó en el sistema. */
    private function insertarSiFalta(string $tabla, array $clave, array $datos): void
    {
        DB::table($tabla)->where($clave)->exists() || DB::table($tabla)->insert($clave + $datos);
    }

    private function pdfDemo(string $ruta): void
    {
        if (! Storage::disk('local')->exists($ruta)) {
            Storage::disk('local')->put($ruta, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n4 0 obj<</Length 60>>stream\nBT /F1 18 Tf 60 760 Td (Documento de demostracion) Tj ET\nendstream endobj\n5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF");
        }

        // Sembrar como root no debe dejar la carpeta inaccesible para el servidor web
        $raiz = Storage::disk('local')->path('');
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            for ($p = Storage::disk('local')->path($ruta); strlen($p) > strlen($raiz); $p = dirname($p)) {
                chown($p, fileowner($raiz));
                chgrp($p, filegroup($raiz));
            }
        }
    }

    /** Periodos anteriores: dan contexto a la tendencia de los reportes. */
    private function seedHistorico(array $usuarios, array $catalogo): void
    {
        $estudiantes = [$usuarios['extra_1021400222']->id_estudiante, $usuarios['extra_1021400323']->id_estudiante];
        $filas = [
            ['2025-I', 'Algoritmos', 'tercera_matricula', 'automatica', 'alta', 'atendida'],
            ['2025-I', 'Matemática II', 'inasistencia_preventiva', 'automatica', 'media', 'atendida'],
            ['2025-II', 'Física I', 'inasistencia_inhabilitacion', 'automatica', 'alta', 'atendida'],
            ['2025-II', 'Estadística', 'reporte_docente', 'reporte_docente', 'media', 'atendida'],
            ['2025-II', 'Redes I', 'inasistencia_preventiva', 'automatica', 'baja', 'descartada'],
        ];
        foreach ($filas as $i => [$periodo, $curso, $regla, $origen, $prioridad, $estado]) {
            $clave = ['id_estudiante' => $estudiantes[$i % 2], 'periodo' => $periodo, 'curso' => $curso, 'regla' => $regla];
            if (DB::table('bienestar.alerta_riesgo')->where($clave)->exists()) {
                continue;
            }
            $fecha = ($periodo === '2025-I' ? '2025-05-' : '2025-10-').(10 + $i);
            $idCaso = $estado !== 'atendida' ? null : DB::table('bienestar.caso_bienestar')->insertGetId([
                'id_estudiante' => $clave['id_estudiante'], 'id_tipo_caso' => $catalogo['id_tipo_academico'],
                'descripcion' => encrypt('Acompañamiento concluido (demostración)', false), 'estado' => 'Cerrado',
                'abierto_en' => $fecha, 'cerrado_en' => $fecha.' 18:00',
            ], 'id_caso');
            DB::table('bienestar.alerta_riesgo')->insert($clave + [
                'origen' => $origen, 'prioridad' => $prioridad, 'estado' => $estado, 'id_caso' => $idCaso,
                'id_docente' => $origen === 'reporte_docente' ? $usuarios['profesor']->id_docente : null,
                'categoria_motivo' => CategoriaMotivo::porRegla($regla), 'fecha_deteccion' => $fecha, 'fecha_resolucion' => $fecha,
                'created_at' => $fecha, 'updated_at' => $fecha,
            ]);
        }
    }
}
