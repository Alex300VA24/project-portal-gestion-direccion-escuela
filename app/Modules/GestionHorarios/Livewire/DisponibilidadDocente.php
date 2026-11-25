<?php

namespace App\Modules\GestionHorarios\Livewire;

use App\Modules\GestionHorarios\Policies\RegistroPolicy;
use App\Modules\GestionHorarios\Services\AmbitoConsultaAdministrativa;
use App\Modules\GestionHorarios\Services\ConsultaDisponibilidadDocente;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

final class DisponibilidadDocente extends Component
{
    public string $periodo = '';

    public string $buscar = '';

    public string $docente = '';

    public function boot(): void
    {
        abort_unless(auth()->user() && (new RegistroPolicy)->viewAny(auth()->user()), 403);
        (new AmbitoConsultaAdministrativa)->denegarRecursoGlobal(auth()->user());
    }

    public function mount(): void
    {
        $periodo = (string) session('gestion_horarios.periodo_seleccionado', '');
        if ($periodo !== '' && ctype_digit($periodo) && strlen($periodo) < 19) {
            $this->periodo = $periodo;
        }
    }

    public function updatedBuscar(): void
    {
        $this->docente = '';
    }

    public function render(): View
    {
        try {
            $periodo = ctype_digit($this->periodo) && strlen($this->periodo) < 19 ? (int) $this->periodo : null;
            $docente = ctype_digit($this->docente) && strlen($this->docente) < 19 ? (int) $this->docente : null;
            $datos = (new ConsultaDisponibilidadDocente(DB::connection()))->consultar($periodo, $docente, mb_substr($this->buscar, 0, 120));
            $datos['errorConsulta'] = false;
        } catch (QueryException $e) {
            report($e);
            $datos = ['periodos' => collect(), 'docentes' => collect(), 'periodoValido' => false, 'docenteValido' => false, 'errorConsulta' => true];
        }

        return view('modules.gestion-horarios.disponibilidad-docente', $datos);
    }
}
