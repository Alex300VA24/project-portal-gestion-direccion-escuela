<?php
namespace App\Modules\GestionHorarios\Livewire;

use App\Modules\GestionHorarios\Services\ConsultaLocalDemostracion;
use App\Modules\GestionHorarios\Services\ConsultaSemanalDemo;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Catálogos independientes del periodo; exclusivamente demostración autorizada. */
final class CatalogoRecursosQa extends Component
{
    #[Locked] public string $recurso = 'cursos';
    public string $buscar = '';
    public function boot(): void { abort_unless(ConsultaLocalDemostracion::permitida(auth()->user()), 403); }
    public function mount(): void
    {
        $recurso = request()->query('recurso', 'cursos');
        abort_unless(in_array($recurso, ['cursos','docentes','aulas','laboratorios'], true), 404);
        $this->recurso = $recurso;
    }
    public function render(): View
    {
        $b = '%'.mb_substr(trim($this->buscar), 0, 120).'%';
        $plan = DB::table('core.plan_estudios')->where('codigo','QA-HORARIOS')->value('id_plan');
        $filas = match ($this->recurso) {
            'cursos' => DB::table('core.curso_plan as cp')->join('core.curso as c','c.id_curso','=','cp.id_curso')->where('cp.id_plan',$plan)
                ->where(fn($q)=>$q->where('c.nombre','ilike',$b)->orWhere('c.codigo','ilike',$b))->orderBy('cp.ciclo')->orderBy('c.nombre')
                ->get(['c.codigo','c.nombre','cp.ciclo','c.horas_teoria','c.horas_practica','c.horas_laboratorio']),
            'docentes' => DB::table('core.docente as d')->join('core.persona as p','p.id_persona','=','d.id_persona')->where('p.tipo_documento','QA')->where('d.codigo_docente','like','QA-%')
                ->selectRaw("d.id_docente, d.estado, trim(concat_ws(' ',p.nombres,p.apellido_paterno,p.apellido_materno)) as nombre")
                ->whereRaw("concat_ws(' ',p.nombres,p.apellido_paterno,p.apellido_materno) ilike ?",[$b])->orderBy('nombre')->get(),
            default => DB::table('core.ambiente')->where('tipo',$this->recurso==='aulas'?'AULA':'LABORATORIO')
                ->where(fn($q)=>$q->where('nombre','ilike',$b)->orWhere('codigo','ilike',$b))->orderBy('nombre')->get(['id_ambiente','codigo','nombre','tipo','capacidad','estado']),
        };
        if ($this->recurso !== 'cursos') {
            $filas = $filas->sort(fn ($a, $b) => ConsultaSemanalDemo::comparar($a->nombre, $b->nombre) ?: (($a->id_ambiente ?? $a->id_docente) <=> ($b->id_ambiente ?? $b->id_docente)))->values();
        }
        return view('modules.gestion-horarios.catalogo-recursos-qa',compact('filas','plan'));
    }
}
