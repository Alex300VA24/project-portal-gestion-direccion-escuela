<?php

namespace App\Modules\GestionHorarios\Services;

use Dompdf\Dompdf;
use RuntimeException;

/** Dibuja directamente en el lienzo PDF: sin paginación automática de tablas. */
final class PdfAcademicoA4
{
    public function generar(array $grupos, array $datos, ?string $logo): string
    {
        $pdf=new Dompdf;
        $pdf->setPaper('A4','landscape');
        $pdf->loadHtml('<!doctype html><html><head><meta charset="utf-8"></head><body></body></html>','UTF-8');
        $pdf->render();
        $c=$pdf->getCanvas(); $fm=$pdf->getFontMetrics();
        $regular=$fm->getFont('DejaVu Sans','normal'); $negrita=$fm->getFont('DejaVu Sans','bold');
        if (!$regular || !$negrita) throw new RuntimeException('No se encontró la fuente PDF institucional.');
        $medir=fn(string $t,float $fs,bool $b)=>$fm->getTextWidth($t,$b?$negrita:$regular,$fs);
        // Validar TODOS los planos antes de producir el documento.
        $planos=array_map(fn($g)=>PlanoAcademicoA4::preparar($g,$medir),$grupos);
        $azul=self::rgb('17224D');$tinta=self::rgb('141A33');$borde=self::rgb('DFE3EA');$blanco=[1,1,1];
        $m=PlanoAcademicoA4::MARGEN;$ancho=PlanoAcademicoA4::ANCHO-2*$m;
        $texto=function(string $t,float $x,float $y,float $fs=8,bool $b=false,?array $color=null) use($c,$regular,$negrita,$tinta){$c->text($x,$y,$t,$b?$negrita:$regular,$fs,$color??$tinta);};
        $centro=function(string $t,float $x,float $y,float $w,float $fs=8,bool $b=false,?array $color=null)use($medir,$texto){$texto($t,$x+($w-$medir($t,$fs,$b))/2,$y,$fs,$b,$color);};
        foreach ($grupos as $i=>$g) {
            if ($i>0) $c->new_page();
            $p=$planos[$i];$fs=$p['fuente'];$y=$p['y'];$dw=$p['anchoDia'];$hora=PlanoAcademicoA4::HORA;
            $c->filled_rectangle(0,0,PlanoAcademicoA4::ANCHO,4,$azul);
            $tx=$m;
            if ($logo && is_file($logo) && ($size=@getimagesize($logo))) {
                $escala=min(36/$size[0],36/$size[1]);$c->image($logo,$m,13,$size[0]*$escala,$size[1]*$escala);$tx+=45;
            }
            $texto('UNIVERSIDAD NACIONAL DE TRUJILLO',$tx,11,11,true,$azul);
            $texto(mb_strtoupper((string)$g['facultad']),$tx,25,7.5);
            $texto(mb_strtoupper((string)$g['escuela']),$tx,36,8,true);
            $periodo='HORARIO ACADÉMICO · '.($datos['periodoSeleccionado']->codigo??'');
            $texto($periodo,$m+$ancho-$medir($periodo,9,true),12,9,true,$azul);
            $estado=(string)($datos['seleccion']->estado??'');
            $texto($estado,$m+$ancho-$medir($estado,7,false),26,7,false);
            $titulo=PlanoAcademicoA4::titulo($g);
            $lineas=PlanoAcademicoA4::envolver($titulo,$ancho,10,true,$medir);
            if (count($lineas)>1) throw new RuntimeException('El encabezado excede el ancho de impresión.');
            $texto($titulo,$m,52,10,true,$azul);
            if ($p['compacto']) {
                $texto('RELACIÓN DE ASIGNATURAS Y DOCENTES · referencia en las tarjetas',$m,68,7,true,$azul);
                foreach ($p['catalogo']['items'] as $item) {
                    $cy=PlanoAcademicoA4::ARRIBA+$item['y'];
                    $c->filled_rectangle($item['x'],$cy,$item['w']-1,$item['h'],self::rgb($item['color']));
                    foreach ($item['lineas'] as $n=>[$l,$b]) $texto($l,$item['x']+4,$cy+1+$n*$fs*1.18,$fs,$b);
                }
            }
            $head=$p['top']-12;
            $c->filled_rectangle($m,$head,$ancho,12,$azul);
            $centro('HORA',$m,$head+1,$hora,7,true,$blanco);
            foreach ($p['dias'] as $j=>$dia) $centro($dia,$m+$hora+$j*$dw,$head+1,$dw,7,true,$blanco);
            $filas=array_column(DistribucionHoraria::filasAcademicas($g,($datos['bloquesPeriodo']??collect())->all()),null,'inicio');
            foreach ($p['limites'] as $k=>$h) {
                if (!isset($p['limites'][$k+1])) break;
                $fin=$p['limites'][$k+1];$alto=$y[$fin]-$y[$h];
                $receso=($filas[780]['receso']??false) && $h>=780 && $fin<=840;
                $c->filled_rectangle($m,$y[$h],$ancho,$alto,self::rgb($receso?'E9ECEB':($k%2?'FFFFFF':'F3F6F3')));
                $c->line($m,$y[$h],$m+$ancho,$y[$h],$borde,.35);
                $rotulo=DistribucionHoraria::rango12($h,$fin);
                $centro($rotulo,$m,$y[$h]+max(0,($alto-8)/2),$hora,6.5,true);
                if (!$receso) for($j=0;$j<=count($p['dias']);$j++) $c->line($m+$hora+$j*$dw,$y[$h],$m+$hora+$j*$dw,$y[$fin],$borde,.35);
                else $centro('RECESO',$m+$hora,$y[$h]+max(0,($alto-8)/2),$ancho-$hora,7,true,$azul);
            }
            foreach ($p['tarjetas'] as $t) foreach ($t['itemsPlano'] as $item) {
                $s=$item['sesion'];$c->filled_rectangle($t['x'],$item['y'],$t['w'],$item['h'],self::rgb($s['color']));
                $trazo=self::rgb(PlanoAcademicoA4::colorActividad($s['tipo'])[1]);
                $c->rectangle($t['x'],$item['y'],$t['w'],$item['h'],$borde,.5);
                $c->filled_rectangle($t['x'],$item['y'],2,$item['h'],$trazo);
                $inicio=$item['y']+max(1,($item['h']-count($item['lineas'])*$fs*1.18)/2);
                foreach ($item['lineas'] as $n=>[$l,$b]) $texto($l,$t['x']+3,$inicio+$n*$fs*1.18,$fs,$b);
            }
            $c->rectangle($m,$p['top'],$ancho,PlanoAcademicoA4::ABAJO-$p['top'],$borde,.5);
            $estado=(string)($datos['seleccion']->estado??'');
            $texto('Las tarjetas indican asignatura, actividad, ambiente y docente. Las coincidencias conservan su franja real.',$m,579,6.5);
            $pag='Página '.($i+1).' de '.count($grupos);$texto($pag,$m+$ancho-$medir($pag,7,false),579,7);
        }
        if ($c->get_page_count()!==count($grupos)) throw new RuntimeException('La cantidad de páginas no coincide con los horarios solicitados.');
        return $pdf->output();
    }

    private static function rgb(string $hex): array
    { return [hexdec(substr($hex,0,2))/255,hexdec(substr($hex,2,2))/255,hexdec(substr($hex,4,2))/255]; }
}
