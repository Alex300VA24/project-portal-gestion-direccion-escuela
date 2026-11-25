<?php

namespace App\Modules\GestionHorarios\Services;

use RuntimeException;

/** Plano en puntos tipográficos. Cada grupo se dibuja en una sola página. */
final class PlanoAcademicoA4
{
    public const ANCHO = 841.89;
    public const ALTO = 595.28;
    public const MARGEN = 20.0;
    public const HORA = 62.0;
    public const ARRIBA = 80.0;
    public const ABAJO = 574.0;

    /** @param callable(string,float,bool):float $medir */
    public static function preparar(array $grupo, callable $medir): array
    {
        $dias = $grupo['dias'];
        $desde = $grupo['desde']; $hasta = $grupo['hasta'];
        if (!$dias || $desde >= $hasta) throw new RuntimeException('Calendario sin franjas válidas.');
        $anchoDia = (self::ANCHO-2*self::MARGEN-self::HORA)/count($dias);
        $limites = [$desde,$hasta];
        for ($h=$desde; $h<$hasta; $h+=60) $limites[]=$h;
        $catalogo=[]; $tarjetas=[];
        foreach ($dias as $j=>$dia) {
            foreach ($grupo['tarjetas'][$dia] as $t) {
                $limites[]=$t['inicio']; $limites[]=$t['fin'];
                if ($t['inicio']<$desde || $t['fin']>$hasta || $t['inicio'] >= $t['fin']) throw new RuntimeException('Sesión fuera de la jornada.');
                $t['x']=self::MARGEN+self::HORA+$j*$anchoDia+$t['lane']*$anchoDia/$t['slots']+1;
                $t['w']=$anchoDia/$t['slots']-2;
                foreach ($t['items'] as &$s) {
                    $k=json_encode([$s['codigo'],$s['curso'],$s['docente']],JSON_THROW_ON_ERROR);
                    if (!isset($catalogo[$k])) $catalogo[$k]=['alias'=>sprintf('%02d',count($catalogo)+1),'codigo'=>$s['codigo'],'curso'=>$s['curso'],'docente'=>$s['docente'],'color'=>self::colorActividad($s['tipo'])[0]];
                    $s['alias']=$catalogo[$k]['alias'];
                    $s['color']=self::colorActividad($s['tipo'])[0];
                } unset($s);
                $tarjetas[]=$t;
            }
        }
        $limites=array_values(array_unique($limites)); sort($limites,SORT_NUMERIC);
        $catalogo=array_values($catalogo);
        // Primero se intenta imprimir todos los datos dentro de cada tarjeta.
        // Solo para secciones densas, se añade una relación de nombres completos
        // en la MISMA página, enlazada por una referencia inequívoca.
        foreach ([false,true] as $compacto) {
            foreach ([8.0,7.5,7.0] as $fs) {
                $cat=$compacto?self::catalogo($catalogo,$fs,$medir):['alto'=>0,'items'=>[]];
                $top=self::ARRIBA+$cat['alto'];
                $altura=self::ABAJO-$top;
                $altos=[];
                for ($i=0;$i<count($limites)-1;$i++) $altos[$i]=max(11.0,13*($limites[$i+1]-$limites[$i])/60);
                $indice=array_flip($limites); $plan=[];
                foreach ($tarjetas as $t) {
                    $items=[]; $necesario=2.0;
                    foreach ($t['items'] as $s) {
                        $tipo=$s['tipo'].($s['grupo']!==''?' · '.$s['grupo']:'');
                        if (count($t['items'])>1) $tipo.=' · '.DistribucionHoraria::hora($t['inicio']).'–'.DistribucionHoraria::hora($t['fin']);
                        $campos=$compacto?[[$s['alias'].' · '.$s['codigo'],true],[$tipo,false],[$s['ambiente'],false]]:[[$s['codigo'].' · '.$s['curso'],true],[$tipo,false],[$s['ambiente'],false],[$s['docente'],false]];
                        if (($grupo['alcance'] ?? '')!=='') $campos[]=[$s['alcance_sesion'],false];
                        // Los límites fraccionarios se rotulan en el eje; cada tarjeta conserva su intervalo.
                        $lineas=[];
                        foreach ($campos as [$texto,$bold]) foreach (self::envolver($texto,$t['w']-6,$fs,$bold,$medir) as $linea) $lineas[]=[$linea,$bold];
                        $min=5+count($lineas)*($fs*1.18);
                        $items[]=['sesion'=>$s,'lineas'=>$lineas,'min'=>$min]; $necesario+=$min;
                    }
                    $a=$indice[$t['inicio']];$b=$indice[$t['fin']];
                    $faltante=$necesario-array_sum(array_slice($altos,$a,$b-$a));
                    if ($faltante>0) for ($i=$a;$i<$b;$i++) $altos[$i]+=$faltante*($limites[$i+1]-$limites[$i])/($t['fin']-$t['inicio']);
                    $plan[]=$t+['itemsPlano'=>$items];
                }
                if (array_sum($altos)>$altura+0.001) continue;
                $sobrante=$altura-array_sum($altos);$y=[$desde=>$top];
                foreach ($altos as $i=>$h) $y[$limites[$i+1]]=$y[$limites[$i]]+$h+$sobrante*($limites[$i+1]-$limites[$i])/($hasta-$desde);
                foreach ($plan as &$t) {
                    $t['y']=$y[$t['inicio']]+1; $t['h']=$y[$t['fin']]-$y[$t['inicio']]-2;
                    $min=array_sum(array_column($t['itemsPlano'],'min'));$cursor=$t['y'];
                    foreach ($t['itemsPlano'] as &$item) {
                        $item['y']=$cursor; $item['h']=$item['min']+max(0,$t['h']-$min)*$item['min']/$min; $cursor+=$item['h'];
                    } unset($item);
                } unset($t);
                return ['dias'=>$dias,'anchoDia'=>$anchoDia,'top'=>$top,'y'=>$y,'limites'=>$limites,'tarjetas'=>$plan,'fuente'=>$fs,'compacto'=>$compacto,'catalogo'=>$cat];
            }
        }
        throw new RuntimeException('El horario no cabe de forma legible en una hoja A4. No se exportaron páginas incompletas.');
    }

    private static function catalogo(array $items,float $fs,callable $medir): array
    {
        $mejor=null; $w=self::ANCHO-2*self::MARGEN;
        foreach ([2,3,4] as $cols) {
            $cw=$w/$cols; $y=0;$filas=array_chunk($items,$cols);$salida=[];
            foreach ($filas as $fila) {
                $alto=0;$registros=[];
                foreach ($fila as $j=>$s) {
                    $lineas=[];
                    foreach ([[$s['alias'].' · '.$s['codigo'].' · '.$s['curso'],true],[$s['docente'],false]] as [$txt,$bold]) foreach (self::envolver($txt,$cw-8,$fs,$bold,$medir) as $l) $lineas[]=[$l,$bold];
                    $alto=max($alto,count($lineas)*$fs*1.18+4);
                    $registros[]=['x'=>self::MARGEN+$j*$cw,'y'=>$y,'w'=>$cw,'lineas'=>$lineas,'color'=>$s['color']];
                }
                foreach ($registros as $r) $salida[]=$r+['h'=>$alto];
                $y+=$alto;
            }
            $c=['alto'=>$y+24,'items'=>$salida];
            if ($mejor===null || $c['alto']<$mejor['alto']) $mejor=$c;
        }
        return $mejor;
    }

    /** Paleta del editor semanal: fondo y borde de cada tipo de actividad. */
    public static function colorActividad(string $tipo): array
    {
        return match ($tipo) {
            'Teoría' => ['EAF4ED','267357'],
            'Práctica' => ['F9EFDF','9C721E'],
            'Laboratorio' => ['E8EFF9','416C9D'],
            default => ['F3F6F3','8396A6'],
        };
    }

    public static function titulo(array $grupo): string
    {
        $ciclo=(string)$grupo['ciclo'];
        $romanos=['I','II','III','IV','V','VI','VII','VIII','IX','X'];
        if (ctype_digit($ciclo) && (int)$ciclo>=1 && (int)$ciclo<=10) $ciclo=$romanos[(int)$ciclo-1];
        return ($grupo['alcance']??'') ?: 'CICLO '.$ciclo.' · SECCIÓN '.mb_strtoupper($grupo['seccion']);
    }

    /** Geometría de Excel sin duplicar bloques. Coordenadas de celdas inclusivas. */
    public static function rejilla(array $p): array
    {
        $xs=[self::MARGEN,self::MARGEN+self::HORA];
        $ys=array_values($p['y']);
        foreach ($p['dias'] as $i=>$dia) $xs[]=self::MARGEN+self::HORA+($i+1)*$p['anchoDia'];
        $rects=[];
        foreach ($p['tarjetas'] as $t) {
            $x1=$t['x']-1; $x2=$x1+$t['w']+2;
            $xs[]=$x1;$xs[]=$x2;
            $a=$p['y'][$t['inicio']];$b=$p['y'][$t['fin']];
            $cursor=$a;
            $total=array_sum(array_column($t['itemsPlano'],'h'));
            foreach ($t['itemsPlano'] as $i=>$item) {
                $fin=$i===count($t['itemsPlano'])-1?$b:$cursor+($b-$a)*$item['h']/$total;
                $ys[]=$cursor;$ys[]=$fin;
                $rects[]=['x1'=>$x1,'x2'=>$x2,'y1'=>$cursor,'y2'=>$fin,'item'=>$item];
                $cursor=$fin;
            }
        }
        $normalizar=fn($v)=>sprintf('%.5f',$v);
        $ejes=function($v) use ($normalizar) { $v=array_values(array_unique(array_map($normalizar,$v)));sort($v,SORT_NUMERIC);return $v; };
        $xs=$ejes($xs);$ys=$ejes($ys);$ix=array_flip($xs);$iy=array_flip($ys);
        $rango=function($x1,$x2,$y1,$y2) use($ix,$iy,$normalizar) {
            return ['col1'=>$ix[$normalizar($x1)]+1,'col2'=>$ix[$normalizar($x2)],'fila1'=>$iy[$normalizar($y1)]+1,'fila2'=>$iy[$normalizar($y2)]];
        };
        $celdas=[];foreach ($rects as $r) $celdas[]=$rango($r['x1'],$r['x2'],$r['y1'],$r['y2'])+['item'=>$r['item']];
        $dias=[];foreach ($p['dias'] as $j=>$dia) {
            $a=self::MARGEN+self::HORA+$j*$p['anchoDia'];$dias[$dia]=[$ix[$normalizar($a)]+1,$ix[$normalizar($a+$p['anchoDia'])]];
        }
        $horas=[];foreach ($p['limites'] as $i=>$h) if (isset($p['limites'][$i+1])) {
            $fin=$p['limites'][$i+1];$horas[]=['inicio'=>$h,'fin'=>$fin,'fila1'=>$iy[$normalizar($p['y'][$h])]+1,'fila2'=>$iy[$normalizar($p['y'][$fin])]];
        }
        return ['xs'=>$xs,'ys'=>$ys,'celdas'=>$celdas,'dias'=>$dias,'horas'=>$horas];
    }

    public static function envolver(string $txt,float $ancho,float $fs,bool $bold,callable $medir): array
    {
        if ($ancho<=0) throw new RuntimeException('Ancho de tarjeta inválido.');
        $out=[];$actual='';
        foreach (preg_split('/\s+/u',trim($txt),-1,PREG_SPLIT_NO_EMPTY) as $palabra) {
            $nuevo=trim($actual.' '.$palabra);
            if ($medir($nuevo,$fs,$bold)<=$ancho) { $actual=$nuevo; continue; }
            if ($actual!=='') { $out[]=$actual; $actual=''; }
            foreach (preg_split('//u',$palabra,-1,PREG_SPLIT_NO_EMPTY) as $letra) {
                if ($actual!=='' && $medir($actual.$letra,$fs,$bold)>$ancho) { $out[]=$actual; $actual=''; }
                if ($medir($letra,$fs,$bold)>$ancho) throw new RuntimeException('Tarjeta demasiado estrecha.');
                $actual.=$letra;
            }
        }
        if ($actual!=='') $out[]=$actual;
        return $out;
    }
}
