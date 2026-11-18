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
 * Requiere haber corrido `module:migrate seguimiento-egresado` (los catálogos vienen en la migración). Para entrar con las
 * cuentas de demostración hace falta DatabaseSeeder del portal. Se puede ejecutar más de una vez.
 */
class SeguimientoEgresadoSeeder extends Seeder
{
    /** correo => [código de rol_interno, nombre]. Son los usuarios internos que esperan los datos de prueba. */
    private const PERSONAL = [
        'secretaria.informatica@unitru.edu.pe' => ['SECRETARIA', 'Secretaría Académica de Informática'],
        'direccion.informatica@unitru.edu.pe' => ['DIRECTOR', 'Dirección de la Escuela de Informática'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $this->personal();
            $this->call([DatosPruebaSeeder::class, EmpresasIndicadoresDemoSeeder::class, EgresadosDemoSeeder::class, PadronSinteticoSeeder::class]);
        });
    }

    /** Crea los usuarios internos de los datos de prueba si faltan. Quien inicia sesión en el portal obtiene el suyo al entrar. */
    private function personal(): void
    {
        foreach (self::PERSONAL as $correo => [$rol, $nombre]) {
            DB::table('seguimiento_egresado.usuario_interno')->insertOrIgnore([
                'id_rol' => DB::table('seguimiento_egresado.rol_interno')->where('codigo', $rol)->value('id'),
                'nombre_completo' => $nombre,
                'correo_institucional' => $correo,
                'password_hash' => '!portal',
                'activo' => true,
            ]);
        }
    }
}
