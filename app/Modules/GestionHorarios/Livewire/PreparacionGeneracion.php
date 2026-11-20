<?php

namespace App\Modules\GestionHorarios\Livewire;

use App\Modules\GestionHorarios\Demand\RepositorioDemandaPostgres;
use App\Modules\GestionHorarios\Policies\RegistroPolicy;
use App\Modules\GestionHorarios\Repositories\CalendarioAcademicoRepository;
use App\Modules\GestionHorarios\Repositories\ConsultaPeriodosRepository;
use App\Modules\GestionHorarios\Repositories\OfertaAcademicaRepository;
use App\Modules\GestionHorarios\Repositories\PoblacionAcademicaRepository;
use App\Modules\GestionHorarios\Services\AmbitoConsultaAdministrativa;
use App\Modules\GestionHorarios\Services\CalendarioAcademico;
use App\Modules\GestionHorarios\Services\PoblacionAcademica;
use App\Modules\GestionHorarios\Services\PrevalidacionGeneracion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

final class PreparacionGeneracion extends Component
{
    public string $periodo = '';

    public array $resultados = [];

    public bool $comprobado = false;

    public bool $falloComprobacion = false;

    public function boot(): void
    {
        abort_unless(auth()->user() && (new RegistroPolicy)->viewAny(auth()->user()), 403);
        (new AmbitoConsultaAdministrativa)->denegarRecursoGlobal(auth()->user());
    }

    public function mount(): void
    {
        $this->periodo = (string) session('gestion_horarios.periodo_seleccionado', '');
    }

    private function idPeriodo(): int
    {
        return ctype_digit($this->periodo) && strlen($this->periodo) < 19 ? (int) $this->periodo : 0;
    }

    public function updatedPeriodo(): void
    {
        $this->resultados = [];
        $this->comprobado = false;
        $this->falloComprobacion = false;
        try {
            if ($this->idPeriodo() > 0 && (new ConsultaPeriodosRepository(DB::connection()))->detalle($this->idPeriodo())) {
                session()->put('gestion_horarios.periodo_seleccionado', $this->idPeriodo());
            }
        } catch (QueryException $e) {
            report($e);
            $this->falloComprobacion = true;
        }
    }

    public function comprobar(): void
    {
        $this->resultados = [];
        $this->comprobado = false;
        try {
            $this->resultados = (new PrevalidacionGeneracion(DB::connection()))->evaluar($this->idPeriodo());
            $this->comprobado = true;
            $this->falloComprobacion = false;
        } catch (QueryException $e) {
            report($e);
            $this->falloComprobacion = true;
        }
    }

    public function render(): View
    {
        $periodos = collect();
        $detalle = $demanda = $poblacion = $calendario = null;
        $errorConsulta = false;
        try {
            $db = DB::connection();
            $periodos = (new OfertaAcademicaRepository($db))->periodos();
            if ($this->idPeriodo() > 0) {
                $detalle = (new ConsultaPeriodosRepository($db))->detalle($this->idPeriodo());
                if ($detalle) {
                    $demanda = (new RepositorioDemandaPostgres($db))->vigente($this->idPeriodo());
                    $secciones = (new PoblacionAcademicaRepository($db))->secciones($this->idPeriodo());
                    $poblacion = (new PoblacionAcademica)->analizar($this->idPeriodo(), $secciones, $demanda ? (array) $demanda : null);
                    $periodosRepo = new ConsultaPeriodosRepository($db);
                    $bloques = $periodosRepo->bloques($this->idPeriodo())->map(fn ($b) => (array) $b)->all();
                    $bloqueos = $periodosRepo->bloqueos($this->idPeriodo())->map(fn ($b) => (array) $b)->all();
                    $actividades = (new CalendarioAcademicoRepository($db))->actividades($this->idPeriodo());
                    $calendario = (new CalendarioAcademico)->evaluar((array) $detalle, $bloques, $actividades, null, $bloqueos);
                }
            }
        } catch (QueryException $e) {
            report($e);
            $errorConsulta = true;
        }

        return view('modules.gestion-horarios.preparacion', compact('periodos', 'detalle', 'demanda', 'poblacion', 'calendario', 'errorConsulta'));
    }
}
