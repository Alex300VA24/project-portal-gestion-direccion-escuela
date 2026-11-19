<?php

namespace App\Modules\GestionPracticas\Database\Seeders;

use App\Modules\GestionPracticas\Models\CriterioEvaluacion;
use App\Modules\GestionPracticas\Support\Acceso;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles, permisos y criterios de evaluación del módulo. Es idempotente.
 *
 *   php artisan db:seed --class="App\Modules\GestionPracticas\Database\Seeders\GestionPracticasAccesoSeeder"
 */
class GestionPracticasAccesoSeeder extends Seeder
{
    /** Criterios del docente supervisor; los pesos suman 100. */
    private const CRITERIOS = [
        'Cumplimiento de las funciones asignadas' => 25,
        'Conocimientos técnicos aplicados' => 25,
        'Responsabilidad y puntualidad' => 20,
        'Trabajo en equipo y comunicación' => 15,
        'Calidad del informe final' => 15,
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Acceso::permisos() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        foreach (Acceso::roles() as $rol => $permisos) {
            Role::findOrCreate($rol, 'web')->syncPermissions($permisos);
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::CRITERIOS as $nombre => $peso) {
            CriterioEvaluacion::updateOrCreate(
                ['nombre' => $nombre],
                ['evaluador' => 'supervisor', 'peso' => $peso, 'activo' => true],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
