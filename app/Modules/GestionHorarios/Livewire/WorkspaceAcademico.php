<?php

namespace App\Modules\GestionHorarios\Livewire;

use App\Modules\GestionHorarios\Policies\RegistroPolicy;
use App\Modules\GestionHorarios\Repositories\OfertaAcademicaRepository;
use App\Modules\GestionHorarios\Services\AmbitoConsultaAdministrativa;
use App\Modules\GestionHorarios\Services\AmbitoPlanes;
use App\Modules\GestionHorarios\Services\ConsultaLocalDemostracion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

final class WorkspaceAcademico extends Component
{
    public string $plan = '';

    public string $periodo = '';

    public string $etapa = 'inicio';

    public bool $errorContexto = false;

    public function boot(): void
    {
        abort_unless(auth()->user() && (new RegistroPolicy)->viewAny(auth()->user()), 403);
        (new AmbitoConsultaAdministrativa)->exigirEscuela(auth()->user());
    }

    public function mount(): void
    {
        $this->plan = $this->texto(request()->query('plan', session('gestion_horarios.workspace.plan', '')));
        $this->periodo = $this->texto(request()->query('periodo', session('gestion_horarios.periodo_seleccionado', '')));
        $etapa = request()->query('etapa', 'inicio');
        $this->etapa = is_string($etapa) && in_array($etapa, ['inicio', 'preparacion', 'recursos', 'elaboracion', 'revision'], true) ? $etapa : 'inicio';
        $this->guardarContexto();
        if ($this->etapa !== 'inicio' && ConsultaLocalDemostracion::permitida(auth()->user())) {
            $plan = DB::table('core.plan_estudios')->where('codigo','QA-HORARIOS')->value('id_plan');
            $periodo = DB::table('core.periodo_academico')->where('codigo','2026-II')->value('id_periodo');
            $parametros = ['plan'=>$plan, 'periodo'=>$periodo];
            $ruta = match ($this->etapa) {
                'preparacion'=>'gestion-horarios.oferta', 'recursos'=>'gestion-horarios.catalogo-recursos-qa',
                'elaboracion'=>'gestion-horarios.borradores-qa', 'revision'=>'gestion-horarios.revision-trabajo-qa',
            };
            if ($this->etapa === 'recursos') { $parametros['recurso']='cursos'; }
            $this->redirectRoute($ruta, $parametros, navigate: false);
        }
    }

    private function texto(mixed $valor): string
    {
        return (is_string($valor) || is_int($valor)) && ctype_digit((string) $valor) && strlen((string) $valor) < 19
            ? (string) $valor : '';
    }

    private function contexto(): array
    {
        $planes = app(AmbitoPlanes::class)->consultar(auth()->user())->orderBy('codigo')->get(['id_plan', 'codigo', 'nombre']);
        $planValido = $this->texto($this->plan) !== '' && $planes->contains('id_plan', (int) $this->plan);
        $periodos = $planValido ? (new OfertaAcademicaRepository(DB::connection()))->periodosParaPlan((int) $this->plan) : collect();
        $periodoValido = $this->texto($this->periodo) !== '' && $periodos->contains('id_periodo', (int) $this->periodo);

        return compact('planes', 'periodos', 'planValido', 'periodoValido');
    }

    public function updatedPlan(): void
    {
        $this->guardarContexto();
    }

    public function updatedPeriodo(): void
    {
        $this->guardarContexto();
    }

    private function guardarContexto(): void
    {
        $this->errorContexto = false;
        try {
            $contexto = $this->contexto();
            if (! $contexto['planValido']) {
                $this->plan = '';
            }
            if (! $contexto['periodoValido']) {
                $this->periodo = '';
            }
            session()->put('gestion_horarios.workspace.plan', $this->plan);
            session()->put('gestion_horarios.periodo_seleccionado', $this->periodo);
        } catch (QueryException $e) {
            report($e);
            $this->errorContexto = true;
        }
    }

    public function render(): View
    {
        try {
            $datos = $this->contexto();
        } catch (QueryException $e) {
            report($e);
            $datos = ['planes' => collect(), 'periodos' => collect(), 'planValido' => false, 'periodoValido' => false];
            $datos['falloConsulta'] = true;
        }

        return view('modules.gestion-horarios.workspace-academico', $datos);
    }
}
