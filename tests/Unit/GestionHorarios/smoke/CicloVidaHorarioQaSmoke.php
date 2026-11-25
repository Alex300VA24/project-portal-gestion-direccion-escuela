<?php
use App\Modules\GestionHorarios\Services\CicloVidaHorarioDemo as C;
require_once dirname(__DIR__, 4).'/app/Modules/GestionHorarios/Services/CicloVidaHorarioDemo.php';
$n=0;
function verificar($cond,$msg){global $n;if(!$cond)throw new RuntimeException($msg);$n++;}
function rechazar(callable $f){try{$f();}catch(InvalidArgumentException $e){return true;}return false;}
verificar(C::normalizarCalendario(['VIERNES','LUNES'],['4','2'])===['dias'=>['LUNES','VIERNES'],'sabados_ciclos'=>[2,4]],'orden de calendario');
verificar(rechazar(fn()=>C::normalizarCalendario([],[])),'sin dias');
verificar(rechazar(fn()=>C::normalizarCalendario(['DOMINGO'],[])),'domingo');
verificar(rechazar(fn()=>C::normalizarCalendario(['LUNES'],[11])),'ciclo invalido');
verificar(rechazar(fn()=>C::normalizarCalendario(['LUNES','LUNES'],[])),'dia duplicado');
verificar(C::decidir('aprobar',2,null,null)==='APROBADO','aprobacion');
verificar(C::decidir('aprobar',2,2,null)===null,'sin duplicado');
verificar(rechazar(fn()=>C::decidir('publicar',2,1,1)),'requiere aprobar vigente');
verificar(C::decidir('publicar',2,2,null)==='PUBLICADO','publicar');
verificar(C::decidir('publicar',3,3,2)==='PUBLICADO','actualizar');
verificar(C::decidir('publicar',2,2,2)===null,'publicacion identica');
verificar(rechazar(fn()=>C::decidir('retirar',1,2,2)),'retiro obsoleto');
verificar(C::decidir('retirar',2,3,2)==='RETIRADO','retirar sin afectar nueva version');
echo "$n comprobaciones correctas.\n";
