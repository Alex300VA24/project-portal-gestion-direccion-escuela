<?php

namespace App\Modules\GestionHorarios\Livewire;

use App\Modules\GestionHorarios\Policies\RegistroPolicy;
use App\Modules\GestionHorarios\Repositories\ConsultaPeriodosRepository;
use App\Modules\GestionHorarios\Services\ConsultaLocalDemostracion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

final class PeriodosAcademicos extends Component
{
    public string $buscar = '';

    public string $estado = '';

    public string $detalle = '';

    public function boot(): void
    {
        abort_unless(auth()->user() && (new RegistroPolicy)->viewAny(auth()->user()), 403);
        ConsultaLocalDemostracion::exigir(auth()->user());
    }

    public function mount(): void
    {
        $valor = request()->query('periodo', session('gestion_horarios.periodo_seleccionado', ''));
        if ((is_string($valor) || is_int($valor)) && ctype_digit((string)$valor) && strlen((string)$valor)<19) { $this->detalle=(string)$valor; }
    }

    public function seleccionar(int $id): void
    {
        abort_unless((new ConsultaPeriodosRepository(DB::connection()))->detalle($id), 404);
        session()->put('gestion_horarios.periodo_seleccionado', $id);
        $this->detalle = (string) $id;
    }

    public function render(): View
    {
        try {
            $repo = new ConsultaPeriodosRepository(DB::connection());
            $periodos = $repo->listar(mb_substr($this->buscar, 0, 80), mb_substr($this->estado, 0, 30));
            $id = ctype_digit($this->detalle) && strlen($this->detalle) < 19 ? (int) $this->detalle : null;
            $detallePeriodo = $id ? $repo->detalle($id) : null;
            $bloques = $detallePeriodo ? $repo->bloques($id) : collect();
            $bloqueos = $detallePeriodo ? $repo->bloqueos($id) : collect();
            $restricciones = $detallePeriodo ? $repo->restricciones($id) : collect();
            $error = false;
        } catch (QueryException $e) {
            report($e);
            $periodos = $bloques = $bloqueos = $restricciones = collect();
            $detallePeriodo = null;
            $error = true;
        }

        return view('modules.gestion-horarios.periodos', compact('periodos', 'detallePeriodo', 'bloques', 'bloqueos', 'restricciones', 'error'));
    }
}
