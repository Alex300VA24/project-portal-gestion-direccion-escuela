<?php

namespace App\Modules\GestionHorarios\Services;

use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/** Una hoja y área imprimible A4 por alcance. El detalle queda fuera de impresión. */
final class XlsxAcademicoA4
{
    public function generar(array $grupos,array $datos): string
    {
        $pdf=new Dompdf; $fm=$pdf->getFontMetrics();
        $normal=$fm->getFont('DejaVu Sans','normal');$bold=$fm->getFont('DejaVu Sans','bold');
        if (!$normal || !$bold) throw new RuntimeException('No se encontró la fuente institucional.');
        $medir=fn($t,$fs,$b)=>$fm->getTextWidth($t,$b?$bold:$normal,$fs);
        $libro=new Spreadsheet;
        $libro->getDefaultStyle()->getFont()->setName('Arial')->setSize(8)->getColor()->setRGB('141A33');
        try {
            foreach ($grupos as $i=>$g) {
                $p=PlanoAcademicoA4::preparar($g,$medir);$r=PlanoAcademicoA4::rejilla($p);
                $ws=$i===0?$libro->getActiveSheet():$libro->createSheet();
                $nombre=($g['alcance']??'')!==''?'Horario seleccionado':'Ciclo '.$g['ciclo'].'-'.$g['seccion'];
                $nombre=str_replace(['\\','/', '*','?',':','[',']'],'-',$nombre);
                $nombre=mb_substr($nombre,0,25);
                if ($libro->getSheetByName($nombre)!==null && $libro->getSheetByName($nombre)!==$ws) $nombre.='-'.($i+1);
                $ws->setTitle($nombre);$ws->setShowGridLines(false);
                $n=count($r['xs'])-1;$ultima=Coordinate::stringFromColumnIndex($n);
                $celda=fn($c,$f)=>Coordinate::stringFromColumnIndex($c).$f;
                $pintar=function($c1,$f1,$c2,$f2,$txt,$fondo='FFFFFF',$negrita=false,$fs=8,$centro=false,$color='141A33')use($ws,$celda){
                    $a=$celda($c1,$f1);$b=$celda($c2,$f2);$rango=$a.':'.$b;
                    if ($a!==$b) $ws->mergeCells($rango);
                    $ws->getCell($a)->setValueExplicit($txt,DataType::TYPE_STRING);
                    $st=$ws->getStyle($rango);
                    $st->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fondo);
                    $st->getFont()->setName('Arial')->setSize($fs)->setBold($negrita)->getColor()->setRGB($color);
                    $st->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal($centro?Alignment::HORIZONTAL_CENTER:Alignment::HORIZONTAL_LEFT);
                    $st->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('DFE3EA');
                };
                // Convertir puntos a píxeles; el ajuste a A4 corrige las diferencias de impresora.
                for($j=1;$j<=$n;$j++) {
                    $pt=(float)$r['xs'][$j]-(float)$r['xs'][$j-1];
                    $ws->getColumnDimension(Coordinate::stringFromColumnIndex($j))->setWidth(max(.15,($pt*4/3-5)/7));
                }
                $titulos=[['UNIVERSIDAD NACIONAL DE TRUJILLO',13,18],[(string)$g['facultad'],8,12],[(string)$g['escuela'],9,13],['HORARIO ACADÉMICO · '.($datos['periodoSeleccionado']->codigo??'').' · '.PlanoAcademicoA4::titulo($g),10,18],[(string)($datos['seleccion']->estado??''),8,12]];
                foreach ($titulos as $k=>[$txt,$fs,$h]) {
                    $pintar(1,$k+1,$n,$k+1,$txt,$k===3?'17224D':'FFFFFF',true,$fs,$k===3,$k===3?'FFFFFF':'141A33');
                    $ws->getRowDimension($k+1)->setRowHeight($h);
                }
                $cab=6;
                if ($p['compacto']) {
                    $pintar(1,$cab,$n,$cab,'RELACIÓN DE ASIGNATURAS Y DOCENTES · Referencias de las tarjetas','F3F6F3',true);$cab++;
                    // Una relación completa, con una fila por referencia. Sin omitir nombres.
                    $porAlias=[];
                    foreach ($p['tarjetas'] as $t) foreach ($t['itemsPlano'] as $it) $porAlias[$it['sesion']['alias']]=$it['sesion'];
                    ksort($porAlias);
                    foreach ($porAlias as $alias=>$s) {
                        $pintar(1,$cab,$n,$cab,$alias.' · '.$s['codigo'].' · '.$s['curso'].' · '.$s['docente'],'FFFFFF',false,7);
                        $ws->getRowDimension($cab++)->setRowHeight(14);
                    }
                }
                $pintar(1,$cab,1,$cab,'HORA','17224D',true,8,true,'FFFFFF');
                foreach ($r['dias'] as $dia=>[$a,$b]) $pintar($a,$cab,$b,$cab,$dia,'17224D',true,8,true,'FFFFFF');
                $ws->getRowDimension($cab)->setRowHeight(14);
                $fin=$cab+count($r['ys'])-1;
                for($j=1;$j<count($r['ys']);$j++) $ws->getRowDimension($cab+$j)->setRowHeight(max(.5,(float)$r['ys'][$j]-(float)$r['ys'][$j-1]));
                $filas=array_column(DistribucionHoraria::filasAcademicas($g,($datos['bloquesPeriodo']??collect())->all()),null,'inicio');
                foreach ($r['horas'] as $k=>$h) {
                    $a=$cab+$h['fila1'];$b=$cab+$h['fila2'];$rec=($filas[780]['receso']??false)&&$h['inicio']>=780&&$h['fin']<=840;
                    $fondo=$rec?'E9ECEB':($k%2?'FFFFFF':'F3F6F3');
                    $ws->getStyle('A'.$a.':'.$ultima.$b)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fondo);
                    $pintar(1,$a,1,$b,DistribucionHoraria::rango12($h['inicio'],$h['fin']),$fondo,true,7,true);
                    if ($rec) $pintar(2,$a,$n,$b,'RECESO',$fondo,true,8,true);
                    else foreach ($r['dias'] as [$c1,$c2]) $ws->getStyle($celda($c1,$a).':'.$celda($c2,$b))->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('DFE3EA');
                }
                foreach ($r['celdas'] as $c) {
                    $it=$c['item'];$s=$it['sesion'];
                    $txt=implode("\n",array_column($it['lineas'],0));
                    $pintar($c['col1'],$cab+$c['fila1'],$c['col2'],$cab+$c['fila2'],$txt,$s['color'],false,$p['fuente']);
                    $ws->getStyle($celda($c['col1'],$cab+$c['fila1']).':'.$celda($c['col2'],$cab+$c['fila2']))->getBorders()->getLeft()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(PlanoAcademicoA4::colorActividad($s['tipo'])[1]);
                }
                $ws->freezePane('B'.($cab+1));
                $ws->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(1)->setPrintArea('A1:'.$ultima.$fin);
                $ws->getPageMargins()->setLeft(.23)->setRight(.23)->setTop(.23)->setBottom(.23);
                $ws->getHeaderFooter()->setOddFooter('&CUNT · '.($datos['periodoSeleccionado']->codigo??'').' · '.PlanoAcademicoA4::titulo($g));
                // Detalle completo en la misma pestaña, fuera del área de impresión. Se
                // muestra en filas amplias, independientes de los carriles de la cuadrícula.
                $f=$fin+3;$pintar(1,$f,$n,$f,'DETALLE DE SESIONES · NO SE IMPRIME','17224D',true,8,true,'FFFFFF');$f++;
                foreach ($g['sesiones'] as $s) {
                    $txt=$s['dia'].' · '.$s['horario'].' · '.$s['codigo'].' · '.$s['curso']."\n".$s['tipo'].' · '.$s['grupo'].' · '.$s['ambiente'].' · '.$s['docente'].' · '.$s['alcance_sesion'];
                    $pintar(1,$f,$n,$f,$txt,'FFFFFF',false,9);$ws->getRowDimension($f++)->setRowHeight(34);
                }
            }
            ob_start();
            try { (new Xlsx($libro))->save('php://output');return (string)ob_get_clean(); }
            catch (\Throwable $e) {ob_end_clean();throw $e;}
        } finally {$libro->disconnectWorksheets();}
    }
}
