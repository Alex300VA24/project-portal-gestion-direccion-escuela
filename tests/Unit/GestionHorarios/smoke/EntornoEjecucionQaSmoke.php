<?php

use App\Modules\GestionHorarios\Services\EntornoEjecucionQa as Qa;

function base_path(string $ruta = ''): string
{
    return dirname(__DIR__, 4).($ruta === '' ? '' : '/'.$ruta);
}
require_once base_path('app/Modules/GestionHorarios/Services/EntornoEjecucionQa.php');

if (! is_file(Qa::conector())) {
    throw new RuntimeException('El conector no está dentro del módulo.');
}
$variables = ['APP_ENV'=>'testing', 'DB_CONNECTION'=>'pgsql', 'DB_DATABASE'=>'horarios_demo_2026_ii_demanda',
    'HORARIOS_DEMO_PLAN'=>'QA-HORARIOS', 'HORARIOS_QA_CONSULTAS'=>'1'];
foreach ($variables as $clave=>$valor) {
    putenv($clave.'='.$valor);
}
$n=1;
foreach ($variables as $clave=>$valor) {
    putenv($clave.'=rechazado');
    try {
        Qa::variablesProceso();
        throw new LogicException('Se aceptó un entorno incorrecto: '.$clave);
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'Variable QA inválida: '.$clave) throw $e;
        $n++;
    } finally {
        putenv($clave.'='.$valor);
    }
}
putenv('HORARIOS_DEMO_ARCHIVO');
try {
    Qa::variablesProceso();
    throw new LogicException('Se aceptó una fuente ausente.');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'Falta HORARIOS_DEMO_ARCHIVO.') throw $e;
    $n++;
}
echo $n." comprobaciones correctas; no se conectó a la base.\n";
