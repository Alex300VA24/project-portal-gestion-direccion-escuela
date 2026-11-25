<?php

namespace App\Modules\GestionHorarios\Livewire;

use App\Modules\GestionHorarios\Policies\RegistroPolicy;
use App\Modules\GestionHorarios\Services\ConsultaLocalDemostracion;
use App\Modules\GestionHorarios\Services\ConsultaDisponibilidadDocente;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use App\Modules\GestionHorarios\Services\EditarDisponibilidadDemo;
use Livewire\Component;

final class DisponibilidadDocente extends Component
{
    public string $periodo = '';

    public string $docente = '';

    #[Locked]
    public string $versionEdicion = '';
    #[Locked]
    public string $contextoEdicion = '';
    public string $estadoEdicion = 'PENDIENTE';
    public string $tratamientoEdicion = 'ESTRICTA';
    public array $franjasEdicion = [];
    public bool $confirmarEdicion = false;
    public string $mensajeEdicion = '';

    public function abrirEdicion(): void
    {
        abort_unless(ctype_digit($this->periodo) && ctype_digit($this->docente) && strlen($this->periodo) < 19 && strlen($this->docente) < 19, 422);
        $this->cancelarEdicion();
        $this->mensajeEdicion = '';
        $datos = (new EditarDisponibilidadDemo)->leer(auth()->user(), (int) $this->periodo, (int) $this->docente);
        $this->versionEdicion = $datos['version'];
        $this->contextoEdicion = $this->periodo.':'.$this->docente;
        $this->estadoEdicion = $datos['estado'];
        $this->tratamientoEdicion = $datos['tratamiento'];
        $this->franjasEdicion = $datos['franjas'];
    }

    public function cancelarEdicion(): void
    {
        $this->reset('versionEdicion', 'contextoEdicion', 'estadoEdicion', 'tratamientoEdicion', 'franjasEdicion', 'confirmarEdicion');
        $this->resetValidation();
    }

    public function updatedPeriodo(): void { $this->cancelarEdicion(); $this->mensajeEdicion = ''; }
    public function updatedDocente(): void { $this->cancelarEdicion(); $this->mensajeEdicion = ''; }
    public function updatedEstadoEdicion(): void { $this->confirmarEdicion = false; }
    public function updatedFranjasEdicion(): void { $this->confirmarEdicion = false; }
    public function updatedTratamientoEdicion(): void { $this->confirmarEdicion = false; }

    public function limpiarFranjas(): void { $this->franjasEdicion = []; $this->confirmarEdicion = false; }

    public function guardarEdicion(): void
    {
        abort_unless($this->versionEdicion !== '' && $this->contextoEdicion === $this->periodo.':'.$this->docente, 422);
        $this->validate(['estadoEdicion' => 'required|in:PENDIENTE,PRESENTADA,NO_PRESENTADA', 'tratamientoEdicion' => 'required|in:ESTRICTA,FLEXIBLE', 'franjasEdicion' => 'array|max:1000', 'franjasEdicion.*' => 'string|max:40', 'confirmarEdicion' => 'accepted']);
        try {
            (new EditarDisponibilidadDemo)->guardar(auth()->user(), (int) $this->periodo, (int) $this->docente, $this->versionEdicion, $this->estadoEdicion, $this->tratamientoEdicion, $this->franjasEdicion);
        } catch (QueryException $e) {
            report($e);
            $this->addError('edicionDisponibilidad', 'No se pudo guardar. No se aplicaron cambios.');
            return;
        }
        $this->cancelarEdicion();
        $this->mensajeEdicion = 'Disponibilidad de demostración guardada.';
    }

    public function boot(): void
    {
        abort_unless(auth()->user() && (new RegistroPolicy)->viewAny(auth()->user()), 403);
        ConsultaLocalDemostracion::exigir(auth()->user());
    }

    public function mount(): void
    {
        $periodo = request()->query('periodo', session('gestion_horarios.periodo_seleccionado', ''));
        $periodo = is_scalar($periodo) ? (string) $periodo : '';
        if ($periodo !== '' && ctype_digit($periodo) && strlen($periodo) < 19) {
            $this->periodo = $periodo;
        }
    }

    public function render(): View
    {
        try {
            $periodo = ctype_digit($this->periodo) && strlen($this->periodo) < 19 ? (int) $this->periodo : null;
            $docente = ctype_digit($this->docente) && strlen($this->docente) < 19 ? (int) $this->docente : null;
            $datos = (new ConsultaDisponibilidadDocente(DB::connection()))->consultar($periodo, $docente, '');
            $datos['errorConsulta'] = false;
            $datos['edicionDemo'] = $datos['docenteValido'] && $datos['periodoValido'] && ConsultaLocalDemostracion::permitida(auth()->user());
        } catch (QueryException $e) {
            report($e);
            $datos = ['periodos' => collect(), 'docentes' => collect(), 'periodoValido' => false, 'docenteValido' => false, 'errorConsulta' => true];
        }

        return view('modules.gestion-horarios.disponibilidad-docente', $datos);
    }
}
