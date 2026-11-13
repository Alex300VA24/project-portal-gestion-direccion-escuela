<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Cotecu\Models\Registro;
use App\Shared\Models\Core\PeriodoAcademico;
use App\Shared\Models\Core\Persona;
use App\Shared\Models\Core\PlanEstudios;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Datos de prueba de COTECU: semestres académicos y cuentas institucionales.
 *
 * Idempotente: se puede ejecutar cuantas veces sea necesario.
 * Uso (en este orden):
 *   1. php artisan db:seed                     (DatabaseSeeder: roles y cuentas base)
 *   2. php artisan db:seed --class=CotecuPermissionsSeeder
 *   3. php artisan db:seed --class=CotecuDemoSeeder
 *
 * core.periodo_academico, core.persona y seguridad.usuario son tablas
 * institucionales compartidas: este seeder solo siembra lo mínimo para
 * probar el módulo (los FK de cotecu.silabos apuntan a ellas). En
 * producción los carga quien administre core/seguridad; COTECU solo lee.
 *
 * El catálogo del plan (facultad, escuela, plan, documentos) también es
 * demo. Limpieza:
 *   DELETE FROM cotecu.planes_curriculares WHERE id_plan IN
 *     (SELECT id_plan FROM core.plan_estudios WHERE codigo = 'PLAN-INF-2020');
 *   DELETE FROM core.plan_estudios WHERE codigo = 'PLAN-INF-2020';
 *   DELETE FROM core.escuela_profesional WHERE codigo = 'EP-INF';
 *   DELETE FROM core.facultad WHERE codigo = 'FAC-CFM';
 */
class CotecuDemoSeeder extends Seeder
{
    /**
     * Periodos de prueba. El último queda Activo: es el que usan las
     * pantallas por defecto y el único en el que se puede subir un sílabo.
     */
    private const PERIODOS = [
        [
            'codigo' => '2026-I',
            'anio' => 2026,
            'tipo' => 'I',
            'fecha_inicio' => '2026-04-13',
            'fecha_fin' => '2026-08-08',
            'numero_semanas' => 17,
            'estado' => 'Cerrado',
        ],
        [
            'codigo' => '2026-II',
            'anio' => 2026,
            'tipo' => 'II',
            'fecha_inicio' => '2026-08-24',
            'fecha_fin' => '2026-12-19',
            'numero_semanas' => 17,
            'estado' => 'Activo',
        ],
    ];

    /**
     * Cuentas del portal (public.users) con su contraparte institucional:
     * core.persona + seguridad.usuario, que es a donde apuntan las claves
     * foráneas de todos los módulos (cotecu.silabos.id_profesor, etc.).
     *
     * Puente: seguridad.usuario.correo_institucional = users.email.
     * Son filas de prueba: la app solo las lee y el acceso sigue siendo
     * el de Laravel (public.users). Si el grupo de gestión redefine la
     * estrategia de identidad, se limpian con:
     *   DELETE FROM seguridad.usuario WHERE correo_institucional LIKE '%@portal.local';
     *   DELETE FROM core.persona WHERE numero_documento LIKE '7000000%';
     */
    private const USUARIOS = [
        ['email' => 'director@portal.local', 'numero_documento' => '70000001', 'nombres' => 'Director', 'apellido_paterno' => 'Escuela'],
        ['email' => 'secretaria@portal.local', 'numero_documento' => '70000002', 'nombres' => 'Secretaría', 'apellido_paterno' => 'Académica'],
        ['email' => 'profesor@portal.local', 'numero_documento' => '70000003', 'nombres' => 'Profesor', 'apellido_paterno' => 'Demo'],
        ['email' => 'alumno@portal.local', 'numero_documento' => '70000004', 'nombres' => 'Alumno', 'apellido_paterno' => 'Demo'],
        // Director de COTECU: el profesor convocado para una nueva currícula.
        // Conserva el rol profesor y recibe cotecu.plan.upload a nivel usuario.
        ['email' => 'director-cotecu@portal.local', 'numero_documento' => '70000005', 'nombres' => 'Director', 'apellido_paterno' => 'COTECU'],
    ];

    /**
     * Catálogo académico mínimo para ver planes curriculares:
     * facultad → escuela → plan de estudios → documentos del plan.
     * Valores fijos acordados; áreas y cursos vienen en el pase del
     * selector de cursos de sílabos.
     */
    private const FACULTAD = [
        'codigo' => 'FAC-CFM',
        'nombre' => 'Ciencias Físicas y Matemáticas',
    ];

    private const ESCUELA = [
        'codigo' => 'EP-INF',
        'nombre' => 'Ingeniería Informática',
        'estado' => 'Activo',
    ];

    private const PLAN = [
        'codigo' => 'PLAN-INF-2020',
        'nombre' => 'Plan de estudios 2020 - Ingeniería Informática',
        'version' => '2020',
        'anio_aprobacion' => 2020,
        'resolucion_aprobacion' => 'R.D. N° 142-2020-UNT',
        'fecha_vigencia_inicio' => '2021-03-01',
        'es_predeterminado' => true,
        'estado' => 'Vigente',
    ];

    /**
     * Documentos del plan curricular que COTECU revisa (cotecu.planes_curriculares).
     * Sin id_periodo: es por plan y se versiona, no por semestre.
     * archivo_url es placeholder hasta decidir el storage (disco público
     * vs comun.archivo).
     */
    private const PLANES_CURRICULARES = [
        ['version' => 1, 'estado' => 'Validado', 'archivo_url' => 'demo/planes/plan-inf-2020-v1.pdf'],
        ['version' => 2, 'estado' => 'Pendiente', 'archivo_url' => 'demo/planes/plan-inf-2020-v2.pdf'],
    ];

    public function run(): void
    {
        foreach (self::PERIODOS as $periodo) {
            PeriodoAcademico::firstOrCreate(['codigo' => $periodo['codigo']], $periodo);
        }

        foreach (self::USUARIOS as $cuenta) {
            $persona = Persona::firstOrCreate(
                ['tipo_documento' => 'DNI', 'numero_documento' => $cuenta['numero_documento']],
                [
                    'nombres' => $cuenta['nombres'],
                    'apellido_paterno' => $cuenta['apellido_paterno'],
                    'correo_personal' => $cuenta['email'],
                ],
            );

            Usuario::firstOrCreate(
                ['correo_institucional' => $cuenta['email']],
                [
                    'id_persona' => $persona->id_persona,
                    'contrasena_hash' => Hash::make('Portal2026!'),
                    'estado' => 'Activo',
                    'debe_cambiar_contrasena' => false,
                ],
            );
        }

        // Cuenta del portal del director de COTECU: entra con el rol
        // profesor y recibe cotecu.plan.upload directamente en el usuario
        // (sin crear un rol nuevo). Exige CotecuPermissionsSeeder previo.
        $directorCotecu = User::updateOrCreate(
            ['email' => 'director-cotecu@portal.local'],
            [
                'name' => 'Director COTECU',
                'email_verified_at' => now(),
                'password' => Hash::make('Portal2026!'),
            ],
        );
        $directorCotecu->syncRoles(['profesor']);
        $directorCotecu->givePermissionTo('cotecu.plan.upload');

        $this->sembrarPlanCurricular();

        $this->command?->info('Datos demo de COTECU listos: 2 periodos, 5 cuentas institucionales, director de COTECU y plan curricular.');
    }

    /**
     * Cadena mínima para que la pantalla de planes tenga filas:
     * facultad → escuela → plan de estudios → documentos del plan.
     */
    private function sembrarPlanCurricular(): void
    {
        DB::table('core.facultad')->updateOrInsert(
            ['codigo' => self::FACULTAD['codigo']],
            ['nombre' => self::FACULTAD['nombre']],
        );

        $idFacultad = DB::table('core.facultad')
            ->where('codigo', self::FACULTAD['codigo'])
            ->value('id_facultad');

        DB::table('core.escuela_profesional')->updateOrInsert(
            ['codigo' => self::ESCUELA['codigo']],
            array_merge(self::ESCUELA, ['id_facultad' => $idFacultad]),
        );

        $idEscuela = DB::table('core.escuela_profesional')
            ->where('codigo', self::ESCUELA['codigo'])
            ->value('id_escuela');

        $plan = PlanEstudios::firstOrCreate(
            ['codigo' => self::PLAN['codigo']],
            array_merge(self::PLAN, ['id_escuela' => $idEscuela]),
        );

        foreach (self::PLANES_CURRICULARES as $documento) {
            Registro::firstOrCreate(
                ['id_plan' => $plan->id_plan, 'version' => $documento['version']],
                array_merge($documento, ['nombre' => self::PLAN['nombre']]),
            );
        }
    }
}
