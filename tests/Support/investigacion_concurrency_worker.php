<?php

use App\Models\User;
use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Exceptions\EstadoInvalido;
use App\Modules\InvestigacionFormativa\Exceptions\RecursoBloqueado;
use App\Modules\InvestigacionFormativa\Exceptions\VersionDesactualizada;
use App\Modules\InvestigacionFormativa\Models\Encuesta;
use App\Modules\InvestigacionFormativa\Models\Entrega;
use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;
use App\Modules\InvestigacionFormativa\Services\ConfiguracionService;
use App\Modules\InvestigacionFormativa\Services\EncuestaService;
use App\Modules\InvestigacionFormativa\Services\EvaluacionService;
use App\Modules\InvestigacionFormativa\Services\GrupoService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$mode = $argv[2] ?? '';
$applicationName = 'if_concurrency_'.preg_replace('/[^a-z_]/', '', strtolower($mode));
DB::statement("SET application_name = '{$applicationName}'");

try {
    if ($mode === 'roster_import') {
        $exitCode = Artisan::call('investigacion:importar-participantes', [
            'csv' => $argv[4],
            '--apply' => true,
        ]);

        echo json_encode([
            'mutated' => $exitCode === 0,
            'exit_code' => $exitCode,
        ], JSON_THROW_ON_ERROR).PHP_EOL;

        exit(0);
    }

    if ($mode === 'competency_import') {
        $exitCode = Artisan::call('investigacion:importar-competencias', [
            'csv' => $argv[4],
            '--apply' => true,
        ]);

        echo json_encode([
            'mutated' => $exitCode === 0,
            'exit_code' => $exitCode,
        ], JSON_THROW_ON_ERROR).PHP_EOL;

        exit(0);
    }

    $user = User::query()->findOrFail((int) $argv[3]);
    $actor = app(AccesoInvestigacionService::class)->resolver($user);

    if ($mode === 'title') {
        app(GrupoService::class)->actualizarTitulo(
            $actor,
            (int) $argv[4],
            'Título después de la retirada',
            1,
        );
    } elseif ($mode === 'group') {
        app(GrupoService::class)->crear($actor, 9902, [
            'nombre' => 'Grupo concurrente',
            'titulo' => 'Proyecto concurrente',
            'integrante_ids' => [9903],
            'id_linea' => 9802,
            'id_tipo_producto' => 9981,
        ]);
    } elseif ($mode === 'evaluation') {
        $entrega = Entrega::query()->findOrFail((int) $argv[4]);
        $idRubrica = DB::table('investigacion_formativa.entregable')
            ->where('id_entregable', (int) $entrega->id_entregable)
            ->value('id_rubrica');
        $criterios = DB::table('investigacion_formativa.criterio')
            ->where('id_rubrica', (int) $idRubrica)
            ->orderBy('orden')
            ->get()
            ->map(static fn (object $criterio): array => [
                'id_criterio' => (int) $criterio->id_criterio,
                'nivel' => 4,
            ])
            ->all();

        app(EvaluacionService::class)->guardar(
            $actor,
            $entrega,
            $criterios,
            'Corrección concurrente.',
            false,
            ['Observación de la corrección concurrente.'],
            (int) ($argv[5] ?? 1),
        );
    } elseif ($mode === 'config') {
        app(ConfiguracionService::class)->guardarCronograma(
            $actor,
            9902,
            ['2027-01-01T12:00', '2027-02-01T12:00', '2027-03-01T12:00'],
            1,
        );
    } elseif ($mode === 'survey_response') {
        $encuesta = Encuesta::query()->findOrFail((int) $argv[4]);
        app(EncuestaService::class)->responder(
            $actor,
            $encuesta,
            [[
                'id_pregunta' => (int) $argv[5],
                'valor' => 4,
            ]],
            'Respuesta concurrente.',
        );
    } elseif ($mode === 'survey_close') {
        $encuesta = Encuesta::query()->findOrFail((int) $argv[4]);
        app(EncuestaService::class)->cerrar(
            $actor,
            $encuesta,
            (int) $argv[5],
        );
    } else {
        throw new InvalidArgumentException('Modo de concurrencia desconocido.');
    }

    echo json_encode(['mutated' => true], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (AccesoDenegado|EstadoInvalido|RecursoBloqueado|VersionDesactualizada $error) {
    echo json_encode([
        'mutated' => false,
        'exception' => $error::class,
    ], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error::class.': '.$error->getMessage().PHP_EOL);
    exit(2);
}
