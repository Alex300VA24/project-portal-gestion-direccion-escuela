<?php

namespace Tests\Feature\InvestigacionFormativa;

use App\Models\User;
use App\Modules\InvestigacionFormativa\Database\Seeders\InvestigacionDemoSeeder;
use App\Modules\InvestigacionFormativa\Services\ConfiguracionService;
use App\Modules\InvestigacionFormativa\Services\DecisionTrabajoService;
use App\Modules\InvestigacionFormativa\Services\IndicadorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

final class DemoSeederTest extends TestCase
{
    use DatabaseTransactions;

    private string $directorioExportacion = '';

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql' || ! str_contains(DB::connection()->getDatabaseName(), '_test_demo_')) {
            $this->markTestSkipped('Requiere una base PostgreSQL exclusiva con _test_demo_ en su nombre.');
        }
        putenv('IF_DEMO_PASSWORD=OnlyForDisposableTests123');
        $this->directorioExportacion = sys_get_temp_dir().'/if-demo-export-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        putenv('IF_DEMO_PASSWORD');
        putenv('IF_DEMO_MODE');
        putenv('IF_DEMO_EXPORT_DIR');
        $this->eliminarDirectorio($this->directorioExportacion);
        parent::tearDown();
    }

    public function test_workflow_y_dos_lotes_showcase_crean_datos_completos_y_conservan_cuentas(): void
    {
        $existente = User::factory()->create();
        $password = $existente->password;
        $roles = $existente->getRoleNames()->all();

        $workflow = $this->sembrar('workflow', 'workflow');
        $this->assertSame('workflow', $workflow['modo']);
        $this->assertCount(2, $workflow['muestra']['grupos']);
        $this->assertSame(6, $workflow['muestra']['entregas']);
        $this->assertSame(6, $workflow['muestra']['evaluaciones']);
        $this->assertSame(3, $workflow['muestra']['respuestas']);
        $this->assertSame(0, $workflow['muestra']['aprobaciones']);
        $this->assertNull($workflow['muestra']['seleccion']);

        $this->assertLoteCompleto($workflow, 2, 6, 6, 3);
        $this->assertLoteTopologia($workflow);
        $this->assertManifestExport($workflow);

        $estadoWorkflow = app(DecisionTrabajoService::class)
            ->estadoDeSeccion((int) $workflow['secciones']['completa']);
        $this->assertSame(0, $estadoWorkflow->aprobados);
        $this->assertSame(0, $estadoWorkflow->destacados);
        $this->assertNull($estadoWorkflow->seleccion);

        $showcaseA = $this->sembrar('showcase', 'showcase-a');
        $showcaseB = $this->sembrar('showcase', 'showcase-b');

        foreach ([$showcaseA, $showcaseB] as $showcase) {
            $this->assertSame('showcase', $showcase['modo']);
            $this->assertCount(2, $showcase['muestra']['grupos']);
            $this->assertSame(6, $showcase['muestra']['entregas']);
            $this->assertSame(6, $showcase['muestra']['evaluaciones']);
            $this->assertSame(3, $showcase['muestra']['respuestas']);
            $this->assertSame(2, $showcase['muestra']['aprobaciones']);
            $this->assertNotNull($showcase['muestra']['seleccion']);

            $this->assertLoteCompleto($showcase, 2, 6, 6, 3);
            $this->assertLoteTopologia($showcase);
            $this->assertManifestExport($showcase);

            $estado = app(DecisionTrabajoService::class)
                ->estadoDeSeccion((int) $showcase['secciones']['completa']);
            $this->assertSame(2, $estado->aprobados);
            $this->assertSame(1, $estado->destacados);
            $this->assertNotNull($estado->seleccion);
            $this->assertFalse($estado->seleccion->desactualizada);
            $this->assertSame($showcase['muestra']['seleccion'], $estado->seleccion->id_proyecto);
            $this->assertSame(
                3,
                DB::table('investigacion_formativa.decision_trabajo')
                    ->where('id_seccion', (int) $showcase['secciones']['completa'])
                    ->count(),
            );
        }

        $this->assertNotSame($workflow['secciones']['completa'], $showcaseA['secciones']['completa']);
        $this->assertNotSame($showcaseA['secciones']['completa'], $showcaseB['secciones']['completa']);
        $this->assertNotSame(
            $showcaseA['muestra']['grupos']['Grupo Alfa']['id_grupo'],
            $showcaseB['muestra']['grupos']['Grupo Alfa']['id_grupo'],
        );
        $this->assertNotSame(
            $showcaseA['muestra']['grupos']['Grupo Alfa']['id_proyecto'],
            $showcaseB['muestra']['grupos']['Grupo Alfa']['id_proyecto'],
        );

        $estadoWorkflow = app(DecisionTrabajoService::class)
            ->estadoDeSeccion((int) $workflow['secciones']['completa']);
        $this->assertSame(0, $estadoWorkflow->aprobados);
        $this->assertSame(0, $estadoWorkflow->destacados);
        $this->assertNull($estadoWorkflow->seleccion);

        $this->assertSame($password, $existente->fresh()->password);
        $this->assertSame($roles, $existente->fresh()->getRoleNames()->all());
    }

    public function test_exige_un_modo_explicito_antes_de_crear_datos(): void
    {
        putenv('IF_DEMO_MODE');
        $antes = User::count();

        try {
            $this->seed(InvestigacionDemoSeeder::class);
            $this->fail('Se requiere un modo de demostración explícito.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('IF_DEMO_MODE', $exception->getMessage());
        }

        $this->assertSame($antes, User::count());
    }

    public function test_rechaza_un_modo_desconocido_antes_de_crear_datos(): void
    {
        putenv('IF_DEMO_MODE=otro');
        $antes = User::count();

        try {
            $this->seed(InvestigacionDemoSeeder::class);
            $this->fail('El modo de demostración debe ser workflow o showcase.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('IF_DEMO_MODE', $exception->getMessage());
        }

        $this->assertSame($antes, User::count());
    }

    public function test_solicita_una_contrasena_local_antes_de_crear_datos(): void
    {
        putenv('IF_DEMO_MODE=workflow');
        putenv('IF_DEMO_PASSWORD');
        $antes = User::count();

        try {
            $this->seed(InvestigacionDemoSeeder::class);
            $this->fail('Se requiere una contraseña de demostración.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('IF_DEMO_PASSWORD', $exception->getMessage());
        }

        $this->assertSame($antes, User::count());
    }

    /**
     * @return array<string, mixed>
     */
    private function sembrar(string $modo, string $nombre): array
    {
        $destino = $this->directorioExportacion.'/'.$nombre;
        putenv('IF_DEMO_MODE='.$modo);
        putenv('IF_DEMO_EXPORT_DIR='.$destino);
        $this->seed(InvestigacionDemoSeeder::class);

        $lotes = glob($destino.'/*', GLOB_ONLYDIR) ?: [];
        $this->assertCount(1, $lotes);

        $contenido = file_get_contents($lotes[0].'/demo.json');
        $this->assertIsString($contenido);
        $manifest = json_decode($contenido, true, flags: JSON_THROW_ON_ERROR);
        $manifest['_directorio'] = $lotes[0];

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function assertLoteCompleto(array $manifest, int $grupos, int $entregas, int $evaluaciones, int $respuestas): void
    {
        $seccion = (int) $manifest['secciones']['completa'];
        $lote = (string) $manifest['lote'];

        $this->assertSame($grupos, DB::table('investigacion_formativa.grupo')->where('id_seccion', $seccion)->count());
        $this->assertSame($entregas, DB::table('investigacion_formativa.entrega as e')
            ->join('investigacion_formativa.proyecto as p', 'p.id_proyecto', '=', 'e.id_proyecto')
            ->join('investigacion_formativa.grupo as g', 'g.id_grupo', '=', 'p.id_grupo')
            ->where('g.id_seccion', $seccion)
            ->count());
        $this->assertSame($evaluaciones, DB::table('investigacion_formativa.evaluacion as ev')
            ->join('investigacion_formativa.entrega as e', 'e.id_entrega', '=', 'ev.id_entrega')
            ->join('investigacion_formativa.proyecto as p', 'p.id_proyecto', '=', 'e.id_proyecto')
            ->join('investigacion_formativa.grupo as g', 'g.id_grupo', '=', 'p.id_grupo')
            ->where('g.id_seccion', $seccion)
            ->count());

        $encuesta = DB::table('investigacion_formativa.encuesta')
            ->where('id_seccion', $seccion)
            ->first();
        $this->assertNotNull($encuesta);
        $this->assertSame('publicada', $encuesta->estado);

        $idEncuesta = (int) $encuesta->id_encuesta;
        $this->assertSame($respuestas, DB::table('investigacion_formativa.encuesta_respuesta')
            ->where('id_encuesta', $idEncuesta)
            ->count());
        $this->assertSame($respuestas * 2, DB::table('investigacion_formativa.encuesta_respuesta_item')
            ->where('id_encuesta', $idEncuesta)
            ->count());

        $idUsuarioCoordinador = (int) DB::table('investigacion_formativa.identidad_usuario')
            ->where('user_id', (int) $this->cuenta($manifest, 'coordinador')['user_id'])
            ->value('id_usuario_seguridad');
        $idProyectoAlfa = (int) $manifest['muestra']['grupos']['Grupo Alfa']['id_proyecto'];

        $subidores = DB::table('comun.archivo')
            ->where('ruta_almacenamiento', 'like', 'investigacion-formativa/test/'.$lote.'/muestra-proyecto-'.$idProyectoAlfa.'-u%.pdf')
            ->pluck('subido_por')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $this->assertSame([$idUsuarioCoordinador], $subidores);

        $this->assertSame($entregas, DB::table('comun.archivo as a')
            ->join('seguridad.usuario as u', 'u.id_usuario', '=', 'a.subido_por')
            ->where('a.ruta_almacenamiento', 'like', 'investigacion-formativa/test/'.$lote.'/%')
            ->count());

        // El fixture de archivo no tiene binario almacenado: tamaño cero y sin hash.
        $this->assertSame(0, (int) DB::table('comun.archivo')
            ->where('ruta_almacenamiento', 'like', 'investigacion-formativa/test/'.$lote.'/%')
            ->max('tamano_bytes'));
        $this->assertNull(DB::table('comun.archivo')
            ->where('ruta_almacenamiento', 'like', 'investigacion-formativa/test/'.$lote.'/%')
            ->value('hash_sha256'));
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function assertLoteTopologia(array $manifest): void
    {
        $lote = (string) $manifest['lote'];
        $secciones = $manifest['secciones'];
        $seccion = (int) $secciones['completa'];

        $this->assertCount(6, $secciones);
        $this->assertSame(2, DB::table('core.escuela_profesional')
            ->where('codigo', 'like', 'IFD-'.$lote.'-%')
            ->count());
        $this->assertSame(6, DB::table('core.seccion')
            ->where('codigo', 'like', 'IFD-'.$lote.'-%')
            ->count());
        $this->assertSame(12, User::where('email', 'like', '%'.$lote.'@demo.example.test')->count());
        $this->assertSame('IFD-'.$lote.'-completa', DB::table('core.seccion')
            ->where('id_seccion', $seccion)
            ->value('codigo'));

        $this->assertTrue(app(ConfiguracionService::class)->estaConfigurada($seccion));
        $this->assertFalse(app(ConfiguracionService::class)->estaConfigurada((int) $secciones['incompleta']));
        $this->assertSame(1, DB::table('core.seccion')
            ->where('codigo', 'IFD-'.$lote.'-historica')
            ->where('estado', 'Cerrada')
            ->count());
        $this->assertSame(2, DB::table('investigacion_formativa.seccion_configuracion')
            ->whereIn('id_seccion', array_values($secciones))
            ->where('habilitada', false)
            ->count());
        $this->assertSame(3, DB::table('investigacion_formativa.participante_seccion')
            ->whereIn('id_seccion', array_values($secciones))
            ->where('estado', 'retirado')
            ->count());

        $planCompleta = (int) DB::table('core.seccion')->where('id_seccion', $seccion)->value('id_curso_plan');
        $planDeshabilitada = (int) DB::table('core.seccion')
            ->where('id_seccion', (int) $secciones['deshabilitada'])
            ->value('id_curso_plan');
        $this->assertNotSame($planCompleta, $planDeshabilitada);
        $this->assertSame(
            (int) DB::table('core.curso_plan')->where('id_curso_plan', $planCompleta)->value('id_plan'),
            (int) DB::table('core.curso_plan')->where('id_curso_plan', $planDeshabilitada)->value('id_plan'),
        );
        $this->assertSame(
            (int) DB::table('core.seccion')->where('id_seccion', $seccion)->value('id_periodo'),
            (int) DB::table('core.seccion')->where('id_seccion', (int) $secciones['deshabilitada'])->value('id_periodo'),
        );

        $idSeccionSinIf = (int) $secciones['sin-investigacion'];
        $this->assertSame(0, DB::table('investigacion_formativa.seccion_configuracion')
            ->where('id_seccion', $idSeccionSinIf)
            ->count());
        $this->assertSame(0, DB::table('investigacion_formativa.competencia_seccion')
            ->where('id_seccion', $idSeccionSinIf)
            ->count());

        $idsCursoPlan = DB::table('core.seccion')
            ->whereIn('id_seccion', array_values($secciones))
            ->distinct()
            ->pluck('id_curso_plan')
            ->all();
        $this->assertSame(4, DB::table('investigacion_formativa.indicador_experiencia')
            ->whereIn('id_curso_plan', $idsCursoPlan)
            ->count());
        $this->assertSame(2, DB::table('investigacion_formativa.configuracion_indicador')
            ->where('indicador', '3.2')
            ->count());
        $this->assertSame(2, DB::table('investigacion_formativa.configuracion_indicador')
            ->where('indicador', '3.3')
            ->count());
        $this->assertSame(1, DB::table('investigacion_formativa.indicador_competencia_criterio')
            ->where('id_competencia', DB::table('investigacion_formativa.competencia_seccion')
                ->where('id_seccion', $seccion)
                ->value('id_competencia'))
            ->count());

        $idEscuela = (int) DB::table('core.curso_plan as cp')
            ->join('core.plan_estudios as pe', 'pe.id_plan', '=', 'cp.id_plan')
            ->where('cp.id_curso_plan', $planCompleta)
            ->value('pe.id_escuela');
        $idPeriodo = (int) DB::table('core.seccion')->where('id_seccion', $seccion)->value('id_periodo');
        $periodo = (string) DB::table('core.periodo_academico')
            ->where('id_periodo', $idPeriodo)
            ->value('codigo');
        $indicadores = app(IndicadorService::class)->indicadoresDeAlcance(
            [$idEscuela],
            $periodo,
            null,
            [$seccion],
        );

        $this->assertSame(1, $indicadores->experiencia_curricular->numerador);
        $this->assertSame(2, $indicadores->experiencia_curricular->denominador);
        $this->assertSame(50.0, $indicadores->experiencia_curricular->valor);
        $this->assertSame(2, $indicadores->satisfaccion->numerador);
        $this->assertSame(3, $indicadores->satisfaccion->denominador);
        $this->assertSame(66.67, $indicadores->satisfaccion->valor);
        $this->assertTrue($indicadores->satisfaccion->provisional);
        $this->assertSame(2, $indicadores->logro_competencias->numerador);
        $this->assertSame(3, $indicadores->logro_competencias->denominador);
        $this->assertSame(66.67, $indicadores->logro_competencias->valor);
        $this->assertSame(100.0, $indicadores->logro_competencias->cobertura);
        $this->assertTrue($indicadores->logro_competencias->provisional);
        $this->assertCount(1, $indicadores->logro_competencias->competencias);

        $multi = User::where('email', 'multirrol.'.$lote.'@demo.example.test')->firstOrFail();
        $this->assertTrue($multi->hasAllRoles(['profesor', 'alumno']));
        $this->assertTrue(Hash::check('OnlyForDisposableTests123', $multi->password));

        $secretaria = User::where('email', 'secretaria-a.'.$lote.'@demo.example.test')->firstOrFail();
        $this->assertTrue($secretaria->hasRole('secretaria'));
        $this->assertSame(1, DB::table('investigacion_formativa.secretaria_escuela')
            ->where('user_id', $secretaria->id)
            ->where('activo', true)
            ->count());

        $tipos = DB::table('investigacion_formativa.proyecto as p')
            ->join('investigacion_formativa.grupo as g', 'g.id_grupo', '=', 'p.id_grupo')
            ->join('investigacion_formativa.tipo_producto as tp', 'tp.id_tipo_producto', '=', 'p.id_tipo_producto')
            ->where('g.id_seccion', $seccion)
            ->distinct()
            ->pluck('tp.nombre')
            ->all();
        $this->assertSame(['Informe de demostración '.$lote], $tipos);

        $lineas = DB::table('investigacion_formativa.proyecto as p')
            ->join('investigacion_formativa.grupo as g', 'g.id_grupo', '=', 'p.id_grupo')
            ->join('core.linea_investigacion as l', 'l.id_linea', '=', 'p.id_linea')
            ->where('g.id_seccion', $seccion)
            ->distinct()
            ->pluck('l.codigo')
            ->all();
        $this->assertSame(['IFD-'.$lote.'-A'], $lineas);
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function assertManifestExport(array $manifest): void
    {
        $this->assertSame(2, $manifest['version']);
        $this->assertCount(12, $manifest['cuentas']);
        $this->assertStringNotContainsString(
            'OnlyForDisposableTests123',
            (string) file_get_contents($manifest['_directorio'].'/demo.json'),
        );

        foreach (['identidades', 'directores', 'secretarias', 'participantes'] as $nombre) {
            $this->artisan('investigacion:importar-'.$nombre, [
                'csv' => $manifest['_directorio'].'/'.$nombre.'.csv',
                '--dry-run' => true,
            ])->assertExitCode(0);
        }

        // La sección completa ya tiene entregas, por diseño. El comando debe
        // rechazar el reemplazo de competencias en vez de escribir parcialmente.
        $codigo = Artisan::call('investigacion:importar-competencias', [
            'csv' => $manifest['_directorio'].'/competencias.csv',
            '--dry-run' => true,
        ]);
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('ya tiene entregas', Artisan::output());

        // El formato del CSV se valida en una sección aún configurable.
        $filas = file($manifest['_directorio'].'/competencias.csv', FILE_IGNORE_NEW_LINES);
        $this->assertIsArray($filas);
        $cabecera = array_shift($filas);
        $this->assertNotNull($cabecera);
        $seccionIncompleta = (string) $manifest['secciones']['incompleta'];
        $configurables = array_values(array_filter(
            $filas,
            static function (string $fila) use ($seccionIncompleta): bool {
                $columnas = str_getcsv($fila, ',', '"', '');

                return ($columnas[0] ?? '') === $seccionIncompleta;
            },
        ));
        $this->assertNotSame([], $configurables);

        $temporal = $manifest['_directorio'].'/competencias-configurables.csv';
        file_put_contents($temporal, $cabecera."\n".implode("\n", $configurables)."\n");
        $this->artisan('investigacion:importar-competencias', [
            'csv' => $temporal,
            '--dry-run' => true,
        ])->assertExitCode(0);
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function cuenta(array $manifest, string $nombre): array
    {
        foreach ($manifest['cuentas'] as $cuenta) {
            if ($cuenta['nombre'] === $nombre) {
                return $cuenta;
            }
        }

        $this->fail('No se encontró la cuenta de demostración '.$nombre);
    }

    private function eliminarDirectorio(string $directorio): void
    {
        if ($directorio === '' || ! is_dir($directorio)) {
            return;
        }

        $elementos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directorio, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($elementos as $elemento) {
            if ($elemento->isDir()) {
                rmdir($elemento->getPathname());
            } else {
                unlink($elemento->getPathname());
            }
        }

        rmdir($directorio);
    }
}
