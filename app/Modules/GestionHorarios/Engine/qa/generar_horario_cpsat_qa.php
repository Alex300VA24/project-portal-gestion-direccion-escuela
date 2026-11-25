<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use App\Modules\GestionHorarios\Services\HorarioUnicoDemo;
use App\Modules\GestionHorarios\Services\CicloVidaHorarioDemo as Ciclo;
use App\Modules\GestionHorarios\Services\ExportacionHorarioTrabajoDemo;
use Symfony\Component\Process\Process;

// Conector exclusivo de QA aislado. Nunca registrar como ruta HTTP.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'pgsql'
    || getenv('DB_DATABASE') !== 'horarios_demo_2026_ii_demanda' || getenv('HORARIOS_DEMO_PLAN') !== 'QA-HORARIOS' || getenv('HORARIOS_QA_CONSULTAS') !== '1') {
    throw new RuntimeException('Se requiere el entorno aislado de demostración con demanda.');
}
$modo = $argv[1] ?? 'prevalidar';
$segundos = filter_var($argv[2] ?? '150', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>300]]);
if (! in_array($modo, ['prevalidar','generar','editar','reorganizar','aprobar','publicar'], true) || $segundos === false) {
    throw new RuntimeException('Uso: php generar_horario_cpsat_qa.php prevalidar|generar|reorganizar [1..300], editar 150 VERSION ACTIVIDAD DIA INICIO_MIN AMBIENTE, aprobar|publicar 150 VERSION MOTIVO');
}
$root = dirname(__DIR__, 5);
chdir($root);
$fuente = getenv('HORARIOS_DEMO_ARCHIVO');
$puente = __DIR__.'/generar_cpsat_qa.py';
if (! $fuente || ! is_file($fuente) || ! is_file($puente)) { throw new RuntimeException('Falta HORARIOS_DEMO_ARCHIVO o generar_cpsat_qa.py junto al script PHP.'); }
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection();
if (! $app->environment('testing') || $db->getDriverName() !== 'pgsql' || $db->getConfig('host') !== '127.0.0.1'
    || $db->selectOne('SELECT current_database() AS nombre')->nombre !== 'horarios_demo_2026_ii_demanda') {
    throw new RuntimeException('Conexión no autorizada.');
}
$tabla = 'gestion_horarios.qa_horario_revision';
if (! $db->getSchemaBuilder()->hasTable($tabla)) { throw new RuntimeException('Ejecute primero preparar_horarios_versionados_qa.php.'); }
$tablas = ['core.plan_estudios','core.periodo_academico','core.curso_plan','core.curso','core.seccion','core.docente','core.persona','core.ambiente',
    'gestion_horarios.actividad_academica','gestion_horarios.asignacion_docente','gestion_horarios.bloque_horario','gestion_horarios.declaracion_disponibilidad',
    'gestion_horarios.disponibilidad_docente','gestion_horarios.demanda_instantanea','gestion_horarios.demanda_curso','gestion_horarios.oferta_curso',
    'gestion_horarios.restriccion','gestion_horarios.detalle_restriccion','gestion_horarios.ambiente_bloqueo','gestion_horarios.horario','public.users'];
$prioridadTabla='gestion_horarios.qa_prioridad_docente_revision';
$snapshot = function () use ($db, $tablas, $prioridadTabla): array {
    $db->statement('LOCK TABLE '.implode(', ', $tablas).' IN SHARE MODE');
    $hayPrioridad=$db->getSchemaBuilder()->hasTable($prioridadTabla);
    if ($hayPrioridad) { $db->statement('LOCK TABLE '.$prioridadTabla.' IN SHARE MODE'); }
    $planes=$db->table('core.plan_estudios')->get(['id_plan','codigo']);
    $periodos=$db->table('core.periodo_academico')->get(['id_periodo','codigo']);
    if ($planes->count() !== 1 || $planes->first()->codigo !== 'QA-HORARIOS' || $periodos->count() !== 1 || $periodos->first()->codigo !== '2026-II'
        || $db->table('core.docente as d')->leftJoin('core.persona as p','p.id_persona','=','d.id_persona')->where(fn($q)=>$q->whereNull('p.id_persona')->orWhereNull('p.tipo_documento')->orWhere('p.tipo_documento','!=','QA')->orWhere('d.codigo_docente','not like','QA-%'))->exists()
        || ! $db->table('public.users')->where('email','director.qa@horarios.test')->exists()) { throw new RuntimeException('Catálogo o director QA no reconocido.'); }
    $plan=(int)$planes->first()->id_plan; $periodo=(int)$periodos->first()->id_periodo;
    $secciones=$db->table('core.seccion as s')->join('core.curso_plan as cp','cp.id_curso_plan','=','s.id_curso_plan')->join('core.curso as c','c.id_curso','=','cp.id_curso')
        ->where('cp.id_plan',$plan)->where('s.id_periodo',$periodo)->where('s.estado','ACTIVA')->orderBy('s.id_seccion')->get(['s.id_seccion','s.codigo as seccion','s.capacidad','cp.ciclo','c.codigo as curso','c.nombre','c.horas_teoria as ht','c.horas_practica as hp','c.horas_laboratorio as hl'])->all();
    $actividades=$db->table('gestion_horarios.actividad_academica')->orderBy('id_actividad')->get(['id_actividad as id','id_seccion','tipo_actividad as tipo','dia_semana as dia','hora_inicio as inicio','hora_fin as fin'])->all();
    $asignaciones=$db->table('gestion_horarios.asignacion_docente')->whereIn('estado',['PROPUESTA','CONFIRMADA'])->orderBy('id_asignacion')->get(['id_actividad','id_docente','estado'])->all();
    $docentes=$db->table('core.docente')->orderBy('id_docente')->get(['id_docente','codigo_docente','estado','horas_maximas'])->all();
    $bloques=$db->table('gestion_horarios.bloque_horario')->where('id_periodo',$periodo)->orderBy('id_bloque')->get(['hora_inicio as inicio','hora_fin as fin'])->all();
    $declaraciones=$db->table('gestion_horarios.declaracion_disponibilidad')->where('id_periodo',$periodo)->orderBy('id_declaracion')->get(['id_docente','estado','tratamiento_regular'])->all();
    $disponibilidades=$db->table('gestion_horarios.disponibilidad_docente as d')->join('gestion_horarios.bloque_horario as b','b.id_bloque','=','d.id_bloque')->where('d.id_periodo',$periodo)->orderBy('d.id_disponibilidad')->get(['d.id_docente','d.dia_semana as dia','b.hora_inicio as inicio','b.hora_fin as fin'])->all();
    $ambientes=$db->table('core.ambiente')->orderBy('id_ambiente')->get(['id_ambiente','codigo','tipo','capacidad','estado'])->all();
    $restricciones=$db->table('gestion_horarios.restriccion as r')->leftJoin('gestion_horarios.detalle_restriccion as d','d.id_restriccion','=','r.id_restriccion')
        ->where('r.activa',true)->orderBy('r.id_restriccion')->orderBy('d.id_detalle_restriccion')
        ->get(['r.id_restriccion','r.codigo','r.tipo','r.severidad','d.ambito','d.id_periodo','d.parametros'])->all();
    // Conservar la representación anterior cuando no hay reglas nuevas: evita versiones por cambios técnicos.
    if ($restricciones === []) { foreach ($ambientes as $ambiente) unset($ambiente->id_ambiente); }
    $bloqueos=$db->table('gestion_horarios.ambiente_bloqueo')->orderBy('id_bloqueo')->pluck('id_bloqueo')->all();
    $horarios=$db->table('gestion_horarios.horario')->orderBy('id_horario')->pluck('id_horario')->all();
    $instantanea=$db->table('gestion_horarios.demanda_instantanea')->where('id_periodo',$periodo)->orderByDesc('version')->first();
    if (! $instantanea || (int)$instantanea->version !== 1) { throw new RuntimeException('Demanda distinta de la versión histórica 1.'); }
    $demanda=$db->table('gestion_horarios.demanda_curso as d')->join('core.curso_plan as cp','cp.id_curso_plan','=','d.id_curso_plan')->join('core.curso as c','c.id_curso','=','cp.id_curso')->where('d.id_demanda',$instantanea->id_demanda)->where('cp.id_plan',$plan)->orderBy('d.id_curso_plan')->get(['c.codigo','d.total','d.manana','d.tarde'])->all();
    $oferta=$db->table('gestion_horarios.oferta_curso as o')->join('core.curso_plan as cp','cp.id_curso_plan','=','o.id_curso_plan')->join('core.curso as c','c.id_curso','=','cp.id_curso')->where('o.id_periodo',$periodo)->where('cp.id_plan',$plan)->orderBy('o.id_oferta')->get(['c.codigo','o.tipo_oferta','o.estado'])->all();
    $prioridad=$hayPrioridad ? $db->table($prioridadTabla)->orderByDesc('version')->first(['version','orden']) : null;
    if (Ciclo::disponible()) { $db->statement('LOCK TABLE '.Ciclo::CALENDARIO.' IN SHARE MODE'); $cal=Ciclo::calendario(); } else { $cal=['version'=>0]; }
    $resultado=compact('plan','periodo','secciones','actividades','asignaciones','docentes','bloques','declaraciones','disponibilidades','ambientes','restricciones','bloqueos','horarios','demanda','oferta','prioridad');
    if ($cal['version']>0 && ($cal['dias']!==Ciclo::DIAS || $cal['sabados_ciclos']!==[])) $resultado['calendario_qa']=array_diff_key($cal,['version'=>true]);
    return $resultado;
};
$serializar=static fn($x)=>json_encode($x, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
$idTrabajo=HorarioUnicoDemo::id();
if ($idTrabajo===null) { throw new RuntimeException('Registre primero el horario de trabajo único.'); }
$versionBase=(int)$db->table($tabla)->where('propuesta',$idTrabajo)->max('version');
if ($versionBase<1) { throw new RuntimeException('Horario de trabajo sin revisión inicial.'); }
$edicion=null; $base=null; $motivo='';
if (in_array($modo,['reorganizar','aprobar','publicar'],true)) {
    $fila=$db->table($tabla)->where('propuesta',$idTrabajo)->where('version',$versionBase)->first();
    $base=json_decode($fila->datos,true,512,JSON_THROW_ON_ERROR);
    if (($base['cpsat_qa']['validacion_operativa']??false)!==true) throw new RuntimeException('Se requiere un horario validado.');
    if ($modo!=='reorganizar') {
        if (!Ciclo::disponible() || (string)$versionBase!==($argv[3]??'') || trim($argv[4]??'')==='' || mb_strlen($argv[4])>300) throw new RuntimeException('Versión o motivo inválido.');
        $motivo=trim($argv[4]);
    }
}
if ($modo==='editar') {
    $esperada=filter_var($argv[3]??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $actividad=filter_var($argv[4]??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $dia=$argv[5]??'';
    $inicio=filter_var($argv[6]??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>1380]]);
    $ambiente=$argv[7]??'';
    if ($esperada!==$versionBase || $actividad===false || $inicio===false || $inicio%60!==0 || !in_array($dia,['LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO'],true) || strlen($ambiente)>100) { throw new RuntimeException('Movimiento inválido o versión desactualizada.'); }
    $cola='gestion_horarios.qa_generacion';
    if ($db->getSchemaBuilder()->hasTable($cola) && $db->table($cola)->whereIn('estado',['PENDIENTE','EJECUTANDO'])->exists()) { throw new RuntimeException('Hay un cálculo activo.'); }
    $fila=$db->table($tabla)->where('propuesta',$idTrabajo)->where('version',$versionBase)->first();
    $base=json_decode($fila->datos,true,512,JSON_THROW_ON_ERROR);
    if (($base['cpsat_qa']['validacion_operativa']??false)!==true) { throw new RuntimeException('Se requiere una revisión CP-SAT validada.'); }
    $edicion=['base'=>$base,'actividad'=>$actividad,'dia'=>$dia,'inicio'=>$inicio,'ambiente'=>$ambiente===''?null:$ambiente];
}
$datos=$db->transaction($snapshot, 3);
$huella=hash('sha256',$serializar($datos)); $huellaFuente=hash_file('sha256',$fuente);
$python=getenv('HORARIOS_QA_PYTHON') ?: 'python';
$proc=new Process([$python, '-B', $puente, $root, $fuente, $modo, (string)$segundos],$root);
$entrada=$edicion!==null?array_replace($datos,['_edicion'=>$edicion]):($base!==null?array_replace($datos,['_base'=>$base]):$datos);
$proc->setInput($serializar($entrada)); $proc->setTimeout($modo==='editar'?70:$segundos+240);
$proc->run(static function (string $tipo,string $texto): void { if ($tipo === Process::ERR) { fwrite(STDERR,$texto); } });
$r=json_decode($proc->getOutput(),true,512,JSON_THROW_ON_ERROR);
if (! $proc->isSuccessful() || ($r['estado'] ?? '') === 'ERROR') { throw new RuntimeException('No se ejecutó el motor: '.($r['mensaje'] ?? 'Error de infraestructura.')); }
$revision=$r['revision'] ?? null; unset($r['revision']);
if ($modo === 'prevalidar' || ! is_array($revision)) { echo $serializar($r).PHP_EOL; echo "Sin cambios en PostgreSQL.\n"; exit(0); }
if (! in_array($r['estado'],['OPTIMAL','FEASIBLE'],true) || count($revision['sesiones'] ?? []) !== 175 || ($revision['cpsat_qa']['validacion_operativa'] ?? false) !== true) { throw new RuntimeException('Resultado no apto para guardar.'); }
if (in_array($modo,['aprobar','publicar'],true)) {
    $evento=$db->transaction(function() use($db,$snapshot,$serializar,$huella,$huellaFuente,$fuente,$tabla,$idTrabajo,$versionBase,$revision,$modo,$motivo) {
        if (!hash_equals($huella,hash('sha256',$serializar($snapshot()))) || !hash_equals($huellaFuente,hash_file('sha256',$fuente))) throw new RuntimeException('Los recursos cambiaron durante la revisión.');
        Ciclo::sinCalculo();
        $db->statement('LOCK TABLE '.$tabla.' IN SHARE ROW EXCLUSIVE MODE');
        $actual=$db->table($tabla)->where('propuesta',$idTrabajo)->orderByDesc('version')->first();
        if (!$actual || (int)$actual->version!==$versionBase || HorarioUnicoDemo::id()!==$idTrabajo) throw new RuntimeException('La versión cambió.');
        $vigente=json_decode($actual->datos,true,512,JSON_THROW_ON_ERROR);
        // No aprobar una revisión antigua con recursos diferentes: regenerar/revalidar guardando primero.
        $revision['cpsat_qa']['huella_recursos']=$huella; $revision['cpsat_qa']['huella_fuente']=$huellaFuente;
        if (!HorarioUnicoDemo::mismoContenido($vigente,$revision)) throw new RuntimeException('Los recursos o la programación cambiaron. Recalcule antes de aprobar o publicar.');
        $instantanea=null;
        if ($modo==='publicar') {
            $docs=$db->table('core.docente as d')->join('core.persona as p','p.id_persona','=','d.id_persona')->selectRaw("d.codigo_docente, trim(concat_ws(' ',p.nombres,p.apellido_paterno,p.apellido_materno)) as nombre")->pluck('nombre','codigo_docente')->all();
            $rooms=$db->table('core.ambiente')->get(['id_ambiente','codigo','nombre']);
            $adaptado=ExportacionHorarioTrabajoDemo::adaptar($vigente,$docs,$rooms->pluck('nombre','codigo')->all());
            $act=array_column($vigente['actividades'],null,'id'); $filas=[];
            foreach ($adaptado['sesiones'] as $s) {
                $cod=$vigente['sesiones'][$s->id_actividad]['ambiente']??null; $room=$rooms->firstWhere('codigo',$cod);
                foreach ($act[$s->id_actividad]['docentes'] as $d) {
                    $nombre=trim($docs[$d['codigo_docente']]??'');
                    if ($nombre==='') throw new RuntimeException('Docente sin nombre conciliado.');
                    $filas[]=array_merge((array)$s,['id_docente'=>(int)$d['id_docente'],'nombres'=>$nombre,'apellido_paterno'=>null,'apellido_materno'=>null,'id_ambiente'=>$room?(int)$room->id_ambiente:null,'ambiente_codigo'=>$cod]);
                }
            }
            $p=$db->table('core.periodo_academico')->where('id_periodo',$vigente['periodo'])->first(['id_periodo','codigo','fecha_inicio','fecha_fin']);
            $instantanea=['periodo'=>(array)$p,'filas'=>$filas,'ambientes'=>$rooms->map(fn($r)=>['id'=>(int)$r->id_ambiente,'codigo'=>$r->codigo,'nombre'=>$r->nombre])->all()];
        }
        return Ciclo::registrar($idTrabajo,$versionBase,$modo,$motivo,$instantanea);
    },3);
    echo $serializar(['estado'=>'VALIDADO','evento'=>$evento??'SIN_CAMBIOS','version'=>$versionBase]).PHP_EOL; exit(0);
}
$guardado=$db->transaction(function() use ($db,$snapshot,$serializar,$huella,$huellaFuente,$fuente,$revision,$tabla,$idTrabajo,$versionBase,$modo) {
    if (! hash_equals($huella,hash('sha256',$serializar($snapshot()))) || ! hash_equals($huellaFuente,hash_file('sha256',$fuente))) { throw new RuntimeException('La fuente cambió durante el cálculo. Se descarta el resultado; vuelva a generar.'); }
    if ($modo==='editar' && $db->getSchemaBuilder()->hasTable('gestion_horarios.qa_generacion')) {
        $db->statement('LOCK TABLE gestion_horarios.qa_generacion IN SHARE ROW EXCLUSIVE MODE');
        if ($db->table('gestion_horarios.qa_generacion')->whereIn('estado',['PENDIENTE','EJECUTANDO'])->exists()) { throw new RuntimeException('Se inició otro cálculo; vuelva a intentarlo.'); }
    }
    $db->statement('LOCK TABLE '.$tabla.' IN SHARE ROW EXCLUSIVE MODE');
    if (HorarioUnicoDemo::id()!==$idTrabajo) { throw new RuntimeException('El horario de trabajo cambió.'); }
    $actual=$db->table($tabla)->where('propuesta',$idTrabajo)->orderByDesc('version')->first();
    if (! $actual) { throw new RuntimeException('Horario de trabajo no encontrado.'); }
    $version=HorarioUnicoDemo::siguienteVersion($versionBase,(int)$actual->version);
    $revision['cpsat_qa']['huella_recursos']=$huella; $revision['cpsat_qa']['huella_fuente']=$huellaFuente;
    if (HorarioUnicoDemo::mismoContenido(json_decode($actual->datos,true,512,JSON_THROW_ON_ERROR),$revision)) {
        return ['version'=>(int)$actual->version,'nueva_version'=>false];
    }
    $db->table($tabla)->insert(['propuesta'=>$idTrabajo,'version'=>$version,'nombre'=>$actual->nombre,'datos'=>$serializar($revision),'actor_qa'=>'director.qa@horarios.test']);
    return ['version'=>$version,'nueva_version'=>true];
},3);
echo $serializar(array_replace($r,$guardado)).PHP_EOL;
// Mantener el contrato de salida que interpreta el operador ya instalado.
echo ($guardado['nueva_version'] ? "Propuesta QA guardada (solo consulta): " : "Horario QA sin cambios: ").$idTrabajo.PHP_EOL;
