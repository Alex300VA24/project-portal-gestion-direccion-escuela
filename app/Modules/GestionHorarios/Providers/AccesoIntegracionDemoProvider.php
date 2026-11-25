<?php

namespace App\Modules\GestionHorarios\Providers;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Modules\GestionHorarios\Contracts\ProveedorPermisoGestionAcademica;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/** Exclusivamente para la prueba local de integración; no sustituye a Seguridad/Core. */
final class AccesoIntegracionDemoProvider extends ServiceProvider
{
    private const BASE = 'portal_integracion_pm_gh';
    private const CORREO = 'director@portal.local';

    private static function configurado(): bool
    {
        return app()->environment('local', 'testing')
            && config('horarios_integracion.acceso_demo') === true
            && config('database.default') === 'pgsql'
            && config('database.connections.pgsql.database') === self::BASE
            && in_array(config('database.connections.pgsql.host'), ['127.0.0.1', 'localhost', '::1'], true);
    }

    public static function escuelaDemo(): ?int
    {
        if (! self::configurado()) {
            return null;
        }
        $db = DB::connection();
        if ($db->getDriverName() !== 'pgsql' || $db->selectOne('SELECT current_database() AS nombre')->nombre !== self::BASE) {
            return null;
        }
        $ids = $db->table('core.plan_estudios as p')
            ->join('core.escuela_profesional as e', 'e.id_escuela', '=', 'p.id_escuela')
            ->where('p.codigo', 'PLAN-2018-INF')->where('e.codigo', 'EP-INF')
            ->distinct()->pluck('p.id_escuela');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    public static function directorDemo(User $usuario): bool
    {
        return $usuario->email === self::CORREO && $usuario->hasRole('director_escuela', 'web');
    }

    public function register(): void
    {
        if (! self::configurado()) {
            return;
        }
        // No sustituir proveedores institucionales registrados previamente.
        if (! $this->app->bound(ProveedorEscuelasAutorizadas::class)) {
            $this->app->singleton(ProveedorEscuelasAutorizadas::class, fn () => new class implements ProveedorEscuelasAutorizadas
            {
                public function escuelasVigentes(User $usuario): array
                {
                    if (! AccesoIntegracionDemoProvider::directorDemo($usuario)) {
                        return [];
                    }
                    $escuela = AccesoIntegracionDemoProvider::escuelaDemo();
                    return $escuela ? [$escuela] : [];
                }
            });
        }
        if (! $this->app->bound(ProveedorPermisoGestionAcademica::class)) {
            $this->app->singleton(ProveedorPermisoGestionAcademica::class, fn () => new class implements ProveedorPermisoGestionAcademica
            {
                public function puedeGestionar(User $usuario): bool
                {
                    return AccesoIntegracionDemoProvider::directorDemo($usuario)
                        && AccesoIntegracionDemoProvider::escuelaDemo() !== null
                        && $usuario->can('gestion-horarios.view');
                }
            });
        }
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole() || ! self::configurado()) {
            return;
        }
        Artisan::command('horarios:habilitar-director-demo', function () {
            $escuela = AccesoIntegracionDemoProvider::escuelaDemo();
            $usuario = User::query()->where('email', 'director@portal.local')->first();
            if (! $escuela || ! $usuario || ! AccesoIntegracionDemoProvider::directorDemo($usuario)) {
                $this->error('No se verificó la base, escuela o director de demostración. No se concedieron permisos.');
                return 1;
            }
            Permission::findOrCreate('gestion-horarios.view', 'web');
            $usuario->givePermissionTo('gestion-horarios.view');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->info('Permiso de consulta concedido al director demo. Escuela: '.$escuela);
            return 0;
        })->purpose('Habilitar exclusivamente al director de la base local de integración');
    }
}
