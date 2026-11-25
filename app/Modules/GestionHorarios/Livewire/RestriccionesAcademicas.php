<?php

namespace App\Modules\GestionHorarios\Livewire;

use App\Modules\GestionHorarios\Policies\RegistroPolicy;
use App\Modules\GestionHorarios\Repositories\ConsultaRestriccionesRepository;
use App\Modules\GestionHorarios\Repositories\OfertaAcademicaRepository;
use App\Modules\GestionHorarios\Services\ConsultaLocalDemostracion;
use App\Modules\GestionHorarios\Services\PrevalidacionGeneracion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Attributes\Locked;
use App\Modules\GestionHorarios\Services\BloqueosAcademicosDemo;
use App\Modules\GestionHorarios\Services\ConsultaSemanalDemo;

final class RestriccionesAcademicas extends Component
{
    public string $periodo = '';

    public string $estado = '';

    public array $resultados = [];
    public bool $formulario = false;
    #[Locked] public ?int $idEdicion = null;
    #[Locked] public ?string $huellaEdicion = null;
    public string $alcance = 'GLOBAL';
    public string $objetivo = '';
    public string $dia = 'LUNES';
    public string $inicioBloqueo = '7';
    public string $finBloqueo = '8';
    public string $motivo = '';
    public string $mensaje = '';

    public function nuevo(): void { $this->cancelar(); $this->formulario = true; }
    public function cancelar(): void { $this->reset('formulario','idEdicion','huellaEdicion','alcance','objetivo','dia','inicioBloqueo','finBloqueo','motivo'); $this->resetValidation(); }
    public function updatedAlcance(): void { $this->objetivo = ''; }
    private function periodoId(): int { abort_unless(ctype_digit($this->periodo) && strlen($this->periodo)<19,422); return (int)$this->periodo; }
    public function editar(int $id): void
    {
        $this->cancelar();
        $fila=(new BloqueosAcademicosDemo)->listar(auth()->user(),$this->periodoId())->firstWhere('id_restriccion',$id);
        abort_unless($fila,404);
        $p=json_decode($fila->parametros,true,512,JSON_THROW_ON_ERROR);
        $this->idEdicion=$id; $this->huellaEdicion=BloqueosAcademicosDemo::huella($fila);
        $this->alcance=$p['alcance']; $this->objetivo=(string)($p['objetivo']??''); $this->dia=$p['dia'];
        $this->inicioBloqueo=(string)$p['inicio']; $this->finBloqueo=(string)$p['fin']; $this->motivo=$fila->nombre; $this->formulario=true;
    }
    public function guardarBloqueo(): void
    {
        abort_unless($this->formulario,422);
        $this->validate(['motivo'=>'required|string|max:120','alcance'=>'required|in:GLOBAL,CICLO,DOCENTE,AMBIENTE','objetivo'=>'nullable|string|max:18','dia'=>'required|in:LUNES,MARTES,MIERCOLES,JUEVES,VIERNES,SABADO','inicioBloqueo'=>'required|integer|min:7|max:19','finBloqueo'=>'required|integer|min:8|max:20']);
        $p=BloqueosAcademicosDemo::parametros($this->alcance,$this->objetivo,$this->dia,(int)$this->inicioBloqueo,(int)$this->finBloqueo);
        (new BloqueosAcademicosDemo)->guardar(auth()->user(),$this->periodoId(),$p,$this->motivo,$this->idEdicion,$this->huellaEdicion);
        $this->cancelar(); $this->mensaje='Restricción guardada. Revise y vuelva a generar el horario para aplicar los cambios.';
    }
    public function alternarBloqueo(int $id,string $huella): void
    {
        (new BloqueosAcademicosDemo)->alternar(auth()->user(),$this->periodoId(),$id,$huella);
        $this->cancelar(); $this->mensaje='Estado actualizado. Revise y vuelva a generar el horario.';
    }

    public bool $consultaFallida = false;

    public function boot(): void
    {
        abort_unless(auth()->user() && (new RegistroPolicy)->viewAny(auth()->user()), 403);
        ConsultaLocalDemostracion::exigir(auth()->user());
    }

    public function mount(): void
    {
        $valor = request()->query('periodo', session('gestion_horarios.periodo_seleccionado', ''));
        $periodo = is_scalar($valor) ? (string)$valor : '';
        if ($periodo !== '' && ctype_digit($periodo) && strlen($periodo) < 19) {
            $this->periodo = $periodo;
        }
    }

    public function updatedPeriodo(): void
    {
        $this->resultados = [];
        $this->consultaFallida = false;
        $this->cancelar(); $this->mensaje = '';
    }

    public function prevalidar(): void
    {
        try {
            $periodo = ctype_digit($this->periodo) && strlen($this->periodo) < 19 ? (int) $this->periodo : 0;
            $this->resultados = (new PrevalidacionGeneracion(DB::connection()))->evaluar($periodo);
            $this->consultaFallida = false;
        } catch (QueryException $e) {
            report($e);
            $this->resultados = [];
            $this->consultaFallida = true;
        }
    }

    public function render(): View
    {
        try {
            $db = DB::connection();
            $periodos = (new OfertaAcademicaRepository($db))->periodos();
            $periodo = ctype_digit($this->periodo) && strlen($this->periodo) < 19 ? (int) $this->periodo : null;
            $restricciones = (new ConsultaRestriccionesRepository($db))->consultar($periodo);
            if ($this->estado === 'activa') {
                $restricciones = $restricciones->filter(fn ($r) => (bool) $r->activa);
            }
            if ($this->estado === 'inactiva') {
                $restricciones = $restricciones->reject(fn ($r) => (bool) $r->activa);
            }
            $periodoValido=$periodo!==null && $periodos->contains('id_periodo',$periodo);
            $bloqueos=$periodoValido ? (new BloqueosAcademicosDemo)->listar(auth()->user(),$periodo) : collect();
            if ($this->estado==='activa') $bloqueos=$bloqueos->filter(fn($r)=>(bool)$r->activa);
            if ($this->estado==='inactiva') $bloqueos=$bloqueos->reject(fn($r)=>(bool)$r->activa);
            $restricciones=$restricciones->reject(fn($r)=>str_starts_with($r->codigo,'QA-BLOQ-'));
            $docentes=ConsultaSemanalDemo::opciones($db->table('core.docente as d')->join('core.persona as p','p.id_persona','=','d.id_persona')->where('d.estado','ACTIVO')->get(['d.id_docente','p.nombres','p.apellido_paterno','p.apellido_materno'])->mapWithKeys(fn($d)=>[$d->id_docente=>trim($d->nombres.' '.$d->apellido_paterno.' '.$d->apellido_materno)])->all());
            $ambientes=ConsultaSemanalDemo::opciones($db->table('core.ambiente')->where('estado','ACTIVO')->pluck('nombre','id_ambiente')->all());
            $errorConsulta = false;
        } catch (QueryException $e) {
            report($e);
            $periodos = $restricciones = $bloqueos = collect();
            $docentes=$ambientes=[]; $periodoValido=false;
            $errorConsulta = true;
        }

        return view('modules.gestion-horarios.restricciones', compact('periodos', 'restricciones', 'errorConsulta','bloqueos','docentes','ambientes','periodoValido'));
    }
}
