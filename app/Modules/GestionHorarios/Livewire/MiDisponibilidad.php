<?php

namespace App\Modules\GestionHorarios\Livewire;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorIdentidadDocente;
use App\Modules\GestionHorarios\Services\ConsultaDisponibilidadPropia;
use App\Modules\GestionHorarios\Services\IdentidadDocenteNoDisponible;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

final class MiDisponibilidad extends Component
{
    public string $periodo = '';

    public function boot(): void
    {
        abort_unless(auth()->user() instanceof User && auth()->user()->hasRole('profesor', 'web'), 403);
    }

    public function render(): View
    {
        $usuario = auth()->user();
        abort_unless($usuario instanceof User, 403);
        $proveedor = app()->bound(ProveedorIdentidadDocente::class) ? app(ProveedorIdentidadDocente::class) : new IdentidadDocenteNoDisponible;
        try {
            $periodo = ctype_digit($this->periodo) && strlen($this->periodo) < 19 ? (int) $this->periodo : null;
            $datos = (new ConsultaDisponibilidadPropia(DB::connection(), $proveedor))->consultar($usuario, $periodo);
            $datos['error_consulta'] = false;
        } catch (QueryException $e) {
            report($e);
            $datos = ['error_consulta' => true];
        }

        return view('modules.gestion-horarios.mi-disponibilidad', $datos);
    }
}
