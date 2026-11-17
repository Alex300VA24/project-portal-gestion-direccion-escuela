<?php

namespace App\Modules\InvestigacionFormativa\Providers;

use App\Modules\InvestigacionFormativa\Commands\ImportarCompetenciasCommand;
use App\Modules\InvestigacionFormativa\Commands\ImportarDirectoresCommand;
use App\Modules\InvestigacionFormativa\Commands\ImportarIdentidadesCommand;
use App\Modules\InvestigacionFormativa\Commands\ImportarParticipantesCommand;
use App\Modules\InvestigacionFormativa\Models\Encuesta;
use App\Modules\InvestigacionFormativa\Models\Entrega;
use App\Modules\InvestigacionFormativa\Models\Grupo;
use App\Modules\InvestigacionFormativa\Models\Proyecto;
use App\Modules\InvestigacionFormativa\Policies\EncuestaPolicy;
use App\Modules\InvestigacionFormativa\Policies\EntregaPolicy;
use App\Modules\InvestigacionFormativa\Policies\GrupoPolicy;
use App\Modules\InvestigacionFormativa\Policies\ProyectoPolicy;
use App\Modules\InvestigacionFormativa\Policies\SeccionPolicy;
use App\Modules\InvestigacionFormativa\Support\ArchivoGateway;
use App\Modules\InvestigacionFormativa\Support\UnavailableArchivoGateway;
use App\Shared\Models\Core\Seccion;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class InvestigacionFormativaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ArchivoGateway::class, UnavailableArchivoGateway::class);
    }

    public function boot(): void
    {
        Gate::policy(Seccion::class, SeccionPolicy::class);
        Gate::policy(Grupo::class, GrupoPolicy::class);
        Gate::policy(Proyecto::class, ProyectoPolicy::class);
        Gate::policy(Entrega::class, EntregaPolicy::class);
        Gate::policy(Encuesta::class, EncuestaPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportarIdentidadesCommand::class,
                ImportarDirectoresCommand::class,
                ImportarParticipantesCommand::class,
                ImportarCompetenciasCommand::class,
            ]);
        }
    }
}
