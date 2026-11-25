<?php

namespace App\Modules\GestionHorarios\Livewire;

use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Modules\GestionHorarios\Livewire\Concerns\SeleccionPlan;
use App\Modules\GestionHorarios\Policies\RegistroPolicy;
use App\Modules\GestionHorarios\Services\AmbitoConsultaAdministrativa;
use App\Modules\GestionHorarios\Services\AutorizacionGestionAcademica;
use App\Modules\GestionHorarios\Services\ConsultaDistribucionSecciones;
use App\Modules\GestionHorarios\Services\GestionOfertaSeccion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

final class DistribucionSecciones extends Component
{
    use SeleccionPlan;

    public string $periodo = '';

    public string $buscar = '';

    public string $ciclo = '';

    public array $ofertasSeleccionadas = [];

    public string $mensajeAsociacion = '';

    public function asociar(int $seccion, ?int $esperada): void
    {
        $this->mensajeAsociacion = '';
        $this->resetErrorBag('asociacion');
        try {
            $periodo = ctype_digit($this->periodo) && strlen($this->periodo) < 19 ? (int) $this->periodo : 0;
            $seleccion = $this->ofertasSeleccionadas[$seccion] ?? '';
            $oferta = is_string($seleccion) && ctype_digit($seleccion) && strlen($seleccion) < 19 ? (int) $seleccion : 0;
            $proveedor = app()->bound(ProveedorEscuelasAutorizadas::class) ? app(ProveedorEscuelasAutorizadas::class) : null;
            (new GestionOfertaSeccion(DB::connection(), $proveedor))->asociar(auth()->user(), $periodo, $seccion, $oferta, $esperada);
            $this->mensajeAsociacion = 'Asociación registrada.';
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            report($e);
            $this->addError('asociacion', 'No se pudo guardar la asociación. Intente nuevamente o comuníquese con soporte.');
        }
    }

    public function boot(): void
    {
        abort_unless(auth()->user() && (new RegistroPolicy)->viewAny(auth()->user()), 403);
        (new AmbitoConsultaAdministrativa)->exigirEscuela(auth()->user());
    }

    public function mount(): void
    {
        $periodo = (string) request()->query('periodo', session('gestion_horarios.periodo_seleccionado', ''));
        if ($periodo !== '' && ctype_digit($periodo) && strlen($periodo) < 19) {
            $this->periodo = $periodo;
        }
        $plan = (string) request()->query('plan', '');
        if ($plan !== '' && ctype_digit($plan) && strlen($plan) < 19) {
            $this->plan = $plan;
        }
    }

    public function render(): View
    {
        try {
            $id = ctype_digit($this->periodo) && strlen($this->periodo) < 19 ? (int) $this->periodo : null;
            $contexto = $this->contextoPlan();
            $ciclo = $this->ciclo !== '' && ctype_digit($this->ciclo) && $contexto['ciclos']->contains((int) $this->ciclo) ? $this->ciclo : '';
            $datos = (new ConsultaDistribucionSecciones(DB::connection()))->consultar($id, mb_substr($this->buscar, 0, 120), $ciclo, $contexto['planId']) + $contexto;
            $datos['errorConsulta'] = false;
            $datos['escrituraDisponible'] = $contexto['planValido'] && (new AutorizacionGestionAcademica)->permite(auth()->user());
        } catch (QueryException $e) {
            report($e);
            $datos = ['periodos' => collect(), 'demanda' => null, 'cursos' => collect(), 'integridad' => null, 'asociacionDisponible' => false, 'escrituraDisponible' => false, 'errorConsulta' => true];
        }

        return view('modules.gestion-horarios.secciones', $datos);
    }
}
