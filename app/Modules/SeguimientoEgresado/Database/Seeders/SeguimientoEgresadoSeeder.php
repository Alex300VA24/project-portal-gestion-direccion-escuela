<?php

namespace App\Modules\SeguimientoEgresado\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de demostración del módulo Seguimiento al Egresado (nombres inventados, solo para desarrollo y pruebas).
 * No forma parte de DatabaseSeeder del portal; se ejecuta aparte:
 *
 *   php artisan db:seed --class="App\Modules\SeguimientoEgresado\Database\Seeders\SeguimientoEgresadoSeeder"
 *
 * Requiere haber corrido `module:migrate seguimiento-egresado` (los catálogos vienen en la migración) y, para que la
 * autoría de los registros corresponda a las cuentas de demostración del portal, DatabaseSeeder
 * (secretaria@portal.local y director@portal.local). Se puede ejecutar más de una vez.
 */
class SeguimientoEgresadoSeeder extends Seeder
{
    /** correo del módulo => [código de rol_interno, nombre, correo de la cuenta de demostración del portal] */
    private const PERSONAL = [
        'secretaria.informatica@unitru.edu.pe' => ['SECRETARIA', 'Secretaría Académica de Informática', 'secretaria@portal.local'],
        'direccion.informatica@unitru.edu.pe' => ['DIRECTOR', 'Dirección de la Escuela de Informática', 'director@portal.local'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $this->personal();
            $this->call([DatosPruebaSeeder::class, EmpresasIndicadoresDemoSeeder::class, EgresadosDemoSeeder::class]);
        });
    }

    /** Usuarios internos que esperan los datos de prueba, enlazados con las cuentas de demostración del portal. */
    private function personal(): void
    {
        foreach (self::PERSONAL as $correo => [$rol, $nombre, $correoPortal]) {
            $idPortal = DB::table('users')->where('email', $correoPortal)->value('id');

            $existente = DB::table('seguimiento_egresado.usuario_interno')->where('correo_institucional', $correo)->value('id');
            if ($existente !== null) {
                DB::table('seguimiento_egresado.usuario_interno')->where('id', $existente)
                    ->whereNull('id_usuario_portal')->update(['id_usuario_portal' => $idPortal]);

                continue;
            }

            DB::table('seguimiento_egresado.usuario_interno')->insert([
                'id_rol' => DB::table('seguimiento_egresado.rol_interno')->where('codigo', $rol)->value('id'),
                'id_usuario_portal' => $idPortal,
                'nombre_completo' => $nombre,
                'correo_institucional' => $correo,
                'password_hash' => '!portal',
                'activo' => true,
            ]);
        }
    }
}
