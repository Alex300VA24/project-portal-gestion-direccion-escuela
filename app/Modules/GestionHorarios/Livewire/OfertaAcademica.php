<?php

namespace App\Modules\GestionHorarios\Livewire;

use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Modules\GestionHorarios\Livewire\Concerns\SeleccionPlan;
use App\Modules\GestionHorarios\Policies\RegistroPolicy;
use App\Modules\GestionHorarios\Services\AmbitoConsultaAdministrativa;
use App\Modules\GestionHorarios\Services\AmbitoEscrituraAcademica;
use App\Modules\GestionHorarios\Services\AmbitoPlanes;
use App\Modules\GestionHorarios\Services\AutorizacionGestionAcademica;
use App\Modules\GestionHorarios\Services\ConsultaOfertaAcademica;
use App\Modules\GestionHorarios\Services\FuentesAcademicasAutorizadas;
use App\Modules\GestionHorarios\Services\GestionOfertaAcademica;
use App\Modules\GestionHorarios\Services\ResumenPreparacionAcademica;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class OfertaAcademica extends Component
{
    use SeleccionPlan;

    public string $periodo = '';

    public string $buscar = '';

    public string $ciclo = '';

    public string $estado = '';

    public string $tipoFiltro = '';

    public string $cursoFormulario = '';

    public string $tipoFormulario = 'REGULAR';

    public string $docentesFormulario = '1';

    public string $estadoFormulario = 'PLANIFICADA';

    #[Locked]
    public ?int $ofertaEditar = null;

    #[Locked]
    public string $estadoOriginal = '';

    #[Locked]
    public int $docentesOriginales = 0;

    #[Locked]
    public string $detalleOferta = '';

    public bool $formularioAbierto = false;

    public string $mensaje = '';

    public bool $verPreparacion = false;

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
        $oferta = (string) request()->query('oferta', '');
        if ($this->periodo !== '' && $oferta !== '' && ctype_digit($oferta) && strlen($oferta) < 19) {
            $this->editar((int) $oferta);
        }
    }

    public function nueva(): void
    {
        (new AutorizacionGestionAcademica)->exigir(auth()->user());
        $this->resetValidation();
        $this->ofertaEditar = null;
        $this->reset('cursoFormulario', 'tipoFormulario', 'docentesFormulario', 'estadoFormulario', 'mensaje');
        $this->formularioAbierto = true;
    }

    public function updatedPeriodo(): void
    {
        $this->verPreparacion = false;
        $this->formularioAbierto = false;
        $this->ofertaEditar = null;
        $this->mensaje = '';
        $this->resetValidation();
    }

    public function comprobarPreparacion(): void
    {
        $this->verPreparacion = true;
    }

    public function editar(int $id): void
    {
        (new AutorizacionGestionAcademica)->exigir(auth()->user());
        $this->resetValidation();
        $planesAutorizados = app(AmbitoPlanes::class)->consultar(auth()->user())->select('id_plan');
        $oferta = DB::table('gestion_horarios.oferta_curso as o')->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 'o.id_curso_plan')->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')->whereIn('cp.id_plan', $planesAutorizados)->where('o.id_oferta', $id)->where('o.id_periodo', $this->periodo)->first(['o.*', 'c.codigo', 'c.nombre', 'cp.ciclo', 'cp.id_plan']);
        abort_unless($oferta, 404);
        $proveedor = app()->bound(ProveedorEscuelasAutorizadas::class) ? app(ProveedorEscuelasAutorizadas::class) : null;
        (new AmbitoEscrituraAcademica(DB::connection(), $proveedor))->autorizarCurso(auth()->user(), (int) $oferta->id_curso_plan);
        $this->plan = (string) $oferta->id_plan;
        $this->ofertaEditar = $id;
        $this->docentesFormulario = (string) $oferta->docentes_minimos;
        $this->estadoFormulario = $oferta->estado;
        $this->estadoOriginal = $oferta->estado;
        $this->docentesOriginales = (int) $oferta->docentes_minimos;
        $this->detalleOferta = $oferta->codigo.' · '.$oferta->nombre.' · Ciclo '.$oferta->ciclo.' · '.$oferta->tipo_oferta;
        $this->formularioAbierto = true;
        $this->mensaje = '';
    }

    public function guardar(): void
    {
        (new AutorizacionGestionAcademica)->exigir(auth()->user());
        $proveedor = app()->bound(ProveedorEscuelasAutorizadas::class) ? app(ProveedorEscuelasAutorizadas::class) : null;
        $servicio = new GestionOfertaAcademica(DB::connection(), $proveedor);
        try {
            if ($this->ofertaEditar !== null) {
                $servicio->actualizar(auth()->user(), $this->ofertaEditar, ['docentes' => $this->docentesFormulario, 'estado' => $this->estadoFormulario], $this->estadoOriginal, $this->docentesOriginales);
            } else {
                if (! ctype_digit($this->plan) || strlen($this->plan) >= 19 || ! app(AmbitoPlanes::class)->contiene(auth()->user(), (int) $this->plan) || ! DB::table('core.curso_plan')->where('id_plan', $this->plan)->where('id_curso_plan', $this->cursoFormulario)->exists()) {
                    $this->addError('curso', 'Seleccione un curso del plan elegido.');

                    return;
                }
                $servicio->crear(auth()->user(), ['periodo' => $this->periodo, 'curso' => $this->cursoFormulario, 'tipo' => $this->tipoFormulario, 'docentes' => $this->docentesFormulario]);
            }
        } catch (QueryException $e) {
            report($e);
            $this->addError('formulario', 'No se pudo guardar la oferta. Intente nuevamente o comuníquese con soporte.');

            return;
        }
        $this->formularioAbierto = false;
        $this->mensaje = 'La oferta se guardó correctamente.';
    }

    public function render(): View
    {
        try {
            $periodo = ctype_digit($this->periodo) && strlen($this->periodo) < 19 ? (int) $this->periodo : null;
            $contexto = $this->contextoPlan();
            $ciclo = $this->ciclo !== '' && ctype_digit($this->ciclo) && $contexto['ciclos']->contains((int) $this->ciclo) ? $this->ciclo : '';
            $datos = (new ConsultaOfertaAcademica(DB::connection()))->consultar($periodo, mb_substr($this->buscar, 0, 120), $ciclo, $this->estado, $this->tipoFiltro, $contexto['planId']) + $contexto;
            $datos['resumenPreparacion'] = null;
            if ($this->verPreparacion && $contexto['planValido'] && $periodo !== null && $datos['periodos']->contains('id_periodo', $periodo)) {
                $fuentes = (new FuentesAcademicasAutorizadas(DB::connection()))->consultar(auth()->user(), $periodo, $contexto['planId']);
                $datos['resumenPreparacion'] = (new ResumenPreparacionAcademica)->analizar($fuentes);
            } elseif ($this->verPreparacion && $contexto['planValido']) {
                $datos['resumenPreparacion'] = [['nivel' => 'ERROR', 'mensaje' => 'Seleccione un periodo vinculado al plan autorizado.']];
            }
            $datos['errorConsulta'] = false;
            $datos['escrituraDisponible'] = $contexto['planValido'] && (new AutorizacionGestionAcademica)->permite(auth()->user());
        } catch (QueryException $e) {
            report($e);
            $datos = ['periodos' => collect(), 'catalogo' => collect(), 'demanda' => null, 'cursos' => collect(), 'escrituraDisponible' => false, 'errorConsulta' => true];
        }

        return view('modules.gestion-horarios.oferta-academica', $datos);
    }
}
