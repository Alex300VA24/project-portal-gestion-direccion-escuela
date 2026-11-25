<?php
use App\Modules\GestionHorarios\Services\DistribucionHoraria as D;
use App\Modules\GestionHorarios\Services\PlanoAcademicoA4 as P;
require_once dirname(__DIR__, 4).'/app/Modules/GestionHorarios/Services/DistribucionHoraria.php';
require_once dirname(__DIR__, 4).'/app/Modules/GestionHorarios/Services/PlanoAcademicoA4.php';
// En el portal, medir con la misma fuente y biblioteca de la descarga.
$autoload=dirname(__DIR__,4).'/vendor/autoload.php';
if (is_file($autoload)) require_once $autoload;
if (class_exists(\Dompdf\Dompdf::class)) {
    $pdf=new \Dompdf\Dompdf; $fm=$pdf->getFontMetrics();
    $normal=$fm->getFont('DejaVu Sans','normal');$bold=$fm->getFont('DejaVu Sans','bold');
    $medir=fn($s,$fs,$b)=>$fm->getTextWidth($s,$b?$bold:$normal,$fs);
    echo "Métricas Dompdf.\n";
} elseif ($ruta=getenv('GH_METRICAS_PRUEBA')) {
    $metricas=json_decode(file_get_contents($ruta),true,512,JSON_THROW_ON_ERROR);
    $medir=fn($s,$fs,$b)=>$fs*array_sum(array_map(fn($ch)=>$metricas[$b?'bold':'normal'][$ch]??throw new RuntimeException('Carácter no medido'),preg_split('//u',$s,-1,PREG_SPLIT_NO_EMPTY)));
    echo "Métricas de fuente DejaVu Sans; no se prueba el render PDF.\n";
} else {
    $medir=fn($s,$fs,$b)=>iconv_strlen($s,'UTF-8')*$fs*($b?.61:.56);
    echo "Métricas aproximadas; no se prueba el render PDF.\n";
}
$sesiones=[];$docentes=[];
if (isset($argv[1])) {
    $f=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR)['filas'];
    $acts=array_column($f['actividades_programables'],null,'id');$secs=array_column($f['secciones_propuestas'],null,'id');
    $docs=array_column($f['docentes_locales'],null,'id');$rooms=array_column($f['ambientes'],null,'id');
    foreach ($f['sesiones_programadas'] as $i=>$s) {
        $a=$acts[$s['actividad_id']];$sec=$secs[$a['seccion_id']];
        $sesiones[]=(object)['id_detalle'=>$i,'id_actividad'=>$i,'id_plan'=>1,'ciclo'=>$sec['ciclo'],'seccion'=>$sec['etiqueta'],'curso_codigo'=>$sec['codigo'],'curso_nombre'=>$sec['nombre'],'tipo_actividad'=>['T'=>'TEORIA','P'=>'PRACTICA','LAB'=>'LABORATORIO'][$a['tipo']]??$a['tipo'],'grupo_actividad'=>$a['subgrupo']??'','dia_semana'=>$s['dia'],'hora_inicio'=>D::hora($s['inicio']*60),'hora_fin'=>D::hora($s['fin']*60),'ambiente'=>$rooms[$s['ambiente_id']]['nombre']??'Sin aula'];
        $docentes[$i]=[$docs[$a['docente_local_id']]['nombre']];
    }
} else {
    foreach (['A','B','G1','G2','Repetición'] as $i=>$sec) {
        $sesiones[]=(object)['id_detalle'=>$i,'id_actividad'=>$i,'id_plan'=>1,'ciclo'=>$i===2||$i===3?10:2,'seccion'=>$sec,'curso_codigo'=>'1400'.$i,'curso_nombre'=>'Curso de laboratorio','tipo_actividad'=>'LABORATORIO','grupo_actividad'=>'LAB '.($i+1),'dia_semana'=>$i===4?'SABADO':'LUNES','hora_inicio'=>'09:00','hora_fin'=>'11:00','ambiente'=>'LAB A'];
        $docentes[$i]=['Docente con nombre completo de prueba'];
    }
}
$bloques=[];for($h=420;$h<1200;$h+=60) if ($h!==780) $bloques[]=(object)['hora_inicio'=>D::hora($h),'hora_fin'=>D::hora($h+60)];
$grupos=(new D)->preparar($sesiones,$docentes,$bloques,'',true);
$ids=[];$n=0;
foreach ($grupos as $g) {
    $p=P::preparar($g,$medir);$cuenta=0;
    $r=P::rejilla($p);$ocupadas=[];
    foreach ($r['celdas'] as $c) {
        if ($c['col1']<2 || $c['col2']>=count($r['xs']) || $c['fila1']<1 || $c['fila2']>=count($r['ys'])) throw new RuntimeException('Celda fuera de cuadrícula');
        for ($f=$c['fila1'];$f<=$c['fila2'];$f++) for ($col=$c['col1'];$col<=$c['col2'];$col++) {
            $k=$f.':'.$col;if (isset($ocupadas[$k])) throw new RuntimeException('Celdas superpuestas');$ocupadas[$k]=true;
        }
    }
    if (count($r['celdas'])!==count($g['sesiones'])) throw new RuntimeException('Excel omite o duplica sesiones');
    if (abs(end($p['y'])-P::ABAJO)>0.01 || $p['fuente']<7 || preg_match('/^G\d+$/',$g['seccion'])) throw new RuntimeException('Plano inválido');
    foreach ($p['tarjetas'] as $t) foreach ($t['itemsPlano'] as $item) {
        if ($item['y']<$p['top'] || $item['y']+$item['h']>P::ABAJO+0.01 || $t['x']+$t['w']>P::ANCHO-P::MARGEN+0.01) throw new RuntimeException('Tarjeta fuera de página');
        if (isset($ids[$item['sesion']['id']])) throw new RuntimeException('Sesión duplicada');
        $ids[$item['sesion']['id']]=true;$cuenta++;$n++;
    }
    if ($cuenta!==count($g['sesiones'])) throw new RuntimeException('Sesión omitida');
    echo 'Ciclo '.$g['ciclo'].' / '.$g['seccion'].': '.$cuenta.' sesiones, fuente '.$p['fuente'].' pt'.($p['compacto']?' (relación completa en la página)':'')."\n";
}
if ($n!==count($sesiones)) throw new RuntimeException('Total incorrecto');
echo count($grupos)." planos A4; $n sesiones sin duplicar; celdas de Excel sin superposiciones.\n";
