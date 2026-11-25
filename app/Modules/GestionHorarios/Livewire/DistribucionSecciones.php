<?php

namespace App\Modules\GestionHorarios\Livewire;

use App\Modules\GestionHorarios\Livewire\Concerns\SeleccionPlan;
use App\Modules\GestionHorarios\Policies\RegistroPolicy;
use App\Modules\GestionHorarios\Repositories\ConsultaSeccionesRepository;
use App\Modules\GestionHorarios\Services\AmbitoConsultaAdministrativa;
use App\Modules\GestionHorarios\Services\AutorizacionGestionAcademica;
use App\Modules\GestionHorarios\Services\ConsultaDistribucionSecciones;
use App\Modules\GestionHorarios\Services\GestionOfertaSeccion;
use App\Modules\GestionHorarios\Services\PlanificarSeccion;
use App\Modules\GestionHorarios\Services\PlanificarSeccionesPorDemanda;
use App\Modules\GestionHorarios\Services\PropuestaSecciones;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class DistribucionSecciones extends Component
{
    use SeleccionPlan { updatedPlan as private cambiarPlan; }

    public string $periodo = '';

    public string $buscar = '';

    public string $ciclo = '';

    public string $seccionSemana = '';

    public bool $verEditorSemana = false;

    public function updatedCiclo(): void
    {
        $this->reset('seccionSemana', 'verEditorSemana');
    }

    public function updatedSeccionSemana(): void
    {
        $this->verEditorSemana = false;
    }

    public array $ofertasSeleccionadas = [];

    public string $mensajeAsociacion = '';

    #[Locked]
    public ?int $cursoEnEdicion = null;

    #[Locked]
    public ?int $seccionEnEdicion = null;

    #[Locked]
    public array $original = [];

    public string $codigo = '';

    public string $capacidad = '';

    public string $capacidadObjetivo = '';

    #[Locked]
    public ?array $propuestaPendiente = null;

    public function prepararPropuesta(int $curso): void
    {
        $this->cancelarPropuesta();
        $this->mensajeAsociacion = '';
        $this->validate(['capacidadObjetivo' => 'required|integer|min:1|max:2147483647']);
        $contexto = $this->contextoPlan();
        abort_unless($contexto['planValido'], 403);
        try {
            $this->propuestaPendiente = (new PlanificarSeccionesPorDemanda(DB::connection()))->previsualizar(
                auth()->user(), (int) $this->periodo, $contexto['planId'], $curso, (int) $this->capacidadObjetivo,
            );
        } catch (QueryException $e) {
            report($e);
            $this->addError('propuesta', 'No se pudo preparar la propuesta.');
        }
    }

    public function aplicarPropuesta(): void
    {
        $p = $this->propuestaPendiente;
        abort_unless($p && $p['periodo'] === (int) $this->periodo && $p['plan'] === (int) $this->plan && $p['capacidad'] === (int) $this->capacidadObjetivo, 422);
        try {
            $cantidad = (new PlanificarSeccionesPorDemanda(DB::connection()))->aplicar(
                auth()->user(), $p['periodo'], $p['plan'], $p['curso'], $p['capacidad'], $p['huella'],
            );
        } catch (QueryException $e) {
            report($e);
            $this->cancelarPropuesta();
            $this->addError('propuesta', 'No se pudo crear las secciones. No se aplicaron cambios parciales.');

            return;
        } catch (ValidationException $e) {
            $this->cancelarPropuesta();
            throw $e;
        }
        $this->cancelarPropuesta();
        $this->mensajeAsociacion = $cantidad.' secciones adicionales creadas. Complete sus actividades y asignaciones antes de generar.';
    }

    public function cancelarPropuesta(): void
    {
        $this->propuestaPendiente = null;
        $this->resetErrorBag('propuesta');
    }

    public function updatedCapacidadObjetivo(): void
    {
        $this->cancelarPropuesta();
        $this->resetErrorBag('capacidadObjetivo');
        if ($this->capacidadObjetivo !== '') {
            $this->validateOnly('capacidadObjetivo', ['capacidadObjetivo' => 'integer|min:1|max:2147483647']);
        }
    }

    public function abrirSeccion(int $curso, ?int $seccion = null): void
    {
        (new AutorizacionGestionAcademica)->exigir(auth()->user());
        $contexto = $this->contextoPlan();
        abort_unless($contexto['planValido'] && DB::table('core.curso_plan')->where('id_plan', $contexto['planId'])->where('id_curso_plan', $curso)->exists(), 403);
        $this->cancelarEdicion();
        $this->mensajeAsociacion = '';
        if ($seccion !== null) {
            $fila = DB::table('core.seccion')->where('id_periodo', (int) $this->periodo)->where('id_curso_plan', $curso)->where('id_seccion', $seccion)->first();
            abort_unless($fila, 404);
            $this->original = [$fila->codigo, $fila->capacidad, $fila->estado];
            $this->codigo = $fila->codigo;
            $this->capacidad = (string) $fila->capacidad;
        }
        $this->cursoEnEdicion = $curso;
        $this->seccionEnEdicion = $seccion;
    }

    public function guardarSeccion(): void
    {
        abort_unless($this->cursoEnEdicion !== null, 422);
        try {
            (new PlanificarSeccion(DB::connection()))->guardar(auth()->user(), (int) $this->periodo, (int) $this->plan,
                $this->cursoEnEdicion, $this->codigo, $this->capacidad, $this->seccionEnEdicion, $this->original);
        } catch (QueryException $e) {
            report($e);
            $this->addError('seccion', 'No se pudo guardar la sección. Actualice la consulta y vuelva a intentarlo.');

            return;
        }
        $this->mensajeAsociacion = 'Sección '.mb_strtoupper(trim($this->codigo)).' guardada.';
        $this->cancelarEdicion();
    }

    public function cancelarEdicion(): void
    {
        $this->reset('cursoEnEdicion', 'seccionEnEdicion', 'original', 'codigo', 'capacidad');
        $this->resetValidation();
    }

    public function updatedPlan(): void
    {
        $this->cambiarPlan();
        $this->updatedPeriodo();
    }

    public function updatedPeriodo(): void
    {
        $this->cancelarEdicion();
        $this->cancelarPropuesta();
        $this->reset('ofertasSeleccionadas', 'mensajeAsociacion', 'seccionSemana', 'capacidadObjetivo', 'verEditorSemana');
    }

    public function asociar(int $seccion, ?int $esperada): void
    {
        $this->mensajeAsociacion = '';
        $this->resetErrorBag('asociacion');
        try {
            $periodo = ctype_digit($this->periodo) && strlen($this->periodo) < 19 ? (int) $this->periodo : 0;
            $seleccion = $this->ofertasSeleccionadas[$seccion] ?? '';
            $oferta = is_string($seleccion) && ctype_digit($seleccion) && strlen($seleccion) < 19 ? (int) $seleccion : 0;
            (new GestionOfertaSeccion(DB::connection()))->asociar(auth()->user(), $periodo, $seccion, $oferta, $esperada);
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
            $datos['etiquetasSemana'] = $ciclo === '' ? collect() : $datos['cursos']->flatMap(fn ($c) => $c->secciones->pluck('codigo'))->unique()->sort()->values();
            $datos['semana'] = $contexto['planValido'] && $id && $datos['etiquetasSemana']->containsStrict($this->seccionSemana)
                ? (new ConsultaSeccionesRepository(DB::connection()))->semana($id, $contexto['planId'], (int) $ciclo, $this->seccionSemana)
                : null;
            $datos['propuestasSecciones'] = [];
            $objetivo = filter_var($this->capacidadObjetivo, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
            if ($contexto['planValido'] && $objetivo !== false) {
                foreach ($datos['cursos'] as $curso) {
                    $datos['propuestasSecciones'][(int) $curso->id_curso_plan] = PropuestaSecciones::calcular(
                        $curso->total === null ? null : (int) $curso->total,
                        $objetivo,
                        $curso->secciones,
                    );
                }
            }
            $datos['errorConsulta'] = false;
            $datos['escrituraDisponible'] = $contexto['planValido'] && (new AutorizacionGestionAcademica)->permite(auth()->user());
        } catch (QueryException $e) {
            report($e);
            $datos = ['periodos' => collect(), 'demanda' => null, 'cursos' => collect(), 'integridad' => null, 'asociacionDisponible' => false, 'escrituraDisponible' => false, 'errorConsulta' => true];
        }

        return view('modules.gestion-horarios.secciones', $datos);
    }
}
