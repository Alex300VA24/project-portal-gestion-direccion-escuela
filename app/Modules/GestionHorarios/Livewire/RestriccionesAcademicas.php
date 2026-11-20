<?php

namespace App\Modules\GestionHorarios\Livewire;

use App\Modules\GestionHorarios\Policies\RegistroPolicy;
use App\Modules\GestionHorarios\Repositories\ConsultaRestriccionesRepository;
use App\Modules\GestionHorarios\Repositories\OfertaAcademicaRepository;
use App\Modules\GestionHorarios\Services\AmbitoConsultaAdministrativa;
use App\Modules\GestionHorarios\Services\PrevalidacionGeneracion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

final class RestriccionesAcademicas extends Component
{
    public string $periodo = '';

    public string $estado = '';

    public array $resultados = [];

    public bool $consultaFallida = false;

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

    public function updatedPeriodo(): void
    {
        $this->resultados = [];
        $this->consultaFallida = false;
    }

    public function prevalidar(): void
    {
        try {
            $periodo = ctype_digit($this->periodo) && strlen($this->periodo) < 19 ? (int) $this->periodo : 0;
            $this->resultados = (new PrevalidacionGeneracion(DB::connection()))->evaluar($periodo);
            $this->consultaFallida = false;
        } catch (QueryException $e) {
            report($e);
            $this->resultados = [];
            $this->consultaFallida = true;
        }
    }

    public function render(): View
    {
        try {
            $db = DB::connection();
            $periodos = (new OfertaAcademicaRepository($db))->periodos();
            $periodo = ctype_digit($this->periodo) && strlen($this->periodo) < 19 ? (int) $this->periodo : null;
            $restricciones = (new ConsultaRestriccionesRepository($db))->consultar($periodo);
            if ($this->estado === 'activa') {
                $restricciones = $restricciones->filter(fn ($r) => (bool) $r->activa);
            }
            if ($this->estado === 'inactiva') {
                $restricciones = $restricciones->reject(fn ($r) => (bool) $r->activa);
            }
            $errorConsulta = false;
        } catch (QueryException $e) {
            report($e);
            $periodos = $restricciones = collect();
            $errorConsulta = true;
        }

        return view('modules.gestion-horarios.restricciones', compact('periodos', 'restricciones', 'errorConsulta'));
    }
}
