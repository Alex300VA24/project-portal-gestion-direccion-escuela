<?php

namespace App\Modules\TesisProyectoTesis\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * SOLO DESARROLLO Y PRUEBAS. Inserta datos de ejemplo en core, seguridad y public
 * (cuentas demo con contraseña Portal2026!). Idempotente.
 */
final class DemoTesisSeeder extends Seeder
{
    public const CLAVE = 'Portal2026!';

    private int $idEscuela;

    private int $idDepartamento;

    private int $idPlan;

    private int $idPeriodo;

    private int $idModalidad;

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('DemoTesisSeeder se omite: solo para local o testing.');

            return;
        }

        $this->catalogos();

        $this->cuenta('director@portal.local', 'director_escuela', 'Manuel', 'Reyes', 'Ortiz', '40000001');
        $this->cuenta('secretaria@portal.local', 'secretaria', 'Patricia', 'Loyola', 'Ruiz', '40000002');

        $docentes = [
            ['asesor@portal.local', 'Carlos', 'Medina', 'Ruiz', 'Mg.', 'Asociado', 'DOC-101', ['LI-SW', 'LI-IA']],
            ['jurado1@portal.local', 'Ana', 'Paredes', 'Luna', 'Dra.', 'Principal', 'DOC-102', ['LI-IA']],
            ['jurado2@portal.local', 'Jorge', 'Castillo', 'Ríos', 'Ms.', 'Asociado', 'DOC-103', ['LI-SI']],
            ['jurado3@portal.local', 'Rosa', 'Huamán', 'Díaz', 'Dra.', 'Auxiliar', 'DOC-104', ['LI-IA', 'LI-SW']],
            ['profesor@portal.local', 'Elena', 'Vargas', 'Soto', 'Dra.', 'Asociado', 'DOC-105', ['LI-SI', 'LI-SW']],
        ];
        foreach ($docentes as $i => [$correo, $n, $p, $m, $grado, $cat, $codigo, $lineas]) {
            $this->docente($correo, $n, $p, $m, $grado, $cat, $codigo, '4100000'.$i, $lineas);
        }

        $this->estudiante('alumno@portal.local', 'Lucía', 'Ramírez', 'Torres', '1027004521', '7000001');
        $this->estudiante('alumno2@portal.local', 'Diego', 'Salazar', 'Vega', '1027004588', '7000002');
        $this->estudiante('alumno3@portal.local', 'Andrea', 'Quispe', 'Mendoza', '1027004610', '7000003');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function catalogos(): void
    {
        $idFacultad = $this->upsert('core.facultad', 'id_facultad', ['codigo' => 'FCFYM'], ['nombre' => 'Facultad de Ciencias Físicas y Matemáticas']);
        $this->idEscuela = $this->upsert('core.escuela_profesional', 'id_escuela', ['codigo' => 'EPII'], ['id_facultad' => $idFacultad, 'nombre' => 'Ingeniería Informática', 'estado' => 'Activo']);
        $this->idDepartamento = $this->upsert('core.departamento_academico', 'id_departamento', ['codigo' => 'DAINF'], ['id_facultad' => $idFacultad, 'nombre' => 'Departamento de Informática']);
        $this->idPlan = $this->upsert('core.plan_estudios', 'id_plan', ['codigo' => 'EPII-2018'], [
            'id_escuela' => $this->idEscuela, 'nombre' => 'Plan de estudios 2018', 'version' => '2018', 'anio_aprobacion' => 2018,
            'fecha_vigencia_inicio' => '2018-03-01', 'es_predeterminado' => true, 'estado' => 'Vigente',
        ]);
        $this->idModalidad = $this->upsert('core.modalidad_ingreso', 'id_modalidad', ['codigo' => 'ORD'], ['nombre' => 'Examen ordinario']);

        $hoy = Carbon::today();
        $anio = (int) $hoy->year;
        $segundo = $hoy->month >= 8;
        $inicio = $segundo ? Carbon::create($anio, 8, 31) : Carbon::create($anio, 3, 30);
        $this->idPeriodo = $this->upsert('core.periodo_academico', 'id_periodo', ['codigo' => $anio.'-'.($segundo ? 'II' : 'I')], [
            'anio' => $anio, 'tipo' => $segundo ? 'II' : 'I', 'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $inicio->copy()->addWeeks(16)->toDateString(), 'numero_semanas' => 16, 'estado' => 'En curso',
        ]);

        foreach ([['LI-SW', 'Ingeniería de software'], ['LI-IA', 'Inteligencia artificial'], ['LI-SI', 'Sistemas de información']] as [$codigo, $nombre]) {
            $this->upsert('core.linea_investigacion', 'id_linea', ['codigo' => $codigo], ['id_escuela' => $this->idEscuela, 'nombre' => $nombre, 'estado' => 'Activo']);
        }

        foreach ([['INFO-5', 'Aula INFO-5', 'Aula', 40], ['LAB-8', 'Laboratorio 8', 'Laboratorio', 30], ['AUD-FCFYM', 'Auditorio de la Facultad', 'Auditorio', 120]] as [$codigo, $nombre, $tipo, $cap]) {
            $this->upsert('core.ambiente', 'id_ambiente', ['codigo' => $codigo], ['nombre' => $nombre, 'tipo' => $tipo, 'capacidad' => $cap, 'pabellon' => 'Informática', 'estado' => 'Activo']);
        }
    }

    public function cuenta(string $correo, string $rol, string $nombres, string $paterno, string $materno, string $dni): int
    {
        $idPersona = $this->upsert('core.persona', 'id_persona', ['tipo_documento' => 'DNI', 'numero_documento' => $dni], [
            'nombres' => $nombres, 'apellido_paterno' => $paterno, 'apellido_materno' => $materno,
        ]);

        $user = User::query()->updateOrCreate(['email' => $correo], [
            'name' => trim("$nombres $paterno $materno"),
            'password' => Hash::make(self::CLAVE),
        ]);
        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
        $user->assignRole(Role::findOrCreate($rol, 'web'));

        $this->upsert('seguridad.usuario', 'id_usuario', ['correo_institucional' => $correo], [
            'id_persona' => $idPersona, 'contrasena_hash' => Hash::make(self::CLAVE), 'estado' => 'Activo', 'debe_cambiar_contrasena' => false,
        ]);

        return $idPersona;
    }

    /** @param list<string> $lineas códigos de línea */
    public function docente(string $correo, string $n, string $p, string $m, string $grado, string $categoria, string $codigo, string $dni, array $lineas = []): int
    {
        $idPersona = $this->cuenta($correo, 'profesor', $n, $p, $m, $dni);
        $idDocente = $this->upsert('core.docente', 'id_docente', ['codigo_docente' => $codigo], [
            'id_persona' => $idPersona, 'id_departamento' => $this->idDepartamento ?? (int) DB::table('core.departamento_academico')->value('id_departamento'),
            'categoria' => $categoria, 'condicion' => 'Nombrado', 'grado_academico' => $grado, 'estado' => 'Activo',
            'orcid' => '0000-0002-'.substr(str_pad($dni, 8, '0', STR_PAD_LEFT), -4).'-'.substr($codigo, -3).'X',
        ]);

        foreach ($lineas as $codigoLinea) {
            $idLinea = DB::table('core.linea_investigacion')->where('codigo', $codigoLinea)->value('id_linea');
            if ($idLinea !== null && ! DB::table('core.docente_linea')->where(['id_docente' => $idDocente, 'id_linea' => $idLinea])->exists()) {
                DB::table('core.docente_linea')->insert(['id_docente' => $idDocente, 'id_linea' => $idLinea]);
            }
        }

        return $idDocente;
    }

    public function estudiante(string $correo, string $n, string $p, string $m, string $codigo, string $dni): int
    {
        $idPersona = $this->cuenta($correo, 'alumno', $n, $p, $m, $dni);

        return $this->upsert('core.estudiante', 'id_estudiante', ['codigo_universitario' => $codigo], [
            'id_persona' => $idPersona,
            'id_escuela' => $this->idEscuela ?? (int) DB::table('core.escuela_profesional')->where('codigo', 'EPII')->value('id_escuela'),
            'id_plan' => $this->idPlan ?? (int) DB::table('core.plan_estudios')->where('codigo', 'EPII-2018')->value('id_plan'),
            'id_periodo_ingreso' => $this->idPeriodo ?? (int) DB::table('core.periodo_academico')->orderByDesc('fecha_inicio')->value('id_periodo'),
            'id_modalidad_ingreso' => $this->idModalidad ?? (int) DB::table('core.modalidad_ingreso')->where('codigo', 'ORD')->value('id_modalidad'),
            'estado' => 'Activo',
        ]);
    }

    /**
     * @param  array<string, mixed>  $clave
     * @param  array<string, mixed>  $valores
     */
    private function upsert(string $tabla, string $pk, array $clave, array $valores): int
    {
        DB::table($tabla)->updateOrInsert($clave, $valores);

        return (int) DB::table($tabla)->where($clave)->value($pk);
    }
}
