<?php

use App\Modules\GestionHorarios\Engine\ReconciliarIntentosPrueba;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'gh_27b2', 'database.connections.gh_27b2' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55471, 'database' => 'horarios_27b2_test', 'username' => 'horarios_test', 'password' => getenv('HORARIOS_27B2_PASSWORD'), 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public']]);
$datos = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
echo json_encode((new ReconciliarIntentosPrueba(DB::connection()))->cercarExpirado($datos['id'], $datos['intento'], $datos['generacion']), JSON_THROW_ON_ERROR);
