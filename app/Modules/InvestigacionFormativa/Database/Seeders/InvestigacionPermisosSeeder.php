<?php

namespace App\Modules\InvestigacionFormativa\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class InvestigacionPermisosSeeder extends Seeder
{
    /**
     * Permisos del módulo y roles que los reciben de forma aditiva.
     *
     * @var array<string, list<string>>
     */
    private const GRANTS = [
        'investigacion-formativa.view' => ['director_escuela', 'profesor', 'alumno'],
        'investigacion-formativa.courses.manage' => ['director_escuela'],
        'investigacion-formativa.surveys.manage' => ['director_escuela'],
        'investigacion-formativa.reports.view' => ['director_escuela'],
        'investigacion-formativa.configuration.manage' => ['profesor'],
        'investigacion-formativa.groups.review' => ['profesor'],
        'investigacion-formativa.evaluations.manage' => ['profesor'],
        'investigacion-formativa.groups.create' => ['alumno'],
        'investigacion-formativa.projects.update' => ['alumno'],
        'investigacion-formativa.submissions.create' => ['alumno'],
        'investigacion-formativa.surveys.respond' => ['alumno'],
        'investigacion-formativa.files.download' => ['director_escuela', 'profesor', 'alumno'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::GRANTS as $permissionName => $roles) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);

            foreach ($roles as $roleName) {
                $role = Role::firstOrCreate([
                    'name' => $roleName,
                    'guard_name' => 'web',
                ]);

                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
