<?php

namespace Tests\Feature\InvestigacionFormativa;

use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Exceptions\VersionDesactualizada;
use Illuminate\Support\Facades\DB;
use Tests\Feature\InvestigacionFormativa\Concerns\CreaDatosInvestigacion;
use Tests\TestCase;

final class GrupoConcurrenciaTest extends TestCase
{
    use CreaDatosInvestigacion;

    private array $datos;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('RUN_CONCURRENCY_TESTS') !== '1') {
            $this->markTestSkipped('Requiere RUN_CONCURRENCY_TESTS=1 y una base PostgreSQL exclusiva.');
        }

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL.');
        }

        $this->assertStringContainsString(
            '_test_concurrency_',
            DB::connection()->getDatabaseName(),
            'La prueba de concurrencia exige una base PostgreSQL exclusiva.',
        );

        $this->datos = $this->crearDatosInvestigacion();
    }

    public function test_las_importaciones_y_las_mutaciones_respetan_el_bloqueo_de_seccion(): void
    {
        $this->crearGruposInvestigacion();
        $this->crearTipoProducto();
        $this->configurarSeccionCompleta(9901);
        $this->configurarSeccionCompleta(9902);
        $proyecto = DB::table('investigacion_formativa.proyecto')->insertGetId([
            'id_grupo' => 9951,
            'id_linea' => 9801,
            'id_tipo_producto' => 9981,
            'titulo' => 'Título anterior a la retirada',
            'estado' => 'Registrado',
            'version' => 1,
        ], 'id_proyecto');
        $csvRoster = $this->crearCsv(
            ['id_seccion', 'id_estudiante', 'estado', 'fuente', 'referencia_origen'],
            [[9901, 9901, 'retirado', 'concurrencia', 'corte-concurrente']],
        );
        $csvCompetencias = $this->crearCsv(
            ['id_seccion', 'codigo', 'descripcion', 'fuente', 'seleccionada'],
            [[9902, 'C1', 'Competencia concurrente', 'Sílabo concurrente', 1]],
        );

        try {
            $resultadosRoster = $this->ejecutarCarreraDeImportacionRoster($proyecto, $csvRoster);

            $this->assertTrue($resultadosRoster['roster']['mutated']);
            $this->assertFalse($resultadosRoster['titulo']['mutated']);
            $this->assertSame(
                AccesoDenegado::class,
                $resultadosRoster['titulo']['exception'] ?? null,
            );
            $this->assertSame(
                'Título anterior a la retirada',
                DB::table('investigacion_formativa.proyecto')
                    ->where('id_proyecto', $proyecto)
                    ->value('titulo'),
            );
            $this->assertSame(
                'retirado',
                DB::table('investigacion_formativa.participante_seccion')
                    ->where('id_seccion', 9901)
                    ->where('id_estudiante', 9901)
                    ->value('estado'),
            );

            $idEntrega = $this->crearEntregaEvaluable($proyecto);
            $idCriterio = (int) DB::table('investigacion_formativa.criterio')
                ->where('id_rubrica', DB::table('investigacion_formativa.seccion_configuracion')
                    ->where('id_seccion', 9901)
                    ->value('id_rubrica'))
                ->orderBy('orden')
                ->value('id_criterio');
            $idEvaluacion = DB::table('investigacion_formativa.evaluacion')->insertGetId([
                'id_entrega' => $idEntrega,
                'id_docente_evaluador' => 9801,
                'comentario' => 'Evaluación inicial.',
                'version' => 1,
                'sin_observaciones' => false,
            ], 'id_evaluacion');
            DB::table('investigacion_formativa.evaluacion_criterio')->insert([
                'id_evaluacion' => $idEvaluacion,
                'id_criterio' => $idCriterio,
                'nivel' => 3,
                'puntaje' => 3,
            ]);

            $resultadosGrupo = $this->ejecutarCarreraDeCreacion();

            $this->assertCount(2, $resultadosGrupo);
            $this->assertSame(1, count(array_filter(
                $resultadosGrupo,
                static fn (array $resultado): bool => $resultado['mutated'],
            )));
            $this->assertSame(1, count(array_filter(
                $resultadosGrupo,
                static fn (array $resultado): bool => ! $resultado['mutated'],
            )));
            $this->assertSame(1, DB::table('investigacion_formativa.grupo')
                ->where('id_seccion', 9902)
                ->where('nombre', 'Grupo concurrente')
                ->count());
            $this->assertSame(1, DB::table('investigacion_formativa.grupo_estudiante')
                ->where('id_seccion', 9902)
                ->where('id_estudiante', 9903)
                ->count());
            $this->assertSame(1, DB::table('investigacion_formativa.proyecto')
                ->where('titulo', 'Proyecto concurrente')
                ->count());

            $resultadosCompetencias = $this->ejecutarCarreraDeImportacionCompetencias($csvCompetencias);

            $this->assertTrue($resultadosCompetencias['importacion']['mutated']);
            $this->assertFalse($resultadosCompetencias['configuracion']['mutated']);
            $this->assertSame(
                VersionDesactualizada::class,
                $resultadosCompetencias['configuracion']['exception'] ?? null,
            );
            $this->assertSame(
                'Competencia concurrente',
                DB::table('investigacion_formativa.competencia_seccion')
                    ->where('id_seccion', 9902)
                    ->where('codigo', 'C1')
                    ->value('descripcion'),
            );
            $this->assertSame(2, (int) DB::table('investigacion_formativa.seccion_configuracion')
                ->where('id_seccion', 9902)
                ->value('version'));

            $resultadosEvaluacion = $this->ejecutarCarreraDeEvaluacion($idEntrega);

            $this->assertCount(2, $resultadosEvaluacion);
            $this->assertSame(1, count(array_filter(
                $resultadosEvaluacion,
                static fn (array $resultado): bool => $resultado['mutated'],
            )));
            $this->assertSame(1, count(array_filter(
                $resultadosEvaluacion,
                static fn (array $resultado): bool => ! $resultado['mutated'],
            )));
            $this->assertSame(
                VersionDesactualizada::class,
                $resultadosEvaluacion[0]['exception'] ?? $resultadosEvaluacion[1]['exception'] ?? null,
            );
            $this->assertSame(2, (int) DB::table('investigacion_formativa.evaluacion')
                ->where('id_entrega', $idEntrega)
                ->value('version'));
            $this->assertSame(1, DB::table('investigacion_formativa.observacion')
                ->where('id_entrega', $idEntrega)
                ->count());
        } finally {
            unlink($csvRoster);
            unlink($csvCompetencias);
        }
    }

    /**
     * @return array{roster: array{mutated: bool, exception?: string, exit_code?: int}, titulo: array{mutated: bool, exception?: string, exit_code?: int}}
     */
    private function ejecutarCarreraDeImportacionRoster(int $idProyecto, string $csv): array
    {
        $rosterProcess = null;
        $rosterPipes = [];
        $titleProcess = null;
        $titlePipes = [];

        DB::beginTransaction();

        try {
            DB::table('investigacion_formativa.seccion_configuracion')
                ->where('id_seccion', 9901)
                ->lockForUpdate()
                ->firstOrFail();

            [$rosterProcess, $rosterPipes] = $this->iniciarWorker('roster_import', 0, $csv);
            $this->esperarBloqueos('if_concurrency_roster_import', 1);

            [$titleProcess, $titlePipes] = $this->iniciarWorker(
                'title',
                $this->datos['users']['alumno_a']->id,
                (string) $idProyecto,
            );
            $this->esperarBloqueos('if_concurrency_title', 1);

            DB::commit();

            return [
                'roster' => $this->esperarProceso($rosterProcess, $rosterPipes),
                'titulo' => $this->esperarProceso($titleProcess, $titlePipes),
            ];
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $this->cerrarProceso($rosterProcess, $rosterPipes);
            $this->cerrarProceso($titleProcess, $titlePipes);
        }
    }

    /**
     * @return array{importacion: array{mutated: bool, exception?: string, exit_code?: int}, configuracion: array{mutated: bool, exception?: string, exit_code?: int}}
     */
    private function ejecutarCarreraDeImportacionCompetencias(string $csv): array
    {
        $importProcess = null;
        $importPipes = [];
        $configProcess = null;
        $configPipes = [];

        DB::beginTransaction();

        try {
            DB::table('investigacion_formativa.seccion_configuracion')
                ->where('id_seccion', 9902)
                ->lockForUpdate()
                ->firstOrFail();

            [$importProcess, $importPipes] = $this->iniciarWorker('competency_import', 0, $csv);
            $this->esperarBloqueos('if_concurrency_competency_import', 1);

            [$configProcess, $configPipes] = $this->iniciarWorker(
                'config',
                $this->datos['users']['docente_b']->id,
            );
            $this->esperarBloqueos('if_concurrency_config', 1);

            DB::commit();

            return [
                'importacion' => $this->esperarProceso($importProcess, $importPipes),
                'configuracion' => $this->esperarProceso($configProcess, $configPipes),
            ];
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $this->cerrarProceso($importProcess, $importPipes);
            $this->cerrarProceso($configProcess, $configPipes);
        }
    }

    /**
     * @return list<array{mutated: bool, exception?: string}>
     */
    private function ejecutarCarreraDeEvaluacion(int $idEntrega): array
    {
        $processes = [];
        $pipes = [];

        DB::beginTransaction();

        try {
            DB::table('investigacion_formativa.seccion_configuracion')
                ->where('id_seccion', 9901)
                ->lockForUpdate()
                ->firstOrFail();

            for ($indice = 0; $indice < 2; $indice++) {
                [$processes[$indice], $pipes[$indice]] = $this->iniciarWorker(
                    'evaluation',
                    $this->datos['users']['docente_a']->id,
                    (string) $idEntrega,
                    '1',
                );
            }

            $this->esperarBloqueos('if_concurrency_evaluation', 2);
            DB::commit();

            $resultados = [];

            foreach ($processes as $indice => $process) {
                $resultados[] = $this->esperarProceso($process, $pipes[$indice]);
            }

            return $resultados;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            foreach ($processes as $indice => $process) {
                $this->cerrarProceso($process, $pipes[$indice]);
            }
        }
    }

    /**
     * @return list<array{mutated: bool, exception?: string}>
     */
    private function ejecutarCarreraDeCreacion(): array
    {
        DB::table('investigacion_formativa.grupo_estudiante')
            ->where('id_grupo', 9952)
            ->delete();
        DB::table('investigacion_formativa.grupo')
            ->where('id_grupo', 9952)
            ->delete();

        $processes = [];
        $pipes = [];

        DB::beginTransaction();

        try {
            DB::table('investigacion_formativa.seccion_configuracion')
                ->where('id_seccion', 9902)
                ->lockForUpdate()
                ->firstOrFail();

            for ($indice = 0; $indice < 2; $indice++) {
                [$processes[$indice], $pipes[$indice]] = $this->iniciarWorker(
                    'group',
                    $this->datos['users']['alumno_otra_escuela']->id,
                );
            }

            $this->esperarBloqueos('if_concurrency_group', 2);
            DB::commit();

            $resultados = [];

            foreach ($processes as $indice => $process) {
                $resultados[] = $this->esperarProceso($process, $pipes[$indice]);
            }

            return $resultados;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            foreach ($processes as $indice => $process) {
                $this->cerrarProceso($process, $pipes[$indice]);
            }
        }
    }

    /**
     * @return array{0: resource, 1: array<int, resource>}
     */
    private function iniciarWorker(
        string $mode,
        int $idUsuario,
        ?string $argumentoExtra = null,
        ?string $argumentoAdicional = null,
    ): array {
        $argumentos = [
            PHP_BINARY,
            base_path('tests/Support/investigacion_concurrency_worker.php'),
            base_path(),
            $mode,
            (string) $idUsuario,
        ];

        if ($argumentoExtra !== null) {
            $argumentos[] = $argumentoExtra;
        }

        if ($argumentoAdicional !== null) {
            $argumentos[] = $argumentoAdicional;
        }

        $process = proc_open(
            $argumentos,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            base_path(),
        );

        $this->assertIsResource($process);
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        return [$process, $pipes];
    }

    private function esperarBloqueos(string $applicationName, int $cantidad): void
    {
        for ($intento = 0; $intento < 200; $intento++) {
            DB::select('SELECT pg_stat_clear_snapshot()');
            $bloqueados = DB::selectOne(
                "SELECT count(*) AS total
                FROM pg_stat_activity
                WHERE application_name = ?
                AND datname = current_database()
                AND wait_event_type = 'Lock'",
                [$applicationName],
            );

            if ((int) ($bloqueados->total ?? 0) >= $cantidad) {
                return;
            }

            usleep(50000);
        }

        $this->fail("No se alcanzó la barrera de bloqueo para {$applicationName}.");
    }

    /**
     * @param  resource  $process
     * @param  array<int, resource>  $pipes
     * @return array{mutated: bool, exception?: string}
     */
    private function esperarProceso($process, array $pipes): array
    {
        $salida = '';
        $errores = '';

        for ($intento = 0; $intento < 200; $intento++) {
            $salida .= stream_get_contents($pipes[1]);
            $errores .= stream_get_contents($pipes[2]);

            if (! proc_get_status($process)['running']) {
                break;
            }

            usleep(50000);
        }

        $salida .= stream_get_contents($pipes[1]);
        $errores .= stream_get_contents($pipes[2]);

        $this->assertFalse(
            proc_get_status($process)['running'],
            'El proceso de concurrencia no terminó: '.$errores,
        );

        $resultado = json_decode(trim($salida), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('mutated', $resultado);

        return $resultado;
    }

    /**
     * @param  resource|null  $process
     * @param  array<int, resource>  $pipes
     */
    private function cerrarProceso($process, array $pipes): void
    {
        if (is_resource($process) && proc_get_status($process)['running']) {
            proc_terminate($process);
        }

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        if (is_resource($process)) {
            proc_close($process);
        }
    }

    private function crearEntregaEvaluable(int $idProyecto): int
    {
        $idEntregable = DB::table('investigacion_formativa.entregable')
            ->where('id_seccion', 9901)
            ->orderBy('orden')
            ->value('id_entregable');
        $idModulo = DB::table('seguridad.modulo_sistema')
            ->where('codigo', 'investigacion_formativa')
            ->value('id_modulo');
        $idArchivo = DB::table('comun.archivo')->insertGetId([
            'id_modulo' => $idModulo,
            'nombre_original' => 'avance-concurrente.pdf',
            'ruta_almacenamiento' => 'investigacion-formativa/test/avance-concurrente.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 10,
            'hash_sha256' => str_repeat('c', 64),
            'subido_por' => 8802,
        ], 'id_archivo');

        return DB::table('investigacion_formativa.entrega')->insertGetId([
            'id_proyecto' => $idProyecto,
            'id_entregable' => (int) $idEntregable,
            'version' => 1,
            'id_archivo' => $idArchivo,
        ], 'id_entrega');
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<int|string>>  $rows
     */
    private function crearCsv(array $headers, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'if_concurrency_');
        $handle = fopen($path, 'wb');

        fputcsv($handle, $headers, ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }

        fclose($handle);

        return $path;
    }
}
