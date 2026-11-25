<?php

namespace App\Modules\GestionHorarios\Livewire;

use App\Modules\GestionHorarios\Livewire\Concerns\SeleccionPlan;
use App\Modules\GestionHorarios\Policies\RegistroPolicy;
use App\Modules\GestionHorarios\Services\AmbitoConsultaAdministrativa;
use App\Modules\GestionHorarios\Services\ConsultaAsignacionDocente;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

final class AsignacionDocente extends Component
{
    use SeleccionPlan;

    public string $periodo = '';

    public string $buscar = '';

    public string $ciclo = '';

    public string $seccion = '';

    public function boot(): void
    {
        abort_unless(auth()->user() && (new RegistroPolicy)->viewAny(auth()->user()), 403);
        // El plan no acredita visibilidad de docentes compartidos entre escuelas.
        (new AmbitoConsultaAdministrativa)->denegarRecursoGlobal(auth()->user());
    }

    public function mount(): void
    {
        $periodo = (string) session('gestion_horarios.periodo_seleccionado', '');
        if ($periodo !== '' && ctype_digit($periodo) && strlen($periodo) < 19) {
            $this->periodo = $periodo;
        }
    }

    public function render(): View
    {
        try {
            $id = ctype_digit($this->periodo) && strlen($this->periodo) < 19 ? (int) $this->periodo : null;
            $contexto = $this->contextoPlan();
            $ciclo = $this->ciclo !== '' && ctype_digit($this->ciclo) && $contexto['ciclos']->contains((int) $this->ciclo) ? $this->ciclo : '';
            $datos = (new ConsultaAsignacionDocente(DB::connection()))->consultar($id, mb_substr($this->buscar, 0, 120), $ciclo, mb_substr($this->seccion, 0, 30), $contexto['planId']) + $contexto;
            $datos['errorConsulta'] = false;
        } catch (QueryException $e) {
            report($e);
            $datos = ['periodos' => collect(), 'docentes' => collect(), 'actividades' => collect(), 'periodoValido' => false, 'errorConsulta' => true];
        }

        return view('modules.gestion-horarios.asignacion-docente', $datos);
    }
}
