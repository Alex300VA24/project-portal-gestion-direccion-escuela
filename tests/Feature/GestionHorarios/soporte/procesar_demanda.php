<?php

// Proceso hijo de la prueba PostgreSQL aislada.
// Nunca utiliza la configuración de conexión del archivo .env.

require dirname(__DIR__, 4).'/vendor/autoload.php';

use App\Modules\GestionHorarios\Demand\InstantaneaDemanda;
use App\Modules\GestionHorarios\Demand\ProcesadorInstitucional;
use Illuminate\Database\Capsule\Manager as Capsule;

// Obtener la contraseña exclusiva del entorno de pruebas.
$clave = getenv('HORARIOS_PERSISTENCIA_TEST_PASSWORD');

if (! is_string($clave) || $clave === '') {
    throw new RuntimeException(
        'Falta la contraseña del entorno PostgreSQL aislado.'
    );
}

// Configurar la conexión independiente.
$db = new Capsule;

$db->addConnection([
    'driver' => 'pgsql',
    'host' => '127.0.0.1',
    'port' => 55439,
    'database' => 'horarios_codex_test',
    'username' => 'horarios_test',
    'password' => $clave,
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
]);

$connection = $db->getConnection();

// Comunicar el PID de PostgreSQL al proceso principal.
// Este identificador permite comprobar los bloqueos concurrentes.
$pid = $connection->selectOne(
    'SELECT pg_backend_pid() AS pid'
)->pid;

echo $pid."\n";

flush();

// Construir una demanda académica ficticia.
$snapshot = InstantaneaDemanda::desdeArray([
    'periodo' => (int) $argv[1],
    'version' => 2,
    'cursos' => [
        [
            'id_curso_plan' => 1,
            'total' => 5,
            'manana' => 2,
            'tarde' => 1,
        ],
    ],
]);

// Procesar la demanda respetando los bloqueos de PostgreSQL.
$resultado = (new ProcesadorInstitucional(
    $connection
))->procesar($snapshot);

echo $resultado."\n";