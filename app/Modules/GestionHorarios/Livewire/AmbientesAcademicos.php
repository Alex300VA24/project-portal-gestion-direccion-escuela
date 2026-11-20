<?php

namespace App\Modules\GestionHorarios\Livewire;

use App\Modules\GestionHorarios\Policies\RegistroPolicy;
use App\Modules\GestionHorarios\Services\AmbitoConsultaAdministrativa;
use App\Modules\GestionHorarios\Services\ConsultaAmbientes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

final class AmbientesAcademicos extends Component
{
    public string $periodo = '';

    public string $buscar = '';

    public string $tipo = '';

    public string $ambiente = '';

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
        $this->ambiente = '';
    }

    public function updatedTipo(): void
    {
        $this->ambiente = '';
    }

    public function render(): View
    {
        try {
            $periodo = ctype_digit($this->periodo) && strlen($this->periodo) < 19 ? (int) $this->periodo : null;
            $ambiente = ctype_digit($this->ambiente) && strlen($this->ambiente) < 19 ? (int) $this->ambiente : null;
            $datos = (new ConsultaAmbientes(DB::connection()))->consultar($periodo, $ambiente, mb_substr($this->buscar, 0, 120), mb_substr($this->tipo, 0, 40));
            $datos['errorConsulta'] = false;
        } catch (QueryException $e) {
            report($e);
            $datos = ['periodos' => collect(), 'periodoValido' => false, 'tipos' => collect(), 'ambientes' => collect(), 'seleccion' => null, 'errorConsulta' => true];
        }

        return view('modules.gestion-horarios.ambientes', $datos);
    }
}
