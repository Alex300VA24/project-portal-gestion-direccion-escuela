<?php

namespace App\Modules\GestionHorarios\Livewire;

use App\Modules\GestionHorarios\Policies\RegistroPolicy;
use App\Modules\GestionHorarios\Repositories\ConsultaRevisionesRepository;
use App\Modules\GestionHorarios\Services\AmbitoConsultaAdministrativa;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

final class RevisionesOferta extends Component
{
    public function boot(): void
    {
        abort_unless(auth()->user() && (new RegistroPolicy)->viewAny(auth()->user()), 403);
    }

    public function render(): View
    {
        $revisionDisponible = false;
        try {
            $escuelas = (new AmbitoConsultaAdministrativa)->escuelas(auth()->user());
            $adscripcionPendiente = $escuelas === [];
            $db = DB::connection();
            $revisionDisponible = $db->getSchemaBuilder()->hasTable('gestion_horarios.revision_oferta_pendiente');
            $revisiones = $adscripcionPendiente || ! $revisionDisponible ? collect() : (new ConsultaRevisionesRepository($db))->pendientes($escuelas);
            $error = false;
        } catch (QueryException $e) {
            report($e);
            $revisiones = collect();
            $adscripcionPendiente = false;
            $error = true;
        }

        return view('modules.gestion-horarios.revisiones', compact('revisiones', 'error', 'adscripcionPendiente', 'revisionDisponible'));
    }
}
