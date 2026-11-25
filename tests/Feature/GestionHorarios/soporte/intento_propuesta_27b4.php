<?php

// Proceso hijo exclusivo de la prueba de concurrencia; no se registra como ruta ni trabajo.
use Tests\Support\GestionHorarios\Engine\ProteccionPropuestaPrueba;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Feature\GestionHorarios\soporte\ProveedorComplementosPostgresFicticio;

require __DIR__.'/../../../../vendor/autoload.php';
require __DIR__.'/ProveedorComplementosPostgresFicticio.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'gh_27b2', 'database.connections.gh_27b2' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55471, 'database' => 'horarios_27b2_test', 'username' => 'horarios_test', 'password' => getenv('HORARIOS_27B2_PASSWORD'), 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public']]);
$datos = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$db = DB::connection();
$resultado = (new ProteccionPropuestaPrueba($db, new ProveedorComplementosPostgresFicticio($db)))->aceptar($datos['preparada'], $datos['resultado']);
echo json_encode($resultado, JSON_THROW_ON_ERROR);
