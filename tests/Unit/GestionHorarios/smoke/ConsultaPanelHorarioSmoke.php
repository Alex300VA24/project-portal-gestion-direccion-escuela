<?php
use App\Modules\GestionHorarios\Services\DistribucionHoraria as D;
use App\Modules\GestionHorarios\Services\ConsultaPublicadaPresentacion as C;
require_once dirname(__DIR__, 4).'/app/Modules/GestionHorarios/Services/DistribucionHoraria.php';
require_once dirname(__DIR__, 4).'/app/Modules/GestionHorarios/Services/ConsultaPublicadaPresentacion.php';
$n=0;function check($ok,$msg){global $n;if(!$ok)throw new RuntimeException($msg);$n++;}
check(D::rango12(420,480)==='07 am a 08 am','mañana');
check(D::rango12(660,720)==='11 am a 12 pm','mediodía');
check(D::rango12(720,780)==='12 pm a 01 pm','mediodía');
check(D::rango12(780,840)==='01 pm a 02 pm','receso');
check(D::rango12(1140,1200)==='07 pm a 08 pm','noche');
check(D::hora12(0)==='12 am' && D::hora12(450)==='07:30 am','medianoche/fracción');
$f=(object)['id_actividad'=>1,'id_plan'=>1,'id_ambiente'=>7,'ciclo'=>2,'seccion'=>'A','dia_semana'=>'LUNES','hora_inicio'=>'09:00','hora_fin'=>'11:00','tipo_actividad'=>'TEORIA','curso_codigo'=>'13034','curso_nombre'=>'Análisis matemático','ambiente'=>'INFO 2','nombres'=>'Ana','apellido_paterno'=>'Docente','apellido_materno'=>''];
$f2=clone $f;$f2->nombres='Beatriz';
$f3=clone $f;$f3->id_actividad=2;$f3->hora_inicio='11:00';$f3->hora_fin='13:00';
$g=C::cuadriculas([$f,$f2,$f3],'ciclo','');
check(count($g)===1 && count($g[0]['sesiones'])===2,'no duplica profesores');
check($g[0]['dias']===D::DIAS && $g[0]['desde']===420 && $g[0]['hasta']===1200,'semana completa');
check($g[0]['sesiones'][0]['docente']==='Ana Docente, Beatriz Docente','nombres');
$a=[['id'=>7,'nombre'=>'INFO 2'],['id'=>9,'nombre'=>'LAB A']];
$p=C::ocupacion($a,[$f,$f2,$f3],540,true);
check(count($p[0]['actuales'])===1 && count($p[0]['proximas'])===1,'actual y próxima');
check($p[1]['estado']==='Sin clase programada','no confundir con presencia');
$p=C::ocupacion($a,[$f,$f3],660,true);
check(count($p[0]['actuales'])===1 && $p[0]['actuales'][0]->inicio===660,'límite exclusivo de salida');
check(C::ocupacion($a,[$f],540,false)[0]['estado']==='Fuera de vigencia','fuera de vigencia');
check(C::vigente((object)['fecha_inicio'=>'2026-07-14','fecha_fin'=>'2026-12-30'],'2026-12-30'),'último día');
check(!C::vigente((object)['fecha_inicio'=>'2026-07-14','fecha_fin'=>'2026-12-30'],'2026-12-31'),'periodo terminado');
echo "$n comprobaciones correctas.\n";
