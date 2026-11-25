<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Bloqueos horarios explícitos del periodo local. No autoriza escrituras institucionales. */
final class BloqueosAcademicosDemo
{
    public const DIAS = ['LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO'];
    public const ALCANCES = ['GLOBAL','CICLO','DOCENTE','AMBIENTE'];

    public static function parametros(string $alcance, string $objetivo, string $dia, int $inicio, int $fin): array
    {
        if (!in_array($alcance,self::ALCANCES,true) || !in_array($dia,self::DIAS,true) || $inicio < 7 || $fin > 20 || $inicio >= $fin
            || ($alcance === 'GLOBAL' ? $objetivo !== '' : (!ctype_digit($objetivo) || strlen($objetivo)>18 || (int)$objetivo<1))
            || ($alcance === 'CICLO' && (int)$objetivo>10)) {
            throw ValidationException::withMessages(['bloqueo'=>'Seleccione un alcance y una franja válidos (07:00–20:00).']);
        }
        return ['contrato'=>'QA_BLOQUEO_V1','alcance'=>$alcance,'objetivo'=>$alcance==='GLOBAL'?null:(int)$objetivo,'dia'=>$dia,'inicio'=>$inicio,'fin'=>$fin];
    }

    private function contexto(User $usuario,int $periodo): void
    {
        abort_unless(ConsultaLocalDemostracion::permitida($usuario),403);
        abort_unless(DB::table('core.periodo_academico')->where('id_periodo',$periodo)->where('codigo','2026-II')->exists(),404);
    }

    private function objetivo(array $p): void
    {
        if ($p['alcance']==='DOCENTE') {
            abort_unless(DB::table('core.docente')->where('id_docente',$p['objetivo'])->where('codigo_docente','like','QA-%')->where('estado','ACTIVO')->exists(),422);
        } elseif ($p['alcance']==='AMBIENTE') {
            abort_unless(DB::table('core.ambiente')->where('id_ambiente',$p['objetivo'])->where('estado','ACTIVO')->exists(),422);
        }
    }

    public function guardar(User $usuario,int $periodo,array $p,string $motivo,?int $id=null,?string $huella=null): void
    {
        $this->contexto($usuario,$periodo);
        $p=self::parametros($p['alcance'],(string)($p['objetivo']??''),$p['dia'],$p['inicio'],$p['fin']);
        $motivo=trim($motivo);
        if ($motivo==='' || mb_strlen($motivo)>120) throw ValidationException::withMessages(['motivo'=>'Escriba un motivo de hasta 120 caracteres.']);
        DB::transaction(function() use ($usuario,$periodo,$p,$motivo,$id,$huella) {
            DB::statement('LOCK TABLE gestion_horarios.restriccion, gestion_horarios.detalle_restriccion IN SHARE ROW EXCLUSIVE MODE');
            $this->contexto($usuario,$periodo); $this->objetivo($p);
            $json=json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
            $duplicado=$this->consulta($periodo)->whereRaw('d.parametros = ?::jsonb',[$json])->where('r.activa',true)->when($id!==null,fn($q)=>$q->where('r.id_restriccion','!=',$id))->exists();
            if ($duplicado) throw ValidationException::withMessages(['bloqueo'=>'Ya existe un bloqueo activo con este alcance y franja.']);
            if ($id===null) {
                $id=DB::table('gestion_horarios.restriccion')->insertGetId(['codigo'=>'QA-BLOQ-'.Str::random(24),'nombre'=>$motivo,'descripcion'=>$motivo,'tipo'=>'OTRO','severidad'=>'DURA','activa'=>true],'id_restriccion');
                DB::table('gestion_horarios.detalle_restriccion')->insert(['id_restriccion'=>$id,'ambito'=>'PERIODO','id_periodo'=>$periodo,'parametros'=>$json]);
            } else {
                $fila=$this->fila($periodo,$id,$huella);
                DB::table('gestion_horarios.restriccion')->where('id_restriccion',$id)->update(['nombre'=>$motivo,'descripcion'=>$motivo]);
                DB::table('gestion_horarios.detalle_restriccion')->where('id_detalle_restriccion',$fila->id_detalle_restriccion)->update(['parametros'=>$json]);
            }
        });
    }

    private function consulta(int $periodo)
    {
        return DB::table('gestion_horarios.restriccion as r')->join('gestion_horarios.detalle_restriccion as d','d.id_restriccion','=','r.id_restriccion')
            ->where('d.ambito','PERIODO')->where('d.id_periodo',$periodo)->where('r.codigo','like','QA-BLOQ-%')->whereRaw("d.parametros->>'contrato' = ?",['QA_BLOQUEO_V1']);
    }

    public function listar(User $usuario,int $periodo)
    {
        $this->contexto($usuario,$periodo);
        return $this->consulta($periodo)->orderBy('r.id_restriccion')->get(['r.id_restriccion','r.nombre','r.activa','d.id_detalle_restriccion','d.parametros']);
    }

    public static function huella(object $fila): string { return hash('sha256',json_encode($fila,JSON_THROW_ON_ERROR)); }

    private function fila(int $periodo,int $id,?string $huella): object
    {
        $filas=$this->consulta($periodo)->where('r.id_restriccion',$id)->get(['r.id_restriccion','r.nombre','r.activa','d.id_detalle_restriccion','d.parametros']);
        $fila=$filas->first();
        if ($filas->count()!==1 || !$huella || !hash_equals(self::huella($fila),$huella)) throw ValidationException::withMessages(['bloqueo'=>'La restricción cambió. Actualice e inténtelo nuevamente.']);
        return $fila;
    }

    public function alternar(User $usuario,int $periodo,int $id,string $huella): void
    {
        $this->contexto($usuario,$periodo);
        DB::transaction(function() use ($usuario,$periodo,$id,$huella) {
            DB::statement('LOCK TABLE gestion_horarios.restriccion, gestion_horarios.detalle_restriccion IN SHARE ROW EXCLUSIVE MODE');
            $this->contexto($usuario,$periodo); $fila=$this->fila($periodo,$id,$huella);
            if (!$fila->activa) {
                $p=json_decode($fila->parametros,true,512,JSON_THROW_ON_ERROR); $this->objetivo($p);
                if ($this->consulta($periodo)->where('r.id_restriccion','!=',$id)->where('r.activa',true)->whereRaw('d.parametros = ?::jsonb',[$fila->parametros])->exists()) throw ValidationException::withMessages(['bloqueo'=>'Ya existe un bloqueo activo equivalente.']);
            }
            DB::table('gestion_horarios.restriccion')->where('id_restriccion',$id)->update(['activa'=>!$fila->activa]);
        });
    }
}
