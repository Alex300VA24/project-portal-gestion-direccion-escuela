<?php

namespace App\Modules\GestionHorarios\Livewire;

use App\Modules\GestionHorarios\Policies\RegistroPolicy;
use App\Modules\GestionHorarios\Services\ConsultaLocalDemostracion;
use App\Modules\GestionHorarios\Services\ConsultaAmbientes;
use App\Modules\GestionHorarios\Services\ConsultaSemanalDemo;
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
        ConsultaLocalDemostracion::exigir(auth()->user());
    }

    public function mount(): void
    {
        $entrada = request()->query('periodo', session('gestion_horarios.periodo_seleccionado', ''));
        $periodo = is_scalar($entrada) ? (string) $entrada : '';
        $tipo = request()->query('tipo');
        if (in_array($tipo, ['AULA', 'LABORATORIO'], true)) { $this->tipo = $tipo; }
        $ambiente = request()->query('ambiente');
        if (is_string($ambiente) && ctype_digit($ambiente) && strlen($ambiente) < 19) { $this->ambiente = $ambiente; }
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
            $datos['ambientes'] = $datos['ambientes']->sort(fn ($a, $b) => ConsultaSemanalDemo::comparar($a->nombre ?: $a->codigo ?: '', $b->nombre ?: $b->codigo ?: '') ?: ($a->id_ambiente <=> $b->id_ambiente))->values();
            $datos['errorConsulta'] = false;
        } catch (QueryException $e) {
            report($e);
            $datos = ['periodos' => collect(), 'periodoValido' => false, 'tipos' => collect(), 'ambientes' => collect(), 'seleccion' => null, 'errorConsulta' => true];
        }

        return view('modules.gestion-horarios.ambientes', $datos);
    }
}
