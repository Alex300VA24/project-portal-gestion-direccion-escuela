<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea los permisos de COTECU y los asigna a los roles institucionales.
 *
 * Idempotente: se puede ejecutar cuantas veces sea necesario.
 * Uso: php artisan db:seed --class=CotecuPermissionsSeeder
 *
 * Los roles son compartidos con los demás módulos, por eso se usa
 * givePermissionTo (suma) y jamás syncPermissions (borraría permisos ajenos).
 */
class CotecuPermissionsSeeder extends Seeder
{
    /**
     * Permisos del módulo. Los nombres son contrato con rutas, policies y
     * vistas: no se renombran una vez entregados; un permiso nuevo es una
     * línea nueva aquí.
     */
    private const PERMISSIONS = [
        'cotecu.view',
        'cotecu.silabo.upload',
        'cotecu.silabo.list',
        'cotecu.silabo.review',
    ];

    /**
     * Asignación rol institucional => permisos de COTECU.
     * El alumno no recibe permisos hasta que se decida su acceso al módulo.
     */
    private const ROLE_PERMISSIONS = [
        'director_escuela' => [
            'cotecu.view',
            'cotecu.silabo.list',
            'cotecu.silabo.review',
        ],
        'secretaria' => [
            'cotecu.view',
            'cotecu.silabo.list',
        ],
        'profesor' => [
            'cotecu.view',
            'cotecu.silabo.upload',
        ],
        'alumno' => [],
    ];

    public function run(): void
    {
        $missing = collect(self::ROLE_PERMISSIONS)
            ->keys()
            ->reject(fn (string $role): bool => Role::where('name', $role)
                ->where('guard_name', 'web')
                ->exists(),
            )
            ->values();

        if ($missing->isNotEmpty()) {
            $this->command?->error(
                'Faltan roles institucionales: '.$missing->implode(', ').
                '. Ejecuta primero `php artisan db:seed` (DatabaseSeeder) y vuelve a intentarlo.',
            );

            return;
        }

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::ROLE_PERMISSIONS as $role => $permissions) {
            if ($permissions === []) {
                continue;
            }

            Role::findByName($role, 'web')->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Permisos de COTECU sincronizados con los roles institucionales.');
    }
}
