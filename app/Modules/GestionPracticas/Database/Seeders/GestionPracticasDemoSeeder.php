<?php

namespace App\Modules\GestionPracticas\Database\Seeders;

use App\Models\User;
use App\Modules\GestionPracticas\Support\Acceso;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de DESARROLLO: base académica mínima y una cuenta por actor del módulo.
 * No usar en producción. Es idempotente.
 *
 *   php artisan db:seed --class="App\Modules\GestionPracticas\Database\Seeders\GestionPracticasDemoSeeder"
 *
 * Cuentas (contraseña Portal2026!): practicas.admin@, practicas.empresa@, practicas.estudiante@
 * y practicas.supervisor@portal.local
 */
class GestionPracticasDemoSeeder extends Seeder
{
    private const PASSWORD = 'Portal2026!';

    public function run(): void
    {
        $this->call(GestionPracticasAccesoSeeder::class);

        $facultad = $this->asegurar('core.facultad', ['codigo' => 'FAC-CFM'], ['nombre' => 'Facultad de Ciencias Físicas y Matemáticas'], 'id_facultad');
        $escuela = $this->asegurar('core.escuela_profesional', ['codigo' => 'EP-INF'], ['id_facultad' => $facultad, 'nombre' => 'Ingeniería Informática', 'estado' => 'Activo'], 'id_escuela');
        $departamento = $this->asegurar('core.departamento_academico', ['codigo' => 'DA-INF'], ['id_facultad' => $facultad, 'nombre' => 'Informática'], 'id_departamento');
        $periodo = $this->asegurar('core.periodo_academico', ['codigo' => '2026-II'], [
            'anio' => 2026, 'tipo' => 'II', 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-12-20',
            'numero_semanas' => 17, 'estado' => 'Activo',
        ], 'id_periodo');
        $modalidad = $this->asegurar('core.modalidad_ingreso', ['codigo' => 'ORD'], ['nombre' => 'Ordinario', 'activo' => true], 'id_modalidad');
        $plan = $this->asegurar('core.plan_estudios', ['codigo' => 'PLAN-2026'], [
            'id_escuela' => $escuela, 'nombre' => 'Plan de estudios 2026', 'version' => '2026', 'anio_aprobacion' => 2026,
            'fecha_vigencia_inicio' => '2026-01-01', 'es_predeterminado' => true, 'estado' => 'Vigente',
        ], 'id_plan');

        $admin = $this->cuenta('practicas.admin@portal.local', 'Administrador de Prácticas', '70010001', 'Administrador', 'Prácticas', Acceso::ADMINISTRADOR);
        $empresaUsuario = $this->cuenta('practicas.empresa@portal.local', 'TechNova SAC', '70010002', 'Rosa', 'Empresa', Acceso::EMPRESA);
        $estudiante = $this->cuenta('practicas.estudiante@portal.local', 'Estudiante de Prácticas', '70010003', 'Elena', 'Estudiante', Acceso::ESTUDIANTE);
        $supervisor = $this->cuenta('practicas.supervisor@portal.local', 'Docente Supervisor', '70010004', 'Samuel', 'Supervisor', Acceso::SUPERVISOR);

        $this->asegurar('core.estudiante', ['codigo_universitario' => 'E2026DEMO'], [
            'id_persona' => $estudiante['persona'], 'id_escuela' => $escuela, 'id_plan' => $plan,
            'id_periodo_ingreso' => $periodo, 'id_modalidad_ingreso' => $modalidad, 'estado' => 'Activo',
        ], 'id_estudiante');

        $this->asegurar('core.docente', ['codigo_docente' => 'D2026DEMO'], [
            'id_persona' => $supervisor['persona'], 'id_departamento' => $departamento, 'estado' => 'Activo',
        ], 'id_docente');

        $empresa = $this->asegurar('comun.empresa', ['ruc' => '20123456789'], [
            'razon_social' => 'TechNova S.A.C.', 'nombre_comercial' => 'TechNova', 'direccion' => 'Av. España 123, Trujillo',
            'telefono' => '044123456', 'correo' => 'contacto@technova.test', 'estado' => 'Activo',
        ], 'id_empresa');

        DB::table('gestion_practicas.empresa_usuario')->updateOrInsert(
            ['id_empresa' => $empresa, 'id_usuario' => $empresaUsuario['usuario']],
            ['es_principal' => true, 'estado' => 'activo', 'asignado_por' => $admin['usuario']],
        );

        if (! DB::table('gestion_practicas.convocatoria')->where('id_empresa', $empresa)->exists()) {
            DB::table('gestion_practicas.convocatoria')->insert([
                'id_empresa' => $empresa,
                'titulo' => 'Practicante de Desarrollo Web',
                'descripcion' => 'Apoyo en el desarrollo de aplicaciones web con Laravel y PostgreSQL.',
                'requisitos' => "Estudiante de 8.° ciclo en adelante.\nConocimientos de PHP y SQL.",
                'enlace_postulacion' => 'https://technova.test/practicas',
                'tipo_practica' => 'preprofesional',
                'modalidad' => 'hibrida',
                'vacantes' => 2,
                'fecha_publicacion' => now(),
                'fecha_cierre' => now()->addDays(30)->toDateString(),
                'estado' => 'publicada',
                'registrada_por' => $empresaUsuario['usuario'],
                'publicada_por' => $admin['usuario'],
            ]);
        }
    }

    /** @return array{persona: int, usuario: int} */
    private function cuenta(string $correo, string $nombre, string $dni, string $nombres, string $apellido, string $rol): array
    {
        $persona = $this->asegurar('core.persona', ['tipo_documento' => 'DNI', 'numero_documento' => $dni], [
            'nombres' => $nombres, 'apellido_paterno' => $apellido, 'correo_personal' => $correo,
        ], 'id_persona');

        $usuario = $this->asegurar('seguridad.usuario', ['correo_institucional' => $correo], [
            'id_persona' => $persona, 'contrasena_hash' => Hash::make(self::PASSWORD),
            'estado' => 'Activo', 'debe_cambiar_contrasena' => false,
        ], 'id_usuario');

        $login = User::updateOrCreate(['email' => $correo], [
            'name' => $nombre, 'password' => self::PASSWORD,
        ]);
        $login->forceFill(['email_verified_at' => now()])->save();
        $login->syncRoles([$rol]);

        return ['persona' => $persona, 'usuario' => $usuario];
    }

    /**
     * @param  array<string, mixed>  $clave  columnas que identifican la fila
     * @param  array<string, mixed>  $valores
     */
    private function asegurar(string $tabla, array $clave, array $valores, string $pk): int
    {
        if (! DB::table($tabla)->where($clave)->exists()) {
            DB::table($tabla)->insert($clave + $valores);
        }

        return (int) DB::table($tabla)->where($clave)->value($pk);
    }
}
