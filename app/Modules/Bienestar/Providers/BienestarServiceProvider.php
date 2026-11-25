<?php

namespace App\Modules\Bienestar\Providers;

use App\Models\User;
use App\Modules\Bienestar\Adapters\AdaptadorMatriculaLocal;
use App\Modules\Bienestar\Console\Commands\DetectarEstudiantesRiesgoCommand;
use App\Modules\Bienestar\Contracts\FuenteAcademicaInterface;
use App\Modules\Bienestar\Middleware\EnsurePasswordChanged;
use App\Modules\Bienestar\Services\InicioBienestarService;
use App\Modules\Bienestar\Services\NotificacionService;
use App\Modules\Bienestar\Support\ActorBienestarResolver;
use App\Modules\Bienestar\Support\BienestarPermissions;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class BienestarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FuenteAcademicaInterface::class, AdaptadorMatriculaLocal::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                DetectarEstudiantesRiesgoCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        // Registrar Gates para aceptar indistintamente el permiso o su rol Spatie equivalente
        foreach (BienestarPermissions::MAP as $permission => $role) {
            Gate::define($permission, function ($user) use ($permission) {
                return BienestarPermissions::check($user, $permission);
            });
        }

        Route::aliasMiddleware('password.change', EnsurePasswordChanged::class);
        Blade::anonymousComponentPath(resource_path('views/modules/bienestar/components'), 'bienestar');

        // Al cambiar su propia contraseña se levanta la obligación del primer ingreso
        User::updated(function (User $user) {
            if ($user->wasChanged('password') && auth()->id() === $user->getKey() && DB::getDriverName() === 'pgsql') {
                DB::table('seguridad.usuario')->where('correo_institucional', $user->email)
                    ->update(['contrasena_hash' => $user->password, 'debe_cambiar_contrasena' => false]);
            }
        });

        // Pantalla de acceso denegado propia, solo en las rutas del módulo
        $this->callAfterResolving(ExceptionHandler::class, function ($handler) {
            $handler->renderable(function (HttpExceptionInterface $e, Request $request) {
                if ($e->getStatusCode() === 403 && $request->is('bienestar', 'bienestar/*') && ! $request->expectsJson()) {
                    return response()->view('modules.bienestar.errors.403', ['exception' => $e], 403);
                }
            });
        });

        View::composer(['modules.bienestar.layout', 'modules.bienestar.notificaciones.index'], function ($view) {
            $id = auth()->check() ? ActorBienestarResolver::resolve(auth()->user())->idUsuario : null;
            $servicio = app(NotificacionService::class);
            $view->with('avisosSinLeer', $id ? $servicio->sinLeer($id) : 0)
                ->with('avisosRecientes', $id ? $servicio->propias($id)->limit(5)->get() : collect());
        });
        View::composer('modules.bienestar.index', fn ($view) => $view->with('resumen', app(InicioBienestarService::class)->resumen(auth()->user())));

        // Programar el comando de detección de riesgo una vez al día a las 02:00 (America/Lima)
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('bienestar:detectar-riesgo')
                ->dailyAt('02:00')
                ->timezone('America/Lima');
        });
    }
}
