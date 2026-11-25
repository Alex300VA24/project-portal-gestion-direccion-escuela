<?php

namespace App\Modules\GestionHorarios\Livewire;

use App\Modules\GestionHorarios\Livewire\Concerns\SeleccionPlan;
use App\Modules\GestionHorarios\Policies\RegistroPolicy;
use App\Modules\GestionHorarios\Services\ConsultaLocalDemostracion;
use App\Modules\GestionHorarios\Services\ConsultaAsignacionDocente;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use App\Modules\GestionHorarios\Services\ReasignarDocenteDemo;

final class AsignacionDocente extends Component
{
    use SeleccionPlan;

    public string $periodo = '';

    public string $buscar = '';

    public string $ciclo = '';

    public string $seccion = '';

    #[Locked]
    public ?int $asignacionEditar = null;

    #[Locked]
    public ?int $docenteAnterior = null;

    #[Locked]
    public string $contextoEdicion = '';

    public string $docenteNuevo = '';

    public string $mensaje = '';

    public function abrirReasignacion(int $asignacion, int $anterior): void
    {
        abort_unless(ConsultaLocalDemostracion::permitida(auth()->user()), 403);
        $contexto = $this->contextoPlan();
        abort_unless($contexto['planValido'] && ctype_digit($this->periodo) && strlen($this->periodo) < 19, 403);
        $this->cancelarReasignacion();
        $this->mensaje = '';
        $this->asignacionEditar = $asignacion;
        $this->docenteAnterior = $anterior;
        $this->contextoEdicion = $this->plan.':'.$this->periodo;
    }

    public function cancelarReasignacion(): void
    {
        $this->reset('asignacionEditar', 'docenteAnterior', 'contextoEdicion', 'docenteNuevo');
        $this->resetValidation();
    }

    public function guardarReasignacion(): void
    {
        abort_unless(ConsultaLocalDemostracion::permitida(auth()->user()), 403);
        abort_unless($this->asignacionEditar !== null && $this->docenteAnterior !== null
            && $this->contextoEdicion === $this->plan.':'.$this->periodo, 422);
        $this->validate(['docenteNuevo' => 'required|integer|min:1']);
        $contexto = $this->contextoPlan();
        abort_unless($contexto['planValido'] && ctype_digit($this->periodo) && strlen($this->periodo) < 19, 403);
        try {
            (new ReasignarDocenteDemo)->guardar(auth()->user(), $contexto['planId'], (int) $this->periodo,
                $this->asignacionEditar, $this->docenteAnterior, (int) $this->docenteNuevo);
        } catch (QueryException $e) {
            report($e);
            $this->addError('docenteNuevo', 'No se pudo guardar la propuesta.');
            return;
        }
        $this->cancelarReasignacion();
        $this->mensaje = 'Reasignación de prueba guardada como propuesta, no confirmada.';
    }

    public function boot(): void
    {
        abort_unless(auth()->user() && (new RegistroPolicy)->viewAny(auth()->user()), 403);
        // El plan no acredita visibilidad de docentes compartidos entre escuelas.
        ConsultaLocalDemostracion::exigir(auth()->user());
    }

    public function mount(): void
    {
        $plan = request()->query('plan', session('gestion_horarios.workspace.plan', ''));
        if ((is_string($plan) || is_int($plan)) && ctype_digit((string) $plan) && strlen((string) $plan) < 19) {
            $this->plan = (string) $plan;
        }
        $periodo = (string) request()->query('periodo', session('gestion_horarios.periodo_seleccionado', ''));
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
            $datos['edicionDemo'] = $contexto['planValido'] && $datos['periodoValido'] && ConsultaLocalDemostracion::permitida(auth()->user());
            $datos['candidatosDemo'] = $datos['edicionDemo'] && $this->asignacionEditar !== null
                ? DB::table('core.docente as d')->join('core.persona as p', 'p.id_persona', '=', 'd.id_persona')
                    ->where('p.tipo_documento', 'QA')->where('d.codigo_docente', 'like', 'QA-%')
                    ->whereRaw('upper(d.estado) = ?', ['ACTIVO'])->where('d.id_docente', '<>', $this->docenteAnterior)
                    ->orderBy('p.nombres')->orderBy('p.apellido_paterno')->orderBy('d.codigo_docente')->get(['d.id_docente', 'd.codigo_docente', 'p.nombres', 'p.apellido_paterno'])
                : collect();
            $datos['errorConsulta'] = false;
        } catch (QueryException $e) {
            report($e);
            $datos = ['periodos' => collect(), 'docentes' => collect(), 'actividades' => collect(), 'periodoValido' => false, 'errorConsulta' => true];
        }

        return view('modules.gestion-horarios.asignacion-docente', $datos);
    }
}
