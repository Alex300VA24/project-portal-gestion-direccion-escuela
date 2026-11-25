<?php
use App\Modules\GestionHorarios\Services\ConsultaPublicadaPresentacion as C;
use App\Modules\GestionHorarios\Services\DistribucionHoraria as D;
use App\Modules\GestionHorarios\Services\PlanoAcademicoA4 as P;
require_once dirname(__DIR__, 4).'/app/Modules/GestionHorarios/Services/DistribucionHoraria.php';
require_once dirname(__DIR__, 4).'/app/Modules/GestionHorarios/Services/ConsultaPublicadaPresentacion.php';
require_once dirname(__DIR__, 4).'/app/Modules/GestionHorarios/Services/PlanoAcademicoA4.php';
$n=0;
function verificar($ok,$detalle){global $n;if(!$ok)throw new RuntimeException($detalle);$n++;}
$base=(object)['id_actividad'=>1,'id_plan'=>1,'id_ambiente'=>7,'ciclo'=>2,'seccion'=>'A','dia_semana'=>'LUNES','hora_inicio'=>'09:00','hora_fin'=>'11:00','tipo_actividad'=>'TEORIA','curso_codigo'=>'13034','curso_nombre'=>'Análisis matemático','ambiente'=>'INFO 2','nombres'=>'Ana','apellido_paterno'=>'Docente','apellido_materno'=>'','facultad_nombre'=>'Facultad de Ciencias Físicas y Matemáticas','escuela_nombre'=>'Escuela Profesional de Informática'];
$otro=clone $base;$otro->id_actividad=2;$otro->seccion='B';$otro->nombres='Beatriz';
$planos=C::cuadriculas([$base,$otro],'ciclo','Ciclo II');
verificar(count($planos)===2,'Una hoja por sección');
verificar(count($planos[0]['sesiones'])===1 && count($planos[1]['sesiones'])===1,'Sesiones separadas');
$docente=C::cuadriculas([$base],'docente','Ana Docente');
verificar(count($docente)===1 && count($docente[0]['sesiones'])===1 && $docente[0]['sesiones'][0]['docente']==='Ana Docente','El plano no agrega otras sesiones');
verificar($docente[0]['dias']===D::DIAS,'Seis días');
$filas=array_column(D::filasAcademicas($docente[0],C::bloquesVisuales()),null,'inicio');
verificar($filas[780]['receso']===true,'Receso idéntico a consulta');
$medir=fn($t,$fs,$b)=>preg_match_all('/./us',$t)*$fs*.58;
$p=P::preparar($docente[0],$medir);
verificar(count($p['tarjetas'])===1 && $p['tarjetas'][0]['inicio']===540 && $p['tarjetas'][0]['fin']===660,'Intervalo conservado');
verificar($docente[0]['facultad']===$base->facultad_nombre && $docente[0]['escuela']===$base->escuela_nombre,'Cabecera del registro');
verificar(C::cuadriculas([],'ciclo','')===[],'Sin datos no hay planos');
echo "$n comprobaciones correctas.\n";
