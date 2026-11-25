<?php
use App\Modules\GestionHorarios\Services\EstadoGeneracionHorario as Estado;
use App\Modules\GestionHorarios\Services\DistribucionHoraria as Distribucion;
use App\Modules\GestionHorarios\Services\ExportacionHorarioTrabajoDemo as Exportacion;
foreach (['EstadoGeneracionHorario','DistribucionHoraria','HorariosVersionadosDemo','ConsultaSemanalDemo','ExportacionHorarioTrabajoDemo'] as $clase) { require_once dirname(__DIR__, 4).'/app/Modules/GestionHorarios/Services/'.$clase.'.php'; }
$n=0;
function verificar(bool $condicion, string $mensaje): void { global $n; if (!$condicion) { throw new RuntimeException($mensaje); } $n++; }
$base=['estado'=>'EJECUTANDO','fase'=>'FACTIBILIDAD','creada_en'=>'2026-11-25 04:00:00+00','iniciada_en'=>'2026-11-25 04:00:05+00','actualizada_en'=>'2026-11-25 04:00:10+00','resultado'=>['fases_observadas'=>[['fase'=>'PREPARANDO','en'=>'2026-11-25T04:00:05+00:00'],['fase'=>'FACTIBILIDAD','en'=>'2026-11-25T04:00:10+00:00']]]];
$e=Estado::presentar($base,new DateTimeImmutable('2026-11-25T04:00:40Z'));
verificar($e['creada']==='24/11/2026 23:00:00','Cambio de fecha en Lima');
verificar($e['transcurrido']==='00:00:35','Tiempo transcurrido');
verificar($e['sin_latido'] && $e['activa'],'Latido antiguo no significa terminado');
verificar(count($e['historial'])===2 && $e['historial'][1]['fase']==='Buscando un horario compatible','Fases reales');
$e=Estado::presentar(array_replace($base,['estado'=>'FINALIZADA','finalizada_en'=>'2026-11-25T04:00:45Z']),new DateTimeImmutable('2026-11-25T04:02:00Z'));
verificar($e['transcurrido']==='00:00:40' && !$e['activa'],'Tiempo congelado al finalizar');
$e=Estado::presentar(array_replace($base,['estado'=>'ERROR','resultado'=>['estado'=>'CANCELADA']]),new DateTimeImmutable('2026-11-25T04:02:00Z'));
verificar($e['cancelada'] && !$e['activa'],'Cancelación diferenciada');
$d=['plan'=>1,'secciones'=>[['id_seccion'=>1,'ciclo'=>2,'seccion'=>'A','curso'=>'1','nombre'=>'Curso 1'],['id_seccion'=>2,'ciclo'=>3,'seccion'=>'Repetición','curso'=>'2','nombre'=>'Curso 2']], 'actividades'=>[['id'=>1,'seccion_id'=>1,'duracion'=>120,'tipo'=>'TEORIA','docentes'=>[['codigo_docente'=>'a']]],['id'=>2,'seccion_id'=>2,'duracion'=>60,'tipo'=>'LABORATORIO','docentes'=>[['codigo_docente'=>'a']]]], 'sesiones'=>[1=>['dia'=>'LUNES','inicio'=>420,'fin'=>540,'ambiente'=>'R1'],2=>['dia'=>'SABADO','inicio'=>540,'fin'=>600,'ambiente'=>'R2']], 'bloques'=>[[420,480],[720,780],[840,900]]];
$r=Exportacion::adaptar($d,['a'=>'Docente de prueba'],['R1'=>'INFO 2','R2'=>'LAB A']);
$f=Exportacion::filtrar($d,$r,['vista'=>'docente','docente'=>'a','ciclo'=>'2']);
verificar(count($f['sesiones'])===2,'Consulta docente sin filtros residuales');
$g=(new Distribucion)->preparar($f['sesiones'],$f['docentes'],$f['bloques'],'DOCENTE',true);
verificar(count($g)===1 && count($g[0]['dias'])===6,'Consulta unificada y sábado visible');
$filas=array_column(Distribucion::filasAcademicas($g[0],$f['bloques']),null,'inicio');
verificar($filas[780]['receso']===true,'Receso sin bloque ni sesión');
verificar(count($filas[420]['activas']['LUNES'])===1 && count($filas[480]['activas']['LUNES'])===1,'Sesión de dos horas preservada');
$f=Exportacion::filtrar($d,$r,['vista'=>'ambiente','ambiente'=>'R2','ciclo'=>2]);
verificar(count($f['sesiones'])===1 && $f['sesiones'][0]->dia_semana==='SABADO','Filtro ambiente');
$f=Exportacion::filtrar($d,$r,['vista'=>'ciclo','ciclo'=>'2','seccion'=>'A']);
verificar(count($f['sesiones'])===1 && $f['sesiones'][0]->ciclo===2,'Filtro ciclo');
echo "$n comprobaciones correctas.\n";
