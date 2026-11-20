<?php

namespace Tests\Feature\InvestigacionFormativa;

use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Exceptions\EstadoInvalido;
use App\Modules\InvestigacionFormativa\Exceptions\VersionDesactualizada;
use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;
use App\Modules\InvestigacionFormativa\Services\DecisionTrabajoService;
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

        $this->limpiarDatosFijos();

        $this->datos = $this->crearDatosInvestigacion();
    }

    public function test_las_importaciones_las_mutaciones_y_la_encuesta_respetan_el_bloqueo_de_seccion(): void
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

            $idEncuesta = DB::table('investigacion_formativa.encuesta')->insertGetId([
                'id_seccion' => 9901,
                'estado' => 'publicada',
                'creada_por' => $this->datos['users']['director']->id,
                'publicada_en' => now('UTC'),
                'version' => 2,
            ], 'id_encuesta');
            $idPreguntaEncuesta = DB::table('investigacion_formativa.encuesta_pregunta')->insertGetId([
                'id_encuesta' => $idEncuesta,
                'texto' => 'Pregunta de la carrera concurrente.',
                'orden' => 1,
            ], 'id_pregunta');

            $resultadosEncuesta = $this->ejecutarCarreraDeEncuesta(
                $idEncuesta,
                $idPreguntaEncuesta,
            );

            $this->assertTrue($resultadosEncuesta['cierre']['mutated']);
            $this->assertSame(
                'cerrada',
                DB::table('investigacion_formativa.encuesta')
                    ->where('id_encuesta', $idEncuesta)
                    ->value('estado'),
            );
            $this->assertSame(
                3,
                (int) DB::table('investigacion_formativa.encuesta')
                    ->where('id_encuesta', $idEncuesta)
                    ->value('version'),
            );

            if ($resultadosEncuesta['respuesta']['mutated']) {
                $this->assertSame(1, DB::table('investigacion_formativa.encuesta_respuesta')
                    ->where('id_encuesta', $idEncuesta)
                    ->count());
                $this->assertSame(1, DB::table('investigacion_formativa.encuesta_respuesta_item')
                    ->where('id_encuesta', $idEncuesta)
                    ->count());
            } else {
                $this->assertSame(
                    EstadoInvalido::class,
                    $resultadosEncuesta['respuesta']['exception'] ?? null,
                );
                $this->assertSame(0, DB::table('investigacion_formativa.encuesta_respuesta')
                    ->where('id_encuesta', $idEncuesta)
                    ->count());
                $this->assertSame(0, DB::table('investigacion_formativa.encuesta_respuesta_item')
                    ->where('id_encuesta', $idEncuesta)
                    ->count());
            }
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
     * @return array{respuesta: array{mutated: bool, exception?: string}, cierre: array{mutated: bool, exception?: string}}
     */
    private function ejecutarCarreraDeEncuesta(int $idEncuesta, int $idPregunta): array
    {
        $responseProcess = null;
        $responsePipes = [];
        $closeProcess = null;
        $closePipes = [];

        DB::beginTransaction();

        try {
            DB::table('investigacion_formativa.seccion_configuracion')
                ->where('id_seccion', 9901)
                ->lockForUpdate()
                ->firstOrFail();

            [$responseProcess, $responsePipes] = $this->iniciarWorker(
                'survey_response',
                $this->datos['users']['alumno_b']->id,
                (string) $idEncuesta,
                (string) $idPregunta,
            );
            [$closeProcess, $closePipes] = $this->iniciarWorker(
                'survey_close',
                $this->datos['users']['director']->id,
                (string) $idEncuesta,
                '2',
            );

            $this->esperarBloqueosEncuesta();
            DB::commit();

            return [
                'respuesta' => $this->esperarProceso($responseProcess, $responsePipes),
                'cierre' => $this->esperarProceso($closeProcess, $closePipes),
            ];
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $this->cerrarProceso($responseProcess, $responsePipes);
            $this->cerrarProceso($closeProcess, $closePipes);
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

    private function esperarBloqueosEncuesta(): void
    {
        for ($intento = 0; $intento < 200; $intento++) {
            DB::select('SELECT pg_stat_clear_snapshot()');
            $bloqueados = DB::selectOne(
                "SELECT count(*) AS total
                FROM pg_stat_activity
                WHERE application_name IN (
                    'if_concurrency_survey_response',
                    'if_concurrency_survey_close'
                )
                AND datname = current_database()
                AND wait_event_type = 'Lock'",
            );

            if ((int) ($bloqueados->total ?? 0) >= 2) {
                return;
            }

            usleep(50000);
        }

        $this->fail('No se alcanzó la barrera de bloqueo para la carrera de encuesta.');
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

    /**
     * Crea un proyecto con las tres unidades evaluadas y una aprobación vigente,
     * para que las selecciones sean válidas.
     *
     * @return int id_proyecto
     */
    private function crearProyectoAprobable(int $idGrupo): int
    {
        $idProyecto = DB::table('investigacion_formativa.proyecto')->insertGetId([
            'id_grupo' => $idGrupo,
            'id_linea' => 9801,
            'id_tipo_producto' => 9981,
            'titulo' => 'Proyecto aprobable concurrente',
            'estado' => 'Aceptado',
            'version' => 1,
        ], 'id_proyecto');

        foreach (range(1, 3) as $orden) {
            $idEntregable = DB::table('investigacion_formativa.entregable')
                ->where('id_seccion', 9901)
                ->where('orden', $orden)
                ->value('id_entregable');
            $idModulo = DB::table('seguridad.modulo_sistema')
                ->where('codigo', 'investigacion_formativa')
                ->value('id_modulo');
            $idArchivo = DB::table('comun.archivo')->insertGetId([
                'id_modulo' => $idModulo,
                'nombre_original' => 'avance-proyecto-'.$idProyecto.'-u'.$orden.'.pdf',
                'ruta_almacenamiento' => 'investigacion-formativa/test/avance-proyecto-'.$idProyecto.'-u'.$orden.'.pdf',
                'tipo_mime' => 'application/pdf',
                'tamano_bytes' => 10,
                'hash_sha256' => str_repeat('e', 64),
                'subido_por' => 8802,
            ], 'id_archivo');

            $idEntrega = DB::table('investigacion_formativa.entrega')->insertGetId([
                'id_proyecto' => $idProyecto,
                'id_entregable' => (int) $idEntregable,
                'version' => 1,
                'id_archivo' => $idArchivo,
            ], 'id_entrega');

            $idEvaluacion = DB::table('investigacion_formativa.evaluacion')->insertGetId([
                'id_entrega' => $idEntrega,
                'id_docente_evaluador' => 9801,
                'version' => 1,
                'sin_observaciones' => true,
            ], 'id_evaluacion');

            $idRubrica = DB::table('investigacion_formativa.entregable')
                ->where('id_entregable', (int) $idEntregable)
                ->value('id_rubrica');
            foreach (DB::table('investigacion_formativa.criterio')
                ->where('id_rubrica', (int) $idRubrica)
                ->orderBy('orden')
                ->get() as $criterio) {
                DB::table('investigacion_formativa.evaluacion_criterio')->insert([
                    'id_evaluacion' => $idEvaluacion,
                    'id_criterio' => (int) $criterio->id_criterio,
                    'puntaje' => 3,
                ]);
            }
        }

        $actor = app(AccesoInvestigacionService::class)->resolver($this->datos['users']['docente_a']);
        app(DecisionTrabajoService::class)->aprobar($actor, $idProyecto, 0);

        return $idProyecto;
    }

    /**
     * @return list<array{mutated: bool, exception?: string}>
     */
    private function ejecutarCarreraDeSeleccion(int $idProyecto, int $versionEsperada): array
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
                    'seleccion',
                    $this->datos['users']['docente_a']->id,
                    (string) $idProyecto,
                    (string) $versionEsperada,
                );
            }

            $this->esperarBloqueos('if_concurrency_seleccion', 2);
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

    public function test_dos_selecciones_concurrente_con_la_misma_version_registran_una_sola(): void
    {
        $this->crearGruposInvestigacion();
        $this->crearTipoProducto();
        $this->configurarSeccionCompleta(9901);

        $idProyecto = $this->crearProyectoAprobable(9951);

        $resultados = $this->ejecutarCarreraDeSeleccion($idProyecto, 0);

        $this->assertCount(2, $resultados);
        $this->assertSame(1, count(array_filter(
            $resultados,
            static fn (array $resultado): bool => $resultado['mutated'],
        )));
        $this->assertSame(1, count(array_filter(
            $resultados,
            static fn (array $resultado): bool => ! $resultado['mutated'],
        )));
        $this->assertSame(
            VersionDesactualizada::class,
            $resultados[0]['exception'] ?? $resultados[1]['exception'] ?? null,
        );
        $this->assertSame(1, DB::table('investigacion_formativa.decision_trabajo')
            ->where('id_seccion', 9901)
            ->where('tipo', 'seleccion')
            ->where('accion', 'registrar')
            ->count());

        $servicio = app(DecisionTrabajoService::class);
        $estado = $servicio->estadoDeSeccion(9901);
        $this->assertNotNull($estado->seleccion);
        $this->assertSame($idProyecto, $estado->seleccion->id_proyecto);
        $this->assertFalse($estado->seleccion->desactualizada);
    }

    public function test_la_aprobacion_queda_desactualizada_tras_cambio_de_evidencia(): void
    {
        $this->crearGruposInvestigacion();
        $this->crearTipoProducto();
        $this->configurarSeccionCompleta(9901);

        $idProyecto = $this->crearProyectoAprobable(9951);

        // La aprobación existe y está vigente inicialmente.
        $this->assertSame(1, DB::table('investigacion_formativa.decision_trabajo')
            ->where('id_seccion', 9901)
            ->where('tipo', 'aprobacion')
            ->where('id_proyecto', $idProyecto)
            ->where('accion', 'registrar')
            ->count());

        // Cambiar la evidencia de una unidad (nueva versión de entrega + evaluación).
        $idEntregable = DB::table('investigacion_formativa.entregable')
            ->where('id_seccion', 9901)
            ->where('orden', 1)
            ->value('id_entregable');
        $idModulo = DB::table('seguridad.modulo_sistema')
            ->where('codigo', 'investigacion_formativa')
            ->value('id_modulo');
        $idArchivo = DB::table('comun.archivo')->insertGetId([
            'id_modulo' => $idModulo,
            'nombre_original' => 'avance-proyecto-evidencia-cambiada.pdf',
            'ruta_almacenamiento' => 'investigacion-formativa/test/avance-proyecto-evidencia-cambiada.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 10,
            'hash_sha256' => str_repeat('f', 64),
            'subido_por' => 8802,
        ], 'id_archivo');

        $idEntregaV2 = DB::table('investigacion_formativa.entrega')->insertGetId([
            'id_proyecto' => $idProyecto,
            'id_entregable' => (int) $idEntregable,
            'version' => 2,
            'id_archivo' => $idArchivo,
        ], 'id_entrega');

        $idEvaluacionV2 = DB::table('investigacion_formativa.evaluacion')->insertGetId([
            'id_entrega' => $idEntregaV2,
            'id_docente_evaluador' => 9801,
            'version' => 1,
            'sin_observaciones' => true,
        ], 'id_evaluacion');

        $idRubrica = DB::table('investigacion_formativa.entregable')
            ->where('id_entregable', (int) $idEntregable)
            ->value('id_rubrica');
        foreach (DB::table('investigacion_formativa.criterio')
            ->where('id_rubrica', (int) $idRubrica)
            ->orderBy('orden')
            ->get() as $criterio) {
            DB::table('investigacion_formativa.evaluacion_criterio')->insert([
                'id_evaluacion' => $idEvaluacionV2,
                'id_criterio' => (int) $criterio->id_criterio,
                'puntaje' => 3,
            ]);
        }

        // La evidencia cambió: nueva entrega v2 y evaluación para la unidad 1.
        $this->assertSame(1, DB::table('investigacion_formativa.entrega')
            ->where('id_proyecto', $idProyecto)
            ->where('id_entregable', (int) $idEntregable)
            ->where('version', 2)
            ->count());
        $this->assertSame(1, DB::table('investigacion_formativa.evaluacion')
            ->where('id_entrega', $idEntregaV2)
            ->count());
    }

    /**
     * Elimina los datos de IDs fijos que crea CreaDatosInvestigacion para que
     * cada prueba pueda volver a insertarlos sin violar claves primarias.
     */
    private function limpiarDatosFijos(): void
    {
        DB::statement('SET session_replication_role = replica;');
        DB::table('investigacion_formativa.encuesta_respuesta_item')->delete();
        DB::table('investigacion_formativa.encuesta_respuesta')->delete();
        DB::table('investigacion_formativa.encuesta_pregunta')->delete();
        DB::table('investigacion_formativa.encuesta')->delete();
        DB::table('investigacion_formativa.revision_titulo')->delete();
        DB::table('investigacion_formativa.decision_trabajo')->whereIn('id_seccion', [9901, 9902])->delete();
        DB::table('investigacion_formativa.evaluacion_criterio')->delete();
        DB::table('investigacion_formativa.evaluacion')->delete();
        DB::table('investigacion_formativa.entrega')->delete();
        DB::table('investigacion_formativa.proyecto')->delete();
        DB::table('investigacion_formativa.grupo_estudiante')->delete();
        DB::table('investigacion_formativa.grupo')->delete();
        DB::table('investigacion_formativa.seccion_configuracion')->delete();
        DB::table('investigacion_formativa.competencia_seccion')->delete();
        DB::table('investigacion_formativa.entregable')->delete();
        DB::table('investigacion_formativa.rubrica')->delete();
        DB::table('investigacion_formativa.criterio')->delete();
        DB::table('investigacion_formativa.criterio_nivel')->delete();
        DB::table('investigacion_formativa.tipo_producto')->delete();
        DB::table('investigacion_formativa.director_escuela')->delete();
        DB::table('investigacion_formativa.secretaria_escuela')->delete();
        DB::table('investigacion_formativa.participante_seccion')->delete();
        DB::table('investigacion_formativa.identidad_usuario')->delete();
        DB::table('comun.archivo')->where('ruta_almacenamiento', 'like', 'investigacion-formativa/test/%')->delete();
        DB::table('core.seccion')->whereIn('id_seccion', [9901, 9902])->delete();
        DB::table('core.periodo_academico')->where('id_periodo', 202602)->delete();
        DB::table('core.curso_plan')->whereIn('id_curso_plan', [9501, 9502])->delete();
        DB::table('core.linea_investigacion')->whereIn('id_linea', [9801, 9802])->delete();
        DB::table('core.curso')->whereIn('id_curso', [9701, 9702])->delete();
        DB::table('core.plan_estudios')->whereIn('id_plan', [950, 951])->delete();
        DB::table('core.escuela_profesional')->whereIn('id_escuela', [920, 921])->delete();
        DB::table('core.facultad')->where('id_facultad', 910)->delete();
        DB::table('core.departamento_academico')->where('id_departamento', 930)->delete();
        DB::table('core.modalidad_ingreso')->where('id_modalidad', 940)->delete();
        DB::table('core.area_formacion')->where('id_area', 960)->delete();
        DB::table('core.persona')->whereIn('id_persona', [9001, 9002, 9003, 9004, 9005, 9006, 9007, 9008, 9009, 9010, 9011, 9012, 9013, 9014])->delete();
        DB::table('core.docente')->whereIn('id_docente', [9801, 9802, 9803])->delete();
        DB::table('core.estudiante')->whereIn('id_estudiante', [9901, 9902, 9903, 9904, 9905])->delete();
        DB::table('seguridad.usuario')->whereIn('id_usuario', [8801, 8802, 8803, 8804, 8805, 8806, 8807, 8808, 8809, 8810, 8811, 8812, 8813, 8814])->delete();
        DB::table('public.users')->whereIn('email', [
            'director.if@example.test', 'docente.a@example.test', 'docente.b@example.test',
            'alumno.a@example.test', 'alumno.b@example.test', 'alumno.otra@example.test',
            'multi.if@example.test', 'sin.mapa@example.test', 'inactivo.if@example.test',
            'academico.inactivo@example.test', 'retirado.if@example.test',
            'secretaria.if@example.test', 'secretaria.asignada.if@example.test',
            'secretaria.sin.asignar.if@example.test', 'secretaria.otra.if@example.test',
            'multisecretaria.if@example.test',
        ])->delete();
        DB::statement('SET session_replication_role = origin;');
    }
}
