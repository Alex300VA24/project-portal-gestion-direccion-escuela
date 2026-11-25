<?php

use App\Modules\GestionHorarios\Engine\RecuperarEjecucionPrueba;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Feature\GestionHorarios\soporte\EjecutarPreparacionColaFicticia;
use Tests\Feature\GestionHorarios\soporte\ProveedorComplementosPostgresFicticio;

require __DIR__.'/../../../../vendor/autoload.php';
require __DIR__.'/ProveedorComplementosPostgresFicticio.php';
require __DIR__.'/EjecutarPreparacionColaFicticia.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'gh_27b2', 'database.connections.gh_27b2' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55471, 'database' => 'horarios_27b2_test', 'username' => 'horarios_test', 'password' => getenv('HORARIOS_27B2_PASSWORD'), 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public'], 'queue.connections.database.connection' => 'gh_27b2', 'queue.connections.database.retry_after' => 240]);
$id = trim(stream_get_contents(STDIN));
$db = DB::connection();
$recuperador = new RecuperarEjecucionPrueba($db, new ProveedorComplementosPostgresFicticio($db));
// La prueba madre verifica la terminación antes de lanzar ambos procesos.
echo $recuperador->recuperar($id, new EjecutarPreparacionColaFicticia($id), fn () => true);
