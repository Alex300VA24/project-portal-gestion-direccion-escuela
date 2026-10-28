<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$modules = [
    ['class' => 'PreMatricula', 'entity' => 'PreMatricula', 'slug' => 'pre-matricula', 'schema' => 'pre_matricula', 'name' => 'Pre Matrícula'],
    ['class' => 'Bienestar', 'entity' => 'Bienestar', 'slug' => 'bienestar', 'schema' => 'bienestar', 'name' => 'Bienestar'],
    ['class' => 'InvestigacionFormativa', 'entity' => 'InvestigacionFormativa', 'slug' => 'investigacion-formativa', 'schema' => 'investigacion_formativa', 'name' => 'Investigación Formativa'],
    ['class' => 'SeguimientoEstudiante', 'entity' => 'SeguimientoEstudiante', 'slug' => 'seguimiento-estudiante', 'schema' => 'seguimiento_estudiante', 'name' => 'Seguimiento al Estudiante'],
    ['class' => 'WebInstitucional', 'entity' => 'WebInstitucional', 'slug' => 'web-institucional', 'schema' => 'web_institucional', 'name' => 'Web Institucional'],
    ['class' => 'Calidad', 'entity' => 'Calidad', 'slug' => 'calidad', 'schema' => 'calidad', 'name' => 'Calidad'],
    ['class' => 'GestionHorarios', 'entity' => 'Horario', 'slug' => 'gestion-horarios', 'schema' => 'gestion_horarios', 'name' => 'Gestión de Horarios'],
    ['class' => 'Cotecu', 'entity' => 'Cotecu', 'slug' => 'cotecu', 'schema' => 'cotecu', 'name' => 'COTECU'],
    ['class' => 'GestionPracticas', 'entity' => 'Practica', 'slug' => 'gestion-practicas', 'schema' => 'gestion_practicas', 'name' => 'Gestión de Prácticas'],
    ['class' => 'TesisProyectoTesis', 'entity' => 'Tesis', 'slug' => 'tesis-proyecto-tesis', 'schema' => 'tesis_proyecto_tesis', 'name' => 'Tesis y Proyecto de Tesis'],
    ['class' => 'GestionMatriculas', 'entity' => 'Matricula', 'slug' => 'gestion-matriculas', 'schema' => 'gestion_matriculas', 'name' => 'Gestión de Matrículas'],
    ['class' => 'SeguimientoEgresado', 'entity' => 'SeguimientoEgresado', 'slug' => 'seguimiento-egresado', 'schema' => 'seguimiento_egresado', 'name' => 'Seguimiento al Egresado'],
];

function writeModuleFile(string $path, string $contents): void
{
    $directory = dirname($path);

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    file_put_contents($path, $contents);
}

foreach ($modules as $module) {
    $replace = [
        '{{class}}' => $module['class'],
        '{{entity}}' => $module['entity'],
        '{{slug}}' => $module['slug'],
        '{{schema}}' => $module['schema'],
        '{{name}}' => $module['name'],
    ];

    $moduleRoot = $root.'/app/Modules/'.$module['class'];
    $viewRoot = $root.'/resources/views/modules/'.$module['slug'];

    $controller = <<<'PHP'
<?php

namespace App\Modules\{{class}}\Controllers;

use App\Modules\{{class}}\Requests\{{entity}}Request;
use App\Modules\{{class}}\Services\{{entity}}Service;
use App\Shared\Controllers\ModuleController;
use Illuminate\Http\RedirectResponse;

final class {{entity}}Controller extends ModuleController
{
    protected const MODULE = '{{name}}';

    protected const SCHEMA = '{{schema}}';

    protected const VIEW = 'modules.{{slug}}.index';

    public function store({{entity}}Request $request, {{entity}}Service $service): RedirectResponse
    {
        $service->handle($request->validated());

        return redirect()
            ->route('{{slug}}.index')
            ->with('status', 'Registro procesado.');
    }
}
PHP;

    $request = <<<'PHP'
<?php

namespace App\Modules\{{class}}\Requests;

use App\Shared\Requests\ModuleRequest;

final class {{entity}}Request extends ModuleRequest
{
}
PHP;

    $model = <<<'PHP'
<?php

namespace App\Modules\{{class}}\Models;

use App\Shared\Models\ModuleModel;

final class Registro extends ModuleModel
{
    protected $table = '{{schema}}.registros';
}
PHP;

    $service = <<<'PHP'
<?php

namespace App\Modules\{{class}}\Services;

use App\Shared\Services\ModuleService;

final class {{entity}}Service extends ModuleService
{
}
PHP;

    $policy = <<<'PHP'
<?php

namespace App\Modules\{{class}}\Policies;

use App\Shared\Policies\ModulePolicy;

final class RegistroPolicy extends ModulePolicy
{
    protected string $permission = '{{slug}}.view';
}
PHP;

    $webRoutes = <<<'PHP'
<?php

use App\Modules\{{class}}\Controllers\{{entity}}Controller;
use Illuminate\Support\Facades\Route;

Route::prefix('{{slug}}')
    ->name('{{slug}}.')
    ->group(function () {
        Route::get('/', [{{entity}}Controller::class, 'index'])->name('index');
        Route::get('/crear', [{{entity}}Controller::class, 'create'])->name('create');
        Route::post('/', [{{entity}}Controller::class, 'store'])->name('store');
    });
PHP;

    $apiRoutes = <<<'PHP'
<?php

use App\Modules\{{class}}\Controllers\{{entity}}Controller;
use Illuminate\Support\Facades\Route;

Route::prefix('{{slug}}')
    ->name('api.{{slug}}.')
    ->group(function () {
        Route::get('/', [{{entity}}Controller::class, 'apiIndex'])->name('index');
    });
PHP;

    $view = <<<'BLADE'
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $module }}</title>
</head>
<body>
    <main>
        <h1>{{ $module }}</h1>
        <p>Esquema PostgreSQL: <code>{{ $schema }}</code></p>
        @isset($creating)
            <p>Formulario base de creación.</p>
        @endisset
    </main>
</body>
</html>
BLADE;

    writeModuleFile($moduleRoot.'/Controllers/'.$module['entity'].'Controller.php', strtr($controller, $replace).PHP_EOL);
    writeModuleFile($moduleRoot.'/Requests/'.$module['entity'].'Request.php', strtr($request, $replace).PHP_EOL);
    writeModuleFile($moduleRoot.'/Models/Registro.php', strtr($model, $replace).PHP_EOL);
    writeModuleFile($moduleRoot.'/Services/'.$module['entity'].'Service.php', strtr($service, $replace).PHP_EOL);
    writeModuleFile($moduleRoot.'/Policies/RegistroPolicy.php', strtr($policy, $replace).PHP_EOL);
    writeModuleFile($moduleRoot.'/Routes/web.php', strtr($webRoutes, $replace).PHP_EOL);
    writeModuleFile($moduleRoot.'/Routes/api.php', strtr($apiRoutes, $replace).PHP_EOL);
    writeModuleFile($viewRoot.'/index.blade.php', $view.PHP_EOL);
}

fwrite(STDOUT, sprintf('Generated %d modules.%s', count($modules), PHP_EOL));
