<?php

namespace App\Modules\GestionHorarios\Controllers;

use App\Modules\GestionHorarios\Services\CicloVidaHorarioDemo as Ciclo;
use App\Modules\GestionHorarios\Services\ConsultaLocalDemostracion;
use App\Modules\GestionHorarios\Services\ConsultaPublicadaPresentacion;
use App\Modules\GestionHorarios\Services\HorarioUnicoDemo;
use App\Modules\GestionHorarios\Services\DescargaConsultaPublicada;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/** Vista previa de una publicación QA congelada. Lectura QA para cuentas expresamente autorizadas. */
final class ConsultaPublicacionDemoController
{
    public static function habilitada(Request $r): bool
    {
        return ConsultaLocalDemostracion::lecturaPublicada($r->user()) && Ciclo::disponible();
    }

    public function datos(Request $r): array
    {
        abort_unless(self::habilitada($r),403);
        $id=HorarioUnicoDemo::id(); $pub=$id?Ciclo::publicacion($id):null;
        $d=$pub?json_decode($pub->instantanea,true,512,JSON_THROW_ON_ERROR):[];
        $p=$pub?(object)$d['periodo']:null;
        $filas=collect(array_map(function($f) {
            if ((int)$f['ciclo']===10 && preg_match('/^G[1-9][0-9]*$/i',$f['seccion'])) {
                $f['grupo_actividad']=$f['seccion']; $f['seccion']='A';
            }
            return (object)$f;
        },$d['filas']??[]));
        $periodos=$p?collect([$p]):collect();
        $catalogos=[
            'ciclos'=>$filas->pluck('ciclo')->map(fn($c)=>(int)$c)->unique()->sort()->values(),
            'secciones'=>$filas->map(fn($f)=>['ciclo'=>(int)$f->ciclo,'codigo'=>$f->seccion])->unique(fn($s)=>$s['ciclo'].'|'.$s['codigo'])->sortBy('codigo')->values(),
            'docentes'=>$filas->filter(fn($f)=>isset($f->id_docente))->unique('id_docente')->map(fn($f)=>['id'=>(int)$f->id_docente,'nombre'=>$f->nombres])->sort(fn($a,$b)=>strnatcasecmp($a['nombre'],$b['nombre']))->values(),
            'ambientes'=>collect($d['ambientes']??[])->sort(fn($a,$b)=>strnatcasecmp($a['nombre'],$b['nombre']))->values(),
        ];
        return compact('pub','p','periodos','filas','catalogos');
    }

    public function consulta(Request $r): Response
    {
        $f=$r->validate(['periodo'=>'nullable|integer|min:1','modalidad'=>'nullable|in:ciclo,docente,ambiente','valor'=>'nullable|integer|min:1','seccion'=>'nullable|string|max:50','descargar'=>'nullable|in:pdf']);
        ['pub'=>$pub,'p'=>$p,'periodos'=>$periodos,'filas'=>$filas,'catalogos'=>$catalogos]=$this->datos($r);
        $periodo=$p && (!isset($f['periodo']) || (int)$f['periodo']===(int)$p->id_periodo) ? $p->id_periodo : '';
        $items=$pub?[(object)['id_periodo'=>$p->id_periodo,'ambito'=>'Demostración local','version'=>$pub->version,'publicado_en'=>$pub->creado_en]]:[];
        $horarios=new LengthAwarePaginator($items,count($items),20,1,['path'=>$r->url()]);
        $datos=compact('periodos','periodo','horarios','catalogos');
        $datos['publicacionQa']=true;
        // Abrir la consulta publicada con un ciclo real, sin depender de un segundo clic.
        if ($periodo!=='' && !isset($f['valor']) && ($f['modalidad']??'ciclo')==='ciclo' && $catalogos['ciclos']->isNotEmpty()) {
            $f['modalidad']='ciclo';
            $f['valor']=$catalogos['ciclos']->first();
            $f['seccion']=$catalogos['secciones']->firstWhere('ciclo',(int)$f['valor'])['codigo']??null;
        }
        $datos['filtrosConsulta']=$f;
        if ($periodo!=='' && isset($f['valor'],$f['modalidad'])) {
            $valor=(int)$f['valor']; $modalidad=$f['modalidad'];
            $seleccion=match($modalidad) {
                'ciclo'=>$filas->filter(fn($s)=>(int)$s->ciclo===$valor && (empty($f['seccion']) || $s->seccion===$f['seccion'])),
                'docente'=>$filas->filter(fn($s)=>(int)$s->id_docente===$valor),
                'ambiente'=>$filas->filter(fn($s)=>(int)$s->id_ambiente===$valor),
            };
            $descripcion=match($modalidad) {'ciclo'=>'Ciclo '.$valor,'docente'=>$catalogos['docentes']->firstWhere('id',$valor)['nombre']??'Docente','ambiente'=>$catalogos['ambientes']->firstWhere('id',$valor)['nombre']??'Ambiente'};
            $datos['planos']=ConsultaPublicadaPresentacion::cuadriculas($seleccion->all(),$modalidad,$descripcion);
            $datos['descripcion']=$descripcion;
            if (($f['descargar']??null)==='pdf') return DescargaConsultaPublicada::pdf($datos['planos'], $p, 'PUBLICADO · V'.$pub->version);
        }
        abort_if(($f['descargar']??null)==='pdf',422,'Seleccione un horario publicado.');
        return response()->view('modules.gestion-horarios.publicados-pagina',$datos)->header('Cache-Control','private, no-store');
    }

    public function panel(Request $r): Response
    {
        $f=$r->validate(['periodo'=>'nullable|integer|min:1','fragmento'=>'nullable|in:1']);
        ['p'=>$p,'periodos'=>$periodos,'filas'=>$filas,'catalogos'=>$catalogos]=$this->datos($r);
        $ahora=Carbon::now('America/Lima');
        $periodo=$p && (!isset($f['periodo']) || (int)$f['periodo']===(int)$p->id_periodo)?$p:null;
        $enVigencia=$periodo && ConsultaPublicadaPresentacion::vigente($periodo,$ahora->format('Y-m-d'));
        $dia=['DOMINGO','LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO'][$ahora->dayOfWeek];
        $sesiones=$enVigencia?$filas->where('dia_semana',$dia)->filter(fn($s)=>$s->id_ambiente!==null)->all():[];
        $tarjetas=ConsultaPublicadaPresentacion::ocupacion($periodo?$catalogos['ambientes']->all():[],$sesiones,$ahora->hour*60+$ahora->minute,(bool)$enVigencia);
        $publicacionQa=true;
        return response()->view('modules.gestion-horarios.'.(isset($f['fragmento'])?'panel-ocupacion-contenido':'panel-ocupacion'),compact('periodos','periodo','ahora','enVigencia','tarjetas','publicacionQa'))->header('Cache-Control','private, no-store');
    }
}
