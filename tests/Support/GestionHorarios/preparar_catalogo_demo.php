<?php

// Catálogo de prueba para una base vacía. No acredita pertenencia a un plan institucional.
use Database\Seeders\CotecuDemoSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection();
if (! $app->environment('testing') || $db->getDriverName() !== 'pgsql'
    || $db->getConfig('host') !== '127.0.0.1'
    || $db->selectOne('SELECT current_database() AS nombre')->nombre !== 'horarios_demo_2026_ii') {
    throw new RuntimeException('Se necesita la base local de prueba horarios_demo_2026_ii.');
}
$archivo = getenv('HORARIOS_DEMO_ARCHIVO');
$referencia = dirname($archivo, 3).'/ReferenciaCurricular2018.php';
if (! is_file($referencia) || ! is_file($archivo)) {
    throw new RuntimeException('Indique el archivo original en HORARIOS_DEMO_ARCHIVO.');
}
// Leer la lista declarativa sin ejecutar PHP del repositorio de referencia.
$texto = file_get_contents($referencia);
preg_match_all("/'codigo' => '([^']+)',.*?'creditos' => (\d+)/s", $texto, $filas, PREG_SET_ORDER);
$creditos = array_column($filas, 2, 1);
$origen = json_decode(file_get_contents($archivo), true, 512, JSON_THROW_ON_ERROR)['filas'];
$base = new ReflectionClass(CotecuDemoSeeder::class);
$db->transaction(function () use ($db, $base, $origen, $creditos) {
    if ($db->table('core.plan_estudios')->exists() || $db->table('core.periodo_academico')->exists()) {
        throw new RuntimeException('El catálogo de prueba ya está preparado o contiene datos.');
    }
    $facultad = $db->table('core.facultad')->insertGetId($base->getConstant('FACULTAD'), 'id_facultad');
    $escuela = $db->table('core.escuela_profesional')->insertGetId($base->getConstant('ESCUELA') + ['id_facultad' => $facultad], 'id_escuela');
    $plan = $db->table('core.plan_estudios')->insertGetId(array_merge($base->getConstant('PLAN'), [
        'id_escuela' => $escuela, 'codigo' => 'QA-HORARIOS', 'nombre' => 'Catálogo de prueba de correspondencias',
    ]), 'id_plan');
    $db->table('core.periodo_academico')->insert(collect($base->getConstant('PERIODOS'))->firstWhere('codigo', '2026-II'));
    $area = $db->table('core.area_formacion')->insertGetId($base->getConstant('AREAS')[0], 'id_area');
    foreach ($origen['cursos'] as $c) {
        if (! isset($creditos[$c['codigo']])) {
            continue;
        }
        $curso = $db->table('core.curso')->insertGetId(array_intersect_key($c, array_flip([
            'codigo', 'nombre', 'horas_teoria', 'horas_practica', 'horas_laboratorio',
        ])) + ['creditos' => (int) $creditos[$c['codigo']], 'estado' => 'Activo'], 'id_curso');
        $db->table('core.curso_plan')->insert(['id_plan' => $plan, 'id_curso' => $curso, 'ciclo' => $c['ciclo'],
            'id_area' => $area, 'tipo' => 'Prueba']);
    }
});
echo "Catálogo de ensayo QA-HORARIOS preparado.\n";
