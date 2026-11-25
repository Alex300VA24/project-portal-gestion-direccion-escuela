<?php

namespace App\Modules\GestionHorarios\Services;

/** Presentación de registros publicados: nunca consulta borradores ni escribe horarios. */
final class ConsultaPublicadaPresentacion
{
    public static function normalizar(array $filas): array
    {
        $sesiones=[]; $docentes=[];
        foreach ($filas as $f) {
            // Una actividad puede tener varias filas por docente. No repetir su bloque.
            $id=json_encode([$f->id_actividad,$f->id_ambiente,$f->dia_semana,$f->hora_inicio,$f->hora_fin], JSON_THROW_ON_ERROR);
            if (!isset($sesiones[$id])) {
                $sesiones[$id]=clone $f;
                $sesiones[$id]->id_detalle=$id;
                $sesiones[$id]->id_actividad=$id;
            }
            $nombre=trim(implode(' ',array_filter([$f->nombres??'', $f->apellido_paterno??'', $f->apellido_materno??''])));
            if ($nombre!=='') $docentes[$id][$nombre]=$nombre;
        }
        return [array_values($sesiones), array_map('array_values',$docentes)];
    }

    public static function cuadriculas(array $filas,string $modalidad,string $descripcion): array
    {
        [$sesiones,$docentes]=self::normalizar($filas);
        // Jornada de referencia: ampliar si existen sesiones fuera de ella.
        return (new DistribucionHoraria)->preparar($sesiones,$docentes,self::bloquesVisuales(),$modalidad==='ciclo'?'':$descripcion,true);
    }

    public static function bloquesVisuales(): array
    {
        $bloques=[];
        for ($h=420;$h<1200;$h+=60) if ($h!==780) $bloques[]=(object)['hora_inicio'=>DistribucionHoraria::hora($h),'hora_fin'=>DistribucionHoraria::hora($h+60)];
        return $bloques;
    }

    public static function vigente(object $p,string $fecha): bool
    {
        $inicio=substr((string)($p->fecha_inicio??''),0,10);$fin=substr((string)($p->fecha_fin??''),0,10);
        return $inicio!=='' && $fin!=='' && $inicio<=$fecha && $fecha<=$fin;
    }

    public static function ocupacion(array $ambientes,array $filas,int $minutos,bool $vigente): array
    {
        [$sesiones,$docentes]=self::normalizar($filas);
        $porAmbiente=[];
        foreach ($sesiones as $s) {
            $s->docente=implode(', ',$docentes[$s->id_actividad]??[]) ?: 'Por confirmar';
            $s->inicio=DistribucionHoraria::minutos($s->hora_inicio);$s->fin=DistribucionHoraria::minutos($s->hora_fin);
            $porAmbiente[$s->id_ambiente][]=$s;
        }
        $salida=[];
        foreach ($ambientes as $a) {
            $eventos=$vigente?($porAmbiente[$a['id']]??[]):[];
            $actuales=array_values(array_filter($eventos,fn($s)=>$s->inicio<=$minutos && $minutos<$s->fin));
            $proximas=array_values(array_filter($eventos,fn($s)=>$s->inicio>$minutos));
            usort($proximas,fn($a,$b)=>$a->inicio<=>$b->inicio);
            $siguiente=$proximas[0]->inicio??null;
            $salida[]=$a+['estado'=>!$vigente?'Fuera de vigencia':($actuales?'Clase programada':'Sin clase programada'), 'actuales'=>$actuales, 'proximas'=>array_values(array_filter($proximas,fn($s)=>$s->inicio===$siguiente))];
        }
        return $salida;
    }
}
