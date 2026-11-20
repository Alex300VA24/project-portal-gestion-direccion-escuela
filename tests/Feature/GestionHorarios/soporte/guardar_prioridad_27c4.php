<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Feature\GestionHorarios\soporte\PrioridadDocenteFicticia;

require __DIR__.'/../../../../vendor/autoload.php';
require __DIR__.'/PrioridadDocenteFicticia.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'gh_27b2', 'database.connections.gh_27b2' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55471, 'database' => 'horarios_27b2_test', 'username' => 'horarios_test', 'password' => getenv('HORARIOS_27B2_PASSWORD'), 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public']]);
$entrada = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
try {
    (new PrioridadDocenteFicticia(DB::connection()))->guardar((new User)->forceFill(['id' => 100]), $entrada['version'], $entrada['cola']);
    echo 'GUARDADA';
} catch (ValidationException $e) {
    echo array_key_exists('version', $e->errors()) ? 'VERSION_OBSOLETA' : 'VALIDACION';
}
