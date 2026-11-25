<?php

namespace Tests\Feature\GestionHorarios;

use Tests\Support\GestionHorarios\Engine\EnsambladorAcademicoPrueba;
use Tests\Support\GestionHorarios\Engine\PoliticaReintentoPrueba;
use Tests\Support\GestionHorarios\Engine\ProteccionPropuestaPrueba;
use Tests\Support\GestionHorarios\Engine\ReconciliarIntentosPrueba;
use Tests\Support\GestionHorarios\Engine\RecuperarEjecucionPrueba;
use Tests\Support\GestionHorarios\Engine\TrazabilidadEjecucionPrueba;
use Tests\Support\GestionHorarios\Engine\ValidarResultadoFicticio;
use App\Modules\GestionHorarios\Services\InstantaneaGeneracion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Feature\GestionHorarios\soporte\CrearHorarioFicticio;
use Tests\Feature\GestionHorarios\soporte\EjecutarPreparacionColaFicticia;
use Tests\Feature\GestionHorarios\soporte\InterrupcionOrtoolsJob;
use Tests\Feature\GestionHorarios\soporte\ProveedorComplementosPostgresFicticio;
use Tests\TestCase;

require_once __DIR__.'/soporte/ProveedorComplementosPostgresFicticio.php';
require_once __DIR__.'/soporte/EjecutarPreparacionColaFicticia.php';
require_once __DIR__.'/soporte/InterrupcionOrtoolsJob.php';
require_once __DIR__.'/soporte/CrearHorarioFicticio.php';

final class ColaPreparacionPostgresTest extends TestCase
{
    public function test_cola_real_reserva_una_vez_y_persiste_resultado_validado(): void
    {
        if (getenv('HORARIOS_27B2_POSTGRES_AISLADO') !== '1') {
            $this->markTestSkipped('Requiere PostgreSQL aislado.');
        }
        $clave = getenv('HORARIOS_27B2_PASSWORD');
        $python = getenv('HORARIOS_PYTHON_AISLADO');
        if (! is_string($clave) || $clave === '' || ! is_string($python) || ! is_file($python)) {
            throw new RuntimeException('Falta entorno aislado.');
        }
        config(['database.default' => 'gh_27b2', 'database.connections.gh_27b2' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55471, 'database' => 'horarios_27b2_test', 'username' => 'horarios_test', 'password' => $clave, 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public'], 'queue.connections.database.connection' => 'gh_27b2', 'queue.connections.database.retry_after' => 240]);
        $db = DB::connection();
        $identidad = $db->selectOne("select current_database() as base,current_user as usuario,inet_server_port() as puerto,current_setting('server_version_num') as version");
        if ($identidad->base !== 'horarios_27b2_test' || $identidad->usuario !== 'horarios_test' || (int) $identidad->puerto !== 5432 || (int) $identidad->version < 170000 || (int) $identidad->version >= 180000 || $db->selectOne("select exists(select 1 from pg_namespace where nspname in ('core','gestion_horarios','prueba')) as existe")->existe) {
            throw new RuntimeException('Base de prueba vacía no verificada.');
        }
        $db->unprepared(ltrim(file_get_contents(__DIR__.'/soporte/instantanea_27b2.sql'), "\xEF\xBB\xBF"));
        $db->unprepared('CREATE TABLE prueba.propuesta_cpsat (id uuid PRIMARY KEY, solicitud_sha256 text NOT NULL UNIQUE, academica_sha256 text NOT NULL, periodo bigint NOT NULL, escuela text NOT NULL, planes jsonb NOT NULL, versiones jsonb NOT NULL, estado_solver text NOT NULL, sesiones jsonb NOT NULL, alcance_validacion text NOT NULL, captura_finalizada_en timestamptz NOT NULL, validada_en timestamptz NOT NULL)');
        // JSONB reordena claves; preservar los bytes para verificar la huella de entrada.
        $db->unprepared('CREATE TABLE prueba.solicitud_cola (id uuid PRIMARY KEY, estado text NOT NULL, intento uuid, generacion bigint NOT NULL DEFAULT 0, lease_hasta timestamptz, job_id bigint, preparada text NOT NULL, entrada_sha256 text NOT NULL, academica_sha256 text NOT NULL, solver text, tiempo_aplicado int, tiempo_busqueda double precision, resultado jsonb, diagnostico text)');
        $db->unprepared('CREATE TABLE prueba.intento_cola (id uuid PRIMARY KEY, solicitud_id uuid NOT NULL REFERENCES prueba.solicitud_cola(id), generacion bigint NOT NULL, estado text NOT NULL, worker_pid int, worker_creacion text, lanzador_pid int, python_pid int, python_padre int, python_creacion text, entrada_sha256 text NOT NULL, academica_sha256 text NOT NULL, reclamado_en timestamptz NOT NULL, python_iniciado_en timestamptz, python_finalizado_en timestamptz, renovado_en timestamptz, renovaciones int NOT NULL DEFAULT 0, lease_hasta timestamptz NOT NULL, finalizado_en timestamptz, presupuesto int, solver text, resultado jsonb, evidencia jsonb, validacion text, diagnostico text, UNIQUE(solicitud_id, generacion))');
        $db->unprepared("CREATE UNIQUE INDEX intento_cola_unico_vigente ON prueba.intento_cola(solicitud_id) WHERE estado='EJECUTANDO'");
        (require base_path('database/migrations/0001_01_01_000002_create_jobs_table.php'))->up();
        $proveedor = new ProveedorComplementosPostgresFicticio($db);
        $preparada = (new EnsambladorAcademicoPrueba($db, new ProveedorComplementosPostgresFicticio($db, 7)))->construir((new InstantaneaGeneracion($db))->construir(1), '1');
        self::assertTrue($preparada['ejecutable']);
        Livewire::test(CrearHorarioFicticio::class)->set('tiempo', 151)->call('comprobar')->assertHasErrors(['tiempo']);
        $obsoleta = Livewire::test(CrearHorarioFicticio::class)->set('tiempo', 5)->call('comprobar')->assertHasNoErrors();
        $db->table('gestion_horarios.asignacion_docente')->where('id_asignacion', 71)->update(['id_docente' => 42]);
        $obsoleta->call('solicitar')->assertHasErrors(['preparacion']);
        self::assertSame(0, $db->table('prueba.solicitud_cola')->count());
        $db->table('gestion_horarios.asignacion_docente')->where('id_asignacion', 71)->update(['id_docente' => 41]);
        $interfaz = Livewire::test(CrearHorarioFicticio::class)
            ->set('tiempo', $preparada['entrada']['tiempo_limite'])
            ->call('comprobar')->assertHasNoErrors()
            ->call('solicitar')->assertHasNoErrors()->assertSee('En cola');
        $id = $interfaz->get('solicitud');
        self::assertNotEmpty($id);
        $db->transaction(function () use ($id): void {
            Queue::connection('database')->pushOn('gh-27b5-prueba', new EjecutarPreparacionColaFicticia($id));
        });
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '55471', 'DB_DATABASE' => 'horarios_27b2_test', 'DB_USERNAME' => 'horarios_test', 'DB_PASSWORD' => $clave, 'DB_URL' => '', 'DB_QUEUE_CONNECTION' => 'pgsql', 'DB_QUEUE_RETRY_AFTER' => '240', 'HORARIOS_PYTHON_AISLADO' => $python];
        $cmd = [PHP_BINARY, 'artisan', 'queue:work', 'database', '--once', '--queue=gh-27b5-prueba', '--timeout=190', '--tries=1', '--no-interaction'];
        $a = new Process($cmd, base_path(), $env, null, 220);
        $b = new Process($cmd, base_path(), $env, null, 220);
        $db->beginTransaction();
        try {
            $db->table('prueba.solicitud_cola')->where('id', $id)->lockForUpdate()->first();
            $a->start();
            $b->start();
            $limite = microtime(true) + 20;
            do {
                $db->select('select pg_stat_clear_snapshot()');
                $numero = (int) $db->selectOne("select count(*) as n from pg_stat_activity where datname=current_database() and usename=current_user and wait_event_type='Lock' and query like 'UPDATE prueba.solicitud_cola SET estado%'")->n;
                if ($numero >= 2) {
                    break;
                }
                usleep(100000);
            } while (microtime(true) < $limite);
            self::assertGreaterThanOrEqual(2, $numero, 'Ambos workers alcanzaron la reserva.');
        } finally {
            $db->rollBack();
        }
        $a->wait();
        $b->wait();
        self::assertTrue($a->isSuccessful(), $a->getErrorOutput());
        self::assertTrue($b->isSuccessful(), $b->getErrorOutput());
        $fila = $db->table('prueba.solicitud_cola')->where('id', $id)->first();
        self::assertSame('FINALIZADO', $fila->estado, (string) $fila->diagnostico);
        self::assertNotEmpty($fila->intento);
        self::assertSame('OPTIMAL', $fila->solver);
        $interfaz->call('$refresh')->assertSee('Solución óptima encontrada')->assertSee('Propuesta de prueba');
        self::assertSame(7, $fila->tiempo_aplicado);
        self::assertSame(5, $db->table('prueba.meta')->where('periodo', '1')->value('tiempo_limite'));
        self::assertSame($preparada['entrada']['tiempo_limite'], $fila->tiempo_aplicado);
        self::assertSame($preparada['huella_sha256'], $fila->entrada_sha256);
        $respuesta = json_decode($fila->resultado, true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((new ValidarResultadoFicticio)->validar($preparada['entrada'], $respuesta)['solucion_validada']);
        $consecutivas = $preparada['entrada'];
        foreach ($consecutivas['disponibilidades'] as &$disponibilidad) {
            if ($disponibilidad['docente_id'] === '41') {
                $disponibilidad['franjas'] = [['dia' => 'LUNES', 'inicio' => 9, 'fin' => 10], ['dia' => 'LUNES', 'inicio' => 10, 'fin' => 11]];
            }
        }
        unset($disponibilidad);
        self::assertTrue((new ValidarResultadoFicticio)->validar($consecutivas, $respuesta)['solucion_validada']);
        foreach ($consecutivas['disponibilidades'] as &$disponibilidad) {
            if ($disponibilidad['docente_id'] === '41') {
                $disponibilidad['franjas'] = [['dia' => 'LUNES', 'inicio' => 9, 'fin' => 10]];
            }
        }
        unset($disponibilidad);
        try {
            (new ValidarResultadoFicticio)->validar($consecutivas, $respuesta);
            self::fail('Una interrupción de cobertura debe rechazarse.');
        } catch (\DomainException $e) {
            self::assertSame('Sesión fuera de disponibilidad.', $e->getMessage());
        }
        $historiaInicial = $db->table('prueba.intento_cola')->where('id', $fila->intento)->first();
        $evidenciaInicial = json_decode($historiaInicial->evidencia, true, 512, JSON_THROW_ON_ERROR);
        $trazabilidad = new TrazabilidadEjecucionPrueba;
        self::assertTrue($trazabilidad->coincide($evidenciaInicial, $id, $fila->intento, (int) $historiaInicial->generacion, $preparada, $respuesta, (int) $historiaInicial->worker_pid, (int) $historiaInicial->lanzador_pid));
        foreach (['solicitud', 'intento', 'generacion', 'huella_entrada', 'huella_academica', 'presupuesto', 'motor', 'resultado_sha256', 'worker_pid'] as $campoAlterado) {
            $evidenciaAlterada = $evidenciaInicial;
            $evidenciaAlterada[$campoAlterado] = 'ALTERADO';
            self::assertFalse($trazabilidad->coincide($evidenciaAlterada, $id, $fila->intento, (int) $historiaInicial->generacion, $preparada, $respuesta, (int) $historiaInicial->worker_pid, (int) $historiaInicial->lanzador_pid));
        }
        $resultadoAlterado = $respuesta;
        array_pop($resultadoAlterado['sesiones']);
        self::assertFalse($trazabilidad->coincide($evidenciaInicial, $id, $fila->intento, (int) $historiaInicial->generacion, $preparada, $resultadoAlterado, (int) $historiaInicial->worker_pid, (int) $historiaInicial->lanzador_pid));
        self::assertFalse($trazabilidad->coincide($evidenciaInicial, $id, (string) Str::uuid(), (int) $historiaInicial->generacion, $preparada, $respuesta, (int) $historiaInicial->worker_pid, (int) $historiaInicial->lanzador_pid));
        $politica = new PoliticaReintentoPrueba;
        self::assertSame('NO_REINTENTAR', $politica->evaluar('INFEASIBLE'));
        self::assertSame('NO_REINTENTAR', $politica->evaluar('MODEL_INVALID'));
        self::assertSame('NO_REINTENTAR', $politica->evaluar('INPUT_INVALID'));
        self::assertSame('REVISION_MANUAL', $politica->evaluar('UNKNOWN'));
        self::assertSame('REQUIERE_TERMINACION_VERIFICADA', $politica->evaluar(null));
        self::assertCount(1, array_filter($respuesta['sesiones'], fn ($s) => ($s['sesion_fisica_id'] ?? null) === 'F61'));
        self::assertSame(1, $db->table('prueba.propuesta_cpsat')->count());
        self::assertSame(0, $db->table('jobs')->where('queue', 'gh-27b5-prueba')->count());
        $proteccion = new ProteccionPropuestaPrueba($db, new ProveedorComplementosPostgresFicticio($db, 7));
        self::assertSame('INTENTO_DESACTUALIZADO', $proteccion->aceptar($preparada, $respuesta, fn () => false)['estado']);
        $proteccion = new ProteccionPropuestaPrueba($db, $proveedor);
        self::assertSame(1, $db->table('prueba.propuesta_cpsat')->count());

        $db->table('prueba.meta')->where('periodo', '1')->update(['tiempo_limite' => 4]);
        $otraPreparacion = (new EnsambladorAcademicoPrueba($db, $proveedor))->construir((new InstantaneaGeneracion($db))->construir(1), '1');
        self::assertTrue($otraPreparacion['ejecutable']);
        self::assertSame($preparada['huella_academica_sha256'], $otraPreparacion['huella_academica_sha256']);
        self::assertNotSame($preparada['huella_sha256'], $otraPreparacion['huella_sha256']);
        $otraId = (string) Str::uuid();
        $db->table('prueba.solicitud_cola')->insert(['id' => $otraId, 'estado' => 'EN_COLA', 'preparada' => json_encode($otraPreparacion, JSON_THROW_ON_ERROR), 'entrada_sha256' => $otraPreparacion['huella_sha256'], 'academica_sha256' => $otraPreparacion['huella_academica_sha256']]);
        Queue::connection('database')->pushOn('gh-27b5-prueba', new EjecutarPreparacionColaFicticia($otraId));
        $c = new Process($cmd, base_path(), $env, null, 220);
        $c->mustRun();
        $otraFila = $db->table('prueba.solicitud_cola')->where('id', $otraId)->first();
        self::assertSame('FINALIZADO', $otraFila->estado, (string) $otraFila->diagnostico);
        self::assertSame(4, $otraFila->tiempo_aplicado);
        self::assertNotSame($fila->intento, $otraFila->intento);
        self::assertSame(2, $db->table('prueba.propuesta_cpsat')->count());

        $desactualizada = (string) Str::uuid();
        $db->table('prueba.solicitud_cola')->insert(['id' => $desactualizada, 'estado' => 'EN_COLA', 'preparada' => json_encode($otraPreparacion, JSON_THROW_ON_ERROR), 'entrada_sha256' => $otraPreparacion['huella_sha256'], 'academica_sha256' => $otraPreparacion['huella_academica_sha256']]);
        Queue::connection('database')->pushOn('gh-27b5-prueba', new EjecutarPreparacionColaFicticia($desactualizada));
        $db->table('gestion_horarios.asignacion_docente')->where('id_asignacion', 71)->update(['id_docente' => 42]);
        $d = new Process($cmd, base_path(), $env, null, 220);
        $d->mustRun();
        $rechazada = $db->table('prueba.solicitud_cola')->where('id', $desactualizada)->first();
        self::assertSame('RECHAZADO', $rechazada->estado);
        self::assertContains($rechazada->diagnostico, ['CAMBIO_DETECTADO', 'FUENTE_NO_VERIFICABLE']);
        self::assertSame(2, $db->table('prueba.propuesta_cpsat')->count());

        // Interrupción real antes de que este worker pueda reclamar el intento académico.
        $db->table('gestion_horarios.asignacion_docente')->where('id_asignacion', 71)->update(['id_docente' => 41]);
        $interrumpida = (string) Str::uuid();
        $db->table('prueba.solicitud_cola')->insert(['id' => $interrumpida, 'estado' => 'EN_COLA', 'preparada' => json_encode($otraPreparacion, JSON_THROW_ON_ERROR), 'entrada_sha256' => $otraPreparacion['huella_sha256'], 'academica_sha256' => $otraPreparacion['huella_academica_sha256']]);
        $jobInterrumpido = Queue::connection('database')->pushOn('gh-27b5-prueba', new EjecutarPreparacionColaFicticia($interrumpida));
        $db->table('prueba.solicitud_cola')->where('id', $interrumpida)->update(['job_id' => $jobInterrumpido]);
        $victima = new Process($cmd, base_path(), $env, null, 220);
        $db->beginTransaction();
        try {
            $db->table('prueba.solicitud_cola')->where('id', $interrumpida)->lockForUpdate()->first();
            $victima->start();
            $pid = $victima->getPid();
            $limite = microtime(true) + 20;
            do {
                $db->select('select pg_stat_clear_snapshot()');
                $bloqueados = (int) $db->selectOne("select count(*) as n from pg_stat_activity where datname=current_database() and usename=current_user and wait_event_type='Lock' and query like 'UPDATE prueba.solicitud_cola SET estado%'")->n;
                if ($bloqueados >= 1) {
                    break;
                }
                usleep(100000);
            } while (microtime(true) < $limite);
            self::assertGreaterThan(0, $bloqueados);
            self::assertGreaterThan(0, $pid);
            // En Windows getPid() puede ser cmd.exe: identificar el PHP hijo exacto.
            $script = '$p=@(Get-CimInstance Win32_Process -Filter "ProcessId = '.$pid.' OR ParentProcessId = '.$pid.'" | Where-Object { $_.Name -eq "php.exe" -and $_.CommandLine -like "*gh-27b5-prueba*" }); if ($p.Count -ne 1) { exit 2 }; Stop-Process -Id $p[0].ProcessId -Force; Write-Output $p[0].ProcessId';
            $terminar = new Process(['powershell.exe', '-NoProfile', '-Command', $script], base_path());
            $terminar->mustRun();
            self::assertGreaterThan(0, (int) trim($terminar->getOutput()));
            $victima->wait();
            self::assertFalse($victima->isRunning());
        } finally {
            if ($victima->isRunning()) {
                $victima->stop(0);
            }
            $db->rollBack();
        }
        // Una sentencia ya enviada puede confirmarse tras terminar el cliente.
        $estadoInterrumpido = $db->table('prueba.solicitud_cola')->where('id', $interrumpida)->value('estado');
        self::assertContains($estadoInterrumpido, ['EN_COLA', 'EJECUTANDO']);
        self::assertNotNull($db->table('jobs')->where('queue', 'gh-27b5-prueba')->value('reserved_at'));
        $reconciliador = new ReconciliarIntentosPrueba($db);
        $diagnostico = $reconciliador->consultar($interrumpida);
        self::assertSame('RESERVADO', $diagnostico['mensaje']);
        if ($estadoInterrumpido === 'EJECUTANDO') {
            self::assertSame('VIGENTE_SIN_PRUEBA_DE_VIDA', $diagnostico['lease']);
            self::assertFalse($reconciliador->cercarExpirado($interrumpida, $diagnostico['intento'], $diagnostico['generacion']));
            // Avance de reloj simulado solo en la fila ficticia; no prueba muerte del proceso.
            $db->table('prueba.solicitud_cola')->where('id', $interrumpida)->update(['lease_hasta' => DB::raw("clock_timestamp()-interval '1 second'")]);
            self::assertSame('EXPIRADO_POSIBLE_ABANDONO', $reconciliador->consultar($interrumpida)['lease']);
            self::assertTrue($reconciliador->cercarExpirado($interrumpida, $diagnostico['intento'], $diagnostico['generacion']));
            self::assertFalse($reconciliador->cercarExpirado($interrumpida, $diagnostico['intento'], $diagnostico['generacion']));
            self::assertSame('REQUIERE_REVISION', $reconciliador->consultar($interrumpida)['estado']);
            self::assertSame(0, $db->table('prueba.solicitud_cola')->where('id', $interrumpida)->where('estado', 'EJECUTANDO')->where('intento', $diagnostico['intento'])->where('generacion', $diagnostico['generacion'])->update(['estado' => 'FINALIZADO']));
        }
        $simulada = (string) Str::uuid();
        $intentoSimulado = (string) Str::uuid();
        $db->table('prueba.solicitud_cola')->insert(['id' => $simulada, 'estado' => 'EJECUTANDO', 'intento' => $intentoSimulado, 'generacion' => 1, 'lease_hasta' => DB::raw("clock_timestamp()-interval '1 second'"), 'preparada' => json_encode($otraPreparacion, JSON_THROW_ON_ERROR), 'entrada_sha256' => $otraPreparacion['huella_sha256'], 'academica_sha256' => $otraPreparacion['huella_academica_sha256']]);
        $parametros = json_encode(['id' => $simulada, 'intento' => $intentoSimulado, 'generacion' => 1], JSON_THROW_ON_ERROR);
        $pa = new Process([PHP_BINARY, __DIR__.'/soporte/reconciliar_27b6.php'], base_path(), $env, $parametros, 30);
        $pb = new Process([PHP_BINARY, __DIR__.'/soporte/reconciliar_27b6.php'], base_path(), $env, $parametros, 30);
        $db->beginTransaction();
        try {
            $db->table('prueba.solicitud_cola')->where('id', $simulada)->lockForUpdate()->first();
            $pa->start();
            $pb->start();
            $limite = microtime(true) + 20;
            do {
                $db->select('select pg_stat_clear_snapshot()');
                $esperando = (int) $db->selectOne("select count(*) as n from pg_stat_activity where datname=current_database() and usename=current_user and wait_event_type='Lock' and query like 'update prueba.solicitud_cola set estado=%'")->n;
                if ($esperando >= 2) {
                    break;
                }
                usleep(100000);
            } while (microtime(true) < $limite);
            self::assertGreaterThanOrEqual(2, $esperando);
        } finally {
            $db->rollBack();
        }
        $pa->wait();
        $pb->wait();
        self::assertTrue($pa->isSuccessful(), $pa->getErrorOutput());
        self::assertTrue($pb->isSuccessful(), $pb->getErrorOutput());
        $resultados = [json_decode($pa->getOutput(), true, 512, JSON_THROW_ON_ERROR), json_decode($pb->getOutput(), true, 512, JSON_THROW_ON_ERROR)];
        self::assertCount(1, array_filter($resultados));
        self::assertSame(2, $reconciliador->consultar($simulada)['generacion']);
        self::assertSame('REQUIERE_REVISION', $reconciliador->consultar($simulada)['estado']);
        $duranteId = (string) Str::uuid();
        $token = Str::uuid()->toString();
        $marca = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gh-27b6-'.$token.'.json';
        $db->table('prueba.solicitud_cola')->insert(['id' => $duranteId, 'estado' => 'EN_COLA', 'preparada' => json_encode($otraPreparacion, JSON_THROW_ON_ERROR), 'entrada_sha256' => $otraPreparacion['huella_sha256'], 'academica_sha256' => $otraPreparacion['huella_academica_sha256']]);
        $jobDurante = Queue::connection('database')->pushOn('gh-27b5-prueba', new InterrupcionOrtoolsJob($duranteId, $marca, $token));
        $db->table('prueba.solicitud_cola')->where('id', $duranteId)->update(['job_id' => $jobDurante]);
        $workerDurante = new Process($cmd, base_path(), $env, null, 220);
        $workerDurante->start();
        $pidPython = null;
        try {
            $limite = microtime(true) + 30;
            while (! is_file($marca) && microtime(true) < $limite && $workerDurante->isRunning()) {
                usleep(100000);
            }
            self::assertFileExists($marca, 'CP-SAT debe alcanzar su callback real de solución.');
            $evento = json_decode(file_get_contents($marca), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('callback_cp_sat', $evento['fase']);
            self::assertSame($token, $evento['token']);
            $pidPython = (int) $evento['pid'];
            self::assertGreaterThan(0, $pidPython);
            $pidWorker = $workerDurante->getPid();
            $script = '$p=@(Get-CimInstance Win32_Process -Filter "ProcessId = '.$pidWorker.' OR ParentProcessId = '.$pidWorker.'" | Where-Object { $_.Name -eq "php.exe" -and $_.CommandLine -like "*gh-27b5-prueba*" }); if ($p.Count -ne 1) { exit 2 }; Stop-Process -Id $p[0].ProcessId -Force';
            (new Process(['powershell.exe', '-NoProfile', '-Command', $script], base_path()))->mustRun();
            $observar = '$p=Get-CimInstance Win32_Process -Filter "ProcessId = '.$pidPython.'"; if ($p -and $p.CommandLine -like "*'.$token.'*") { Write-Output "ACTIVO" } else { Write-Output "NO_ACTIVO" }';
            $observado = new Process(['powershell.exe', '-NoProfile', '-Command', $observar], base_path());
            $observado->mustRun();
            $estadoPython = trim($observado->getOutput());
            self::assertContains($estadoPython, ['ACTIVO', 'NO_ACTIVO']);
            fwrite(STDERR, "\nInterrupción CP-SAT: Python {$estadoPython}\n");
            $filaDurante = $reconciliador->consultar($duranteId);
            self::assertSame('EJECUTANDO', $filaDurante['estado']);
            self::assertSame('RESERVADO', $filaDurante['mensaje']);
            $db->table('prueba.solicitud_cola')->where('id', $duranteId)->update(['lease_hasta' => DB::raw("clock_timestamp()-interval '1 second'")]);
            self::assertTrue($reconciliador->cercarExpirado($duranteId, $filaDurante['intento'], $filaDurante['generacion']));
            self::assertSame(0, $db->table('prueba.solicitud_cola')->where('id', $duranteId)->where('estado', 'EJECUTANDO')->where('intento', $filaDurante['intento'])->where('generacion', $filaDurante['generacion'])->update(['estado' => 'FINALIZADO']));
            // Nuevo intento simulado: no es una política de recuperación automática.
            $nuevoIntento = (string) Str::uuid();
            $db->table('prueba.solicitud_cola')->where('id', $duranteId)->where('estado', 'REQUIERE_REVISION')->update(['estado' => 'EJECUTANDO', 'intento' => $nuevoIntento, 'generacion' => DB::raw('generacion+1'), 'lease_hasta' => DB::raw("clock_timestamp()+interval '210 seconds'")]);
            $solucionValida = json_decode($otraFila->resultado, true, 512, JSON_THROW_ON_ERROR);
            $tardio = $proteccion->aceptar($otraPreparacion, $solucionValida, fn () => $db->table('prueba.solicitud_cola')->where('id', $duranteId)->where('estado', 'EJECUTANDO')->where('intento', $filaDurante['intento'])->where('generacion', $filaDurante['generacion'])->whereRaw('lease_hasta > clock_timestamp()')->update(['estado' => 'FINALIZADO']) === 1);
            self::assertSame('INTENTO_DESACTUALIZADO', $tardio['estado']);
            self::assertSame($nuevoIntento, $db->table('prueba.solicitud_cola')->where('id', $duranteId)->value('intento'));
            self::assertSame(2, $db->table('prueba.propuesta_cpsat')->count());
        } finally {
            // Solo procesos del escenario identificados por su token único y comando exacto.
            $limpiarArbol = '$p=@(Get-CimInstance Win32_Process | Where-Object { $_.Name -eq "python.exe" -and $_.CommandLine -like "*solver_interrumpido_27b6.py*" -and $_.CommandLine -like "*'.$token.'*" }); foreach ($h in $p) { Stop-Process -Id $h.ProcessId -Force -ErrorAction SilentlyContinue }';
            (new Process(['powershell.exe', '-NoProfile', '-Command', $limpiarArbol], base_path()))->run();
            if ($pidPython !== null) {
                $limpiar = '$p=Get-CimInstance Win32_Process -Filter "ProcessId = '.$pidPython.'"; if ($p -and $p.CommandLine -like "*'.$token.'*") { Stop-Process -Id $p.ProcessId -Force }';
                (new Process(['powershell.exe', '-NoProfile', '-Command', $limpiar], base_path()))->run();
            }
            if ($workerDurante->isRunning()) {
                $workerDurante->stop(0);
            }
            if (is_file($marca)) {
                unlink($marca);
            }
        }
        // Reintento real: el primer worker muere después del retorno de Python.
        $db->table('prueba.meta')->where('periodo', '1')->update(['tiempo_limite' => 3]);
        $preparacionRetry = (new EnsambladorAcademicoPrueba($db, $proveedor))->construir((new InstantaneaGeneracion($db))->construir(1), '1');
        self::assertTrue($preparacionRetry['ejecutable']);
        $retryId = (string) Str::uuid();
        $db->table('prueba.solicitud_cola')->insert(['id' => $retryId, 'estado' => 'EN_COLA', 'preparada' => json_encode($preparacionRetry, JSON_THROW_ON_ERROR), 'entrada_sha256' => $preparacionRetry['huella_sha256'], 'academica_sha256' => $preparacionRetry['huella_academica_sha256']]);
        $retryJob = Queue::connection('database')->pushOn('gh-27b5-prueba', new EjecutarPreparacionColaFicticia($retryId));
        $db->table('prueba.solicitud_cola')->where('id', $retryId)->update(['job_id' => $retryJob]);
        $envPausa = array_merge($env, ['HORARIOS_27B7_PAUSA_TRAS_PYTHON' => '1']);
        $workerPausado = new Process($cmd, base_path(), $envPausa, null, 220);
        $workerPausado->start();
        try {
            $limite = microtime(true) + 40;
            do {
                $primerIntento = $db->table('prueba.intento_cola')->where('solicitud_id', $retryId)->first();
                if ($primerIntento && $primerIntento->python_finalizado_en) {
                    break;
                }
                usleep(100000);
            } while (microtime(true) < $limite && $workerPausado->isRunning());
            self::assertNotNull($primerIntento?->python_finalizado_en, 'Python real debe terminar antes de interrumpir el worker.');
            self::assertNotNull($primerIntento->renovado_en);
            $pidPausa = $workerPausado->getPid();
            $matar = '$p=@(Get-CimInstance Win32_Process -Filter "ProcessId = '.$pidPausa.' OR ParentProcessId = '.$pidPausa.'" | Where-Object { $_.Name -eq "php.exe" -and $_.CommandLine -like "*gh-27b5-prueba*" }); if ($p.Count -ne 1) { exit 2 }; Stop-Process -Id $p[0].ProcessId -Force';
            (new Process(['powershell.exe', '-NoProfile', '-Command', $matar], base_path()))->mustRun();
            $workerPausado->wait();
            self::assertFalse($workerPausado->isRunning());
        } finally {
            if ($workerPausado->isRunning()) {
                $workerPausado->stop(0);
            }
        }
        $db->table('prueba.solicitud_cola')->where('id', $retryId)->update(['lease_hasta' => DB::raw("clock_timestamp()-interval '1 second'")]);
        self::assertTrue($reconciliador->cercarExpirado($retryId, $primerIntento->id, (int) $primerIntento->generacion));
        self::assertFalse($reconciliador->renovar($retryId, $primerIntento->id, (int) $primerIntento->generacion));
        $recuperar = new RecuperarEjecucionPrueba($db, $proveedor);
        self::assertSame('PROCESO_NO_VERIFICADO', $recuperar->recuperar($retryId, new EjecutarPreparacionColaFicticia($retryId), fn () => false));
        self::assertSame('LIMITE_INTENTOS', $recuperar->recuperar($retryId, new EjecutarPreparacionColaFicticia($retryId), fn () => true, 1));
        $db->table('gestion_horarios.oferta_curso')->where('id_oferta', 11)->update(['estado' => 'PLANIFICADA']);
        self::assertSame('PREPARACION_OBSOLETA_O_NO_VERIFICABLE', $recuperar->recuperar($retryId, new EjecutarPreparacionColaFicticia($retryId), fn () => true));
        $db->table('gestion_horarios.oferta_curso')->where('id_oferta', 11)->update(['estado' => 'OFERTADA']);
        $recuperadorA = new Process([PHP_BINARY, __DIR__.'/soporte/recuperar_27b7.php'], base_path(), $env, $retryId, 30);
        $recuperadorB = new Process([PHP_BINARY, __DIR__.'/soporte/recuperar_27b7.php'], base_path(), $env, $retryId, 30);
        $db->beginTransaction();
        try {
            $db->table('prueba.solicitud_cola')->where('id', $retryId)->lockForUpdate()->first();
            $recuperadorA->start();
            $recuperadorB->start();
            $limite = microtime(true) + 20;
            do {
                $db->select('select pg_stat_clear_snapshot()');
                $bloqueadoRecuperar = (int) $db->selectOne("select count(*) as n from pg_stat_activity where datname=current_database() and usename=current_user and wait_event_type='Lock' and query like '%solicitud_cola%for update%'")->n;
                if ($bloqueadoRecuperar > 0) {
                    break;
                }
                usleep(100000);
            } while (microtime(true) < $limite);
            self::assertGreaterThan(0, $bloqueadoRecuperar);
        } finally {
            $db->rollBack();
        }
        $recuperadorA->wait();
        $recuperadorB->wait();
        self::assertTrue($recuperadorA->isSuccessful(), $recuperadorA->getErrorOutput());
        self::assertTrue($recuperadorB->isSuccessful(), $recuperadorB->getErrorOutput());
        $respuestasRetry = [trim($recuperadorA->getOutput()), trim($recuperadorB->getOutput())];
        self::assertSame(1, count(array_filter($respuestasRetry, fn ($r) => $r === 'REENCOLADA')));
        self::assertCount(1, array_filter($respuestasRetry, fn ($r) => in_array($r, ['PROCESO_EN_CURSO', 'ESTADO_NO_RECUPERABLE'], true)));
        self::assertSame('ESTADO_NO_RECUPERABLE', $recuperar->recuperar($retryId, new EjecutarPreparacionColaFicticia($retryId), fn () => true));
        $senalRetry = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gh-27b7-'.Str::uuid().'.ready';
        $workerRetry = new Process($cmd, base_path(), array_merge($envPausa, ['HORARIOS_27B7_LIBERAR' => $senalRetry]), null, 220);
        $workerRetry->start();
        try {
            $limite = microtime(true) + 40;
            do {
                $segundoIntento = $db->table('prueba.intento_cola')->where('solicitud_id', $retryId)->where('id', '<>', $primerIntento->id)->first();
                if ($segundoIntento && $segundoIntento->python_finalizado_en) {
                    break;
                }
                usleep(100000);
            } while (microtime(true) < $limite && $workerRetry->isRunning());
            self::assertNotNull($segundoIntento?->python_finalizado_en);
            $resultadoAnterior = json_decode($db->table('prueba.intento_cola')->where('id', $primerIntento->id)->value('resultado'), true, 512, JSON_THROW_ON_ERROR);
            $intentoTardio = $proteccion->aceptar($preparacionRetry, $resultadoAnterior, fn () => $db->table('prueba.solicitud_cola')->where('id', $retryId)->where('estado', 'EJECUTANDO')->where('intento', $primerIntento->id)->where('generacion', $primerIntento->generacion)->update(['estado' => 'FINALIZADO']) === 1);
            self::assertSame('INTENTO_DESACTUALIZADO', $intentoTardio['estado']);
            self::assertSame(0, $db->table('prueba.propuesta_cpsat')->where('solicitud_sha256', $preparacionRetry['huella_sha256'])->count());
            file_put_contents($senalRetry, 'ok');
            $workerRetry->wait();
            self::assertTrue($workerRetry->isSuccessful(), $workerRetry->getErrorOutput());
        } finally {
            if ($workerRetry->isRunning()) {
                file_put_contents($senalRetry, 'ok');
                $workerRetry->wait();
            }
            if (is_file($senalRetry)) {
                unlink($senalRetry);
            }
        }
        $historial = $db->table('prueba.intento_cola')->where('solicitud_id', $retryId)->orderBy('generacion')->get();
        self::assertCount(2, $historial);
        self::assertSame('CERCADO', $historial[0]->estado);
        self::assertSame('FINALIZADO', $historial[1]->estado);
        self::assertNotSame($historial[0]->id, $historial[1]->id);
        self::assertGreaterThan($historial[0]->generacion, $historial[1]->generacion);
        self::assertSame('FINALIZADO', $db->table('prueba.solicitud_cola')->where('id', $retryId)->value('estado'));
        self::assertSame(1, $db->table('prueba.propuesta_cpsat')->where('solicitud_sha256', $preparacionRetry['huella_sha256'])->count());
        self::assertSame('PROPUESTA_YA_EXISTENTE', $recuperar->recuperar($retryId, new EjecutarPreparacionColaFicticia($retryId), fn () => true));
        $respuestaNueva = json_decode($db->table('prueba.solicitud_cola')->where('id', $retryId)->value('resultado'), true, 512, JSON_THROW_ON_ERROR);
        $salidaTardia = $proteccion->aceptar($preparacionRetry, $respuestaNueva, fn () => $db->table('prueba.solicitud_cola')->where('id', $retryId)->where('estado', 'EJECUTANDO')->where('intento', $primerIntento->id)->where('generacion', $primerIntento->generacion)->update(['estado' => 'FINALIZADO']) === 1);
        self::assertSame('INTENTO_DESACTUALIZADO', $salidaTardia['estado']);
        self::assertSame('FINALIZADO', $db->table('prueba.solicitud_cola')->where('id', $retryId)->value('estado'));
        // Interrumpir tras commit y antes del ACK de Laravel; reentregar el mismo mensaje.
        $db->table('prueba.meta')->where('periodo', '1')->update(['tiempo_limite' => 2]);
        $preparacionCommit = (new EnsambladorAcademicoPrueba($db, $proveedor))->construir((new InstantaneaGeneracion($db))->construir(1), '1');
        self::assertTrue($preparacionCommit['ejecutable']);
        $commitId = (string) Str::uuid();
        $db->table('prueba.solicitud_cola')->insert(['id' => $commitId, 'estado' => 'EN_COLA', 'preparada' => json_encode($preparacionCommit, JSON_THROW_ON_ERROR), 'entrada_sha256' => $preparacionCommit['huella_sha256'], 'academica_sha256' => $preparacionCommit['huella_academica_sha256']]);
        $commitJob = Queue::connection('database')->pushOn('gh-27b5-prueba', new EjecutarPreparacionColaFicticia($commitId));
        $workerCommit = new Process($cmd, base_path(), array_merge($env, ['HORARIOS_27B7_PAUSA_TRAS_COMMIT' => '1']), null, 220);
        $workerCommit->start();
        try {
            $limite = microtime(true) + 40;
            do {
                $estadoCommit = $db->table('prueba.solicitud_cola')->where('id', $commitId)->value('estado');
                if ($estadoCommit === 'FINALIZADO') {
                    break;
                }
                usleep(100000);
            } while (microtime(true) < $limite && $workerCommit->isRunning());
            self::assertSame('FINALIZADO', $estadoCommit);
            self::assertSame(1, $db->table('prueba.propuesta_cpsat')->where('solicitud_sha256', $preparacionCommit['huella_sha256'])->count());
            self::assertNotNull($db->table('jobs')->where('id', $commitJob)->value('reserved_at'));
            $pidCommit = $workerCommit->getPid();
            $matarCommit = '$p=@(Get-CimInstance Win32_Process -Filter "ProcessId = '.$pidCommit.' OR ParentProcessId = '.$pidCommit.'" | Where-Object { $_.Name -eq "php.exe" -and $_.CommandLine -like "*gh-27b5-prueba*" }); if ($p.Count -ne 1) { exit 2 }; Stop-Process -Id $p[0].ProcessId -Force';
            (new Process(['powershell.exe', '-NoProfile', '-Command', $matarCommit], base_path()))->mustRun();
            $workerCommit->wait();
        } finally {
            if ($workerCommit->isRunning()) {
                $workerCommit->stop(0);
            }
        }
        // Simula el transcurso de retry_after solo en el mensaje exclusivo de prueba.
        $db->table('jobs')->where('id', $commitJob)->update(['reserved_at' => time() - 241]);
        $reentrega = new Process($cmd, base_path(), $env, null, 220);
        $reentrega->mustRun();
        self::assertSame(0, $db->table('jobs')->where('id', $commitJob)->count());
        self::assertSame(1, $db->table('prueba.intento_cola')->where('solicitud_id', $commitId)->count());
        self::assertSame(1, $db->table('prueba.propuesta_cpsat')->where('solicitud_sha256', $preparacionCommit['huella_sha256'])->count());
        self::assertSame('FINALIZADO', $db->table('prueba.solicitud_cola')->where('id', $commitId)->value('estado'));
        // Trigger temporal solo en la base ficticia para detener la inserción antes del commit.
        $db->table('prueba.meta')->where('periodo', '1')->update(['tiempo_limite' => 1]);
        $preparacionRollback = (new EnsambladorAcademicoPrueba($db, $proveedor))->construir((new InstantaneaGeneracion($db))->construir(1), '1');
        self::assertTrue($preparacionRollback['ejecutable']);
        $hashRollback = $preparacionRollback['huella_sha256'];
        $db->unprepared("CREATE FUNCTION prueba.pausar_insercion_27b7() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF NEW.solicitud_sha256 = '$hashRollback' THEN PERFORM pg_sleep(8); END IF; RETURN NEW; END \$\$");
        $db->unprepared('CREATE TRIGGER pausa_insercion_27b7 AFTER INSERT ON prueba.propuesta_cpsat FOR EACH ROW EXECUTE FUNCTION prueba.pausar_insercion_27b7()');
        $rollbackId = (string) Str::uuid();
        $db->table('prueba.solicitud_cola')->insert(['id' => $rollbackId, 'estado' => 'EN_COLA', 'preparada' => json_encode($preparacionRollback, JSON_THROW_ON_ERROR), 'entrada_sha256' => $hashRollback, 'academica_sha256' => $preparacionRollback['huella_academica_sha256']]);
        Queue::connection('database')->pushOn('gh-27b5-prueba', new EjecutarPreparacionColaFicticia($rollbackId));
        $workerRollback = new Process($cmd, base_path(), $env, null, 220);
        $workerRollback->start();
        try {
            $limite = microtime(true) + 40;
            do {
                $db->select('select pg_stat_clear_snapshot()');
                $backend = $db->selectOne("select pid from pg_stat_activity where datname=current_database() and usename=current_user and wait_event='PgSleep' and query like '%propuesta_cpsat%' limit 1");
                if ($backend) {
                    break;
                }
                usleep(100000);
            } while (microtime(true) < $limite && $workerRollback->isRunning());
            self::assertNotNull($backend, 'La inserción debe estar abierta antes del commit.');
            $pidRollback = $workerRollback->getPid();
            $matarRollback = '$p=@(Get-CimInstance Win32_Process -Filter "ProcessId = '.$pidRollback.' OR ParentProcessId = '.$pidRollback.'" | Where-Object { $_.Name -eq "php.exe" -and $_.CommandLine -like "*gh-27b5-prueba*" }); if ($p.Count -ne 1) { exit 2 }; Stop-Process -Id $p[0].ProcessId -Force';
            (new Process(['powershell.exe', '-NoProfile', '-Command', $matarRollback], base_path()))->mustRun();
            $workerRollback->wait();
            $limite = microtime(true) + 15;
            do {
                $db->select('select pg_stat_clear_snapshot()');
                $ocupado = $db->selectOne('select exists(select 1 from pg_stat_activity where pid=? and state <> ?) as ocupado', [$backend->pid, 'idle']);
                if (! $ocupado->ocupado) {
                    break;
                }
                usleep(200000);
            } while (microtime(true) < $limite);
            self::assertFalse($ocupado->ocupado, 'La transacción interrumpida debe cerrarse.');
            self::assertSame(0, $db->table('prueba.propuesta_cpsat')->where('solicitud_sha256', $hashRollback)->count());
            self::assertSame('EJECUTANDO', $db->table('prueba.solicitud_cola')->where('id', $rollbackId)->value('estado'));
        } finally {
            if ($workerRollback->isRunning()) {
                $workerRollback->stop(0);
            }
            $db->unprepared('DROP TRIGGER pausa_insercion_27b7 ON prueba.propuesta_cpsat');
            $db->unprepared('DROP FUNCTION prueba.pausar_insercion_27b7()');
        }
        // No reencolar automáticamente: el resultado del reclamo puede haber sido confirmado.
        self::assertSame(4, $db->table('prueba.propuesta_cpsat')->count());

        // El callback se inserta en el primer solve del motor académico real, no sustituye el modelo.
        $db->table('prueba.meta')->where('periodo', '1')->update(['tiempo_limite' => 6]);
        $academica = (new EnsambladorAcademicoPrueba($db, $proveedor))->construir((new InstantaneaGeneracion($db))->construir(1), '1');
        self::assertTrue($academica['ejecutable']);
        $academicaId = (string) Str::uuid();
        $tokenAcademico = Str::uuid()->toString();
        $marcaAcademica = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gh-27b8-'.$tokenAcademico.'.json';
        $db->table('prueba.solicitud_cola')->insert(['id' => $academicaId, 'estado' => 'EN_COLA', 'preparada' => json_encode($academica, JSON_THROW_ON_ERROR), 'entrada_sha256' => $academica['huella_sha256'], 'academica_sha256' => $academica['huella_academica_sha256']]);
        $jobAcademico = Queue::connection('database')->pushOn('gh-27b5-prueba', new EjecutarPreparacionColaFicticia($academicaId));
        $db->table('prueba.solicitud_cola')->where('id', $academicaId)->update(['job_id' => $jobAcademico]);
        $envAcademico = array_merge($env, ['HORARIOS_27B8_MARCA' => $marcaAcademica, 'HORARIOS_27B8_TOKEN' => $tokenAcademico]);
        $workerAcademico = new Process($cmd, base_path(), $envAcademico, null, 220);
        $workerAcademico->start();
        $identidades = [];
        try {
            $limite = microtime(true) + 30;
            while (! is_file($marcaAcademica) && microtime(true) < $limite && $workerAcademico->isRunning()) {
                usleep(100000);
            }
            self::assertFileExists($marcaAcademica, 'El modelo académico debe alcanzar un callback real de OR-Tools.');
            $eventoAcademico = json_decode(file_get_contents($marcaAcademica), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('callback_modelo_academico', $eventoAcademico['fase']);
            self::assertSame($tokenAcademico, $eventoAcademico['token']);
            $historialAcademico = $db->table('prueba.intento_cola')->where('solicitud_id', $academicaId)->first();
            self::assertNotNull($historialAcademico);
            self::assertGreaterThan(0, $historialAcademico->worker_pid);
            self::assertGreaterThan(0, $historialAcademico->lanzador_pid);
            $consultaProceso = '$p=@(Get-CimInstance Win32_Process | Where-Object { $_.Name -eq "python.exe" -and $_.CommandLine -like "*academico_instrumentado_27b8.py*" -and $_.CommandLine -like "*'.$tokenAcademico.'*" } | Select-Object ProcessId,ParentProcessId,@{Name="creacion";Expression={$_.CreationDate.ToString("o")}},CommandLine); ConvertTo-Json -InputObject $p -Compress';
            $procesoIdentidad = new Process(['powershell.exe', '-NoProfile', '-Command', $consultaProceso], base_path());
            $procesoIdentidad->mustRun();
            $identidades = json_decode($procesoIdentidad->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            self::assertNotEmpty($identidades);
            self::assertContains((int) $eventoAcademico['pid'], array_map('intval', array_column($identidades, 'ProcessId')));
            $arbolScript = '$todos=@(Get-CimInstance Win32_Process); $ids=New-Object System.Collections.Generic.HashSet[int]; [void]$ids.Add('.(int) $historialAcademico->worker_pid.'); do { $antes=$ids.Count; foreach ($p in $todos) { if ($ids.Contains([int]$p.ParentProcessId)) { [void]$ids.Add([int]$p.ProcessId) } } } while ($ids.Count -ne $antes); $p=@($todos | Where-Object { $ids.Contains([int]$_.ProcessId) } | Select-Object ProcessId,ParentProcessId,@{Name="creacion";Expression={$_.CreationDate.ToString("o")}},CommandLine); ConvertTo-Json -InputObject $p -Compress';
            $leerArbol = new Process(['powershell.exe', '-NoProfile', '-Command', $arbolScript], base_path());
            $leerArbol->mustRun();
            $arbol = json_decode($leerArbol->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            self::assertContains((int) $eventoAcademico['pid'], array_map('intval', array_column($arbol, 'ProcessId')), 'Python debe descender del worker de prueba.');
            $infoWorker = current(array_filter($arbol, fn ($p) => (int) $p['ProcessId'] === (int) $historialAcademico->worker_pid));
            $infoPython = current(array_filter($identidades, fn ($p) => (int) $p['ProcessId'] === (int) $eventoAcademico['pid']));
            self::assertNotEmpty($infoWorker);
            $db->table('prueba.intento_cola')->where('id', $historialAcademico->id)->update(['worker_creacion' => $infoWorker['creacion'], 'python_pid' => $infoPython['ProcessId'], 'python_padre' => $infoPython['ParentProcessId'], 'python_creacion' => $infoPython['creacion']]);
            $primeraRenovacion = $historialAcademico->renovado_en;
            $limite = microtime(true) + 8;
            do {
                usleep(300000);
                $renovado = $db->table('prueba.intento_cola')->where('id', $historialAcademico->id)->value('renovado_en');
            } while ((! $primeraRenovacion || $renovado === $primeraRenovacion) && microtime(true) < $limite);
            self::assertNotNull($primeraRenovacion);
            self::assertNotSame($primeraRenovacion, $renovado, 'El lease debe renovarse sucesivamente durante el solve instrumentado.');
            $pidPhp = (int) $historialAcademico->worker_pid;
            $matarPhp = '$p=Get-CimInstance Win32_Process -Filter "ProcessId = '.$pidPhp.'"; if (!$p -or $p.CreationDate.ToString("o") -ne "'.$infoWorker['creacion'].'" -or $p.Name -ne "php.exe" -or $p.CommandLine -notlike "*gh-27b5-prueba*") { exit 2 }; Stop-Process -Id $p.ProcessId -Force';
            (new Process(['powershell.exe', '-NoProfile', '-Command', $matarPhp], base_path()))->mustRun();
            $procesoIdentidad = new Process(['powershell.exe', '-NoProfile', '-Command', $consultaProceso], base_path());
            $procesoIdentidad->mustRun();
            $activos = json_decode($procesoIdentidad->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            self::assertContains((int) $eventoAcademico['pid'], array_map('intval', array_column($activos, 'ProcessId')), 'Observar Python vivo tras terminar el worker.');
            $reconciliacion = $reconciliador->consultar($academicaId);
            self::assertSame('EJECUTANDO', $reconciliacion['estado']);
            self::assertSame('RESERVADO', $reconciliacion['mensaje']);
            $db->table('prueba.solicitud_cola')->where('id', $academicaId)->update(['lease_hasta' => DB::raw("clock_timestamp()-interval '1 second'")]);
            self::assertTrue($reconciliador->cercarExpirado($academicaId, $historialAcademico->id, (int) $historialAcademico->generacion));
            self::assertFalse($reconciliador->renovar($academicaId, $historialAcademico->id, (int) $historialAcademico->generacion));
            self::assertSame('PROCESO_NO_VERIFICADO', $recuperar->recuperar($academicaId, new EjecutarPreparacionColaFicticia($academicaId), fn () => false));
            foreach ($identidades as $identidad) {
                $pid = (int) $identidad['ProcessId'];
                $creacion = $identidad['creacion'];
                $detener = '$p=Get-CimInstance Win32_Process -Filter "ProcessId = '.$pid.'"; if ($p -and $p.CreationDate.ToString("o") -eq "'.$creacion.'" -and $p.CommandLine -like "*'.$tokenAcademico.'*") { Stop-Process -Id $p.ProcessId -Force } elseif ($p) { exit 3 }';
                (new Process(['powershell.exe', '-NoProfile', '-Command', $detener], base_path()))->mustRun();
            }
            $verificarTerminacion = function () use ($arbol, $tokenAcademico): bool {
                foreach ($arbol as $identidad) {
                    $pid = (int) $identidad['ProcessId'];
                    $verificar = '$p=Get-CimInstance Win32_Process -Filter "ProcessId = '.$pid.'"; if ($p) { exit 3 }';
                    $proceso = new Process(['powershell.exe', '-NoProfile', '-Command', $verificar], base_path());
                    $proceso->run();
                    if (! $proceso->isSuccessful()) {
                        return false;
                    }
                }
                $buscar = '$p=@(Get-CimInstance Win32_Process | Where-Object { $_.Name -eq "python.exe" -and $_.CommandLine -like "*academico_instrumentado_27b8.py*" -and $_.CommandLine -like "*'.$tokenAcademico.'*" }); if ($p.Count -ne 0) { exit 4 }';
                $proceso = new Process(['powershell.exe', '-NoProfile', '-Command', $buscar], base_path());
                $proceso->run();

                return $proceso->isSuccessful();
            };
            self::assertTrue($verificarTerminacion());
            self::assertSame('REENCOLADA', $recuperar->recuperar($academicaId, new EjecutarPreparacionColaFicticia($academicaId), $verificarTerminacion));
            $workerRecuperado = new Process($cmd, base_path(), $env, null, 220);
            $workerRecuperado->mustRun();
            $intentosAcademicos = $db->table('prueba.intento_cola')->where('solicitud_id', $academicaId)->orderBy('generacion')->get();
            self::assertCount(2, $intentosAcademicos);
            self::assertSame('CERCADO', $intentosAcademicos[0]->estado);
            self::assertSame('FINALIZADO', $intentosAcademicos[1]->estado);
            self::assertNotSame($intentosAcademicos[0]->id, $intentosAcademicos[1]->id);
            self::assertSame('VALIDADA_PHP_PARCIAL', $intentosAcademicos[1]->validacion);
            self::assertSame(1, $db->table('prueba.propuesta_cpsat')->where('solicitud_sha256', $academica['huella_sha256'])->count());
            $resultadoRecuperado = json_decode($intentosAcademicos[1]->resultado, true, 512, JSON_THROW_ON_ERROR);
            $tardioAcademico = $proteccion->aceptar($academica, $resultadoRecuperado, fn () => $db->table('prueba.solicitud_cola')->where('id', $academicaId)->where('estado', 'EJECUTANDO')->where('intento', $historialAcademico->id)->where('generacion', $historialAcademico->generacion)->update(['estado' => 'FINALIZADO']) === 1);
            self::assertSame('INTENTO_DESACTUALIZADO', $tardioAcademico['estado']);
        } finally {
            $limpieza = '$p=@(Get-CimInstance Win32_Process | Where-Object { $_.Name -eq "python.exe" -and $_.CommandLine -like "*academico_instrumentado_27b8.py*" -and $_.CommandLine -like "*'.$tokenAcademico.'*" }); foreach ($h in $p) { Stop-Process -Id $h.ProcessId -Force -ErrorAction SilentlyContinue }';
            (new Process(['powershell.exe', '-NoProfile', '-Command', $limpieza], base_path()))->run();
            if ($workerAcademico->isRunning()) {
                $workerAcademico->stop(0);
            }
            foreach ([$marcaAcademica, $marcaAcademica.'.tmp'] as $temporal) {
                if (is_file($temporal)) {
                    unlink($temporal);
                }
            }
        }

        // Tres interrupciones reales de worker, siempre después del retorno de Python.
        $db->table('prueba.meta')->where('periodo', '1')->update(['tiempo_limite' => 8]);
        $repetida = (new EnsambladorAcademicoPrueba($db, $proveedor))->construir((new InstantaneaGeneracion($db))->construir(1), '1');
        self::assertTrue($repetida['ejecutable']);
        $repetidaId = (string) Str::uuid();
        $db->table('prueba.solicitud_cola')->insert(['id' => $repetidaId, 'estado' => 'EN_COLA', 'preparada' => json_encode($repetida, JSON_THROW_ON_ERROR), 'entrada_sha256' => $repetida['huella_sha256'], 'academica_sha256' => $repetida['huella_academica_sha256']]);
        Queue::connection('database')->pushOn('gh-27b5-prueba', new EjecutarPreparacionColaFicticia($repetidaId));
        for ($numero = 1; $numero <= 3; $numero++) {
            $workerFallo = new Process($cmd, base_path(), $envPausa, null, 220);
            $workerFallo->start();
            try {
                $limite = microtime(true) + 40;
                do {
                    $intentoFallo = $db->table('prueba.intento_cola')->where('solicitud_id', $repetidaId)->orderByDesc('generacion')->first();
                    if ($intentoFallo && $intentoFallo->python_finalizado_en && $intentoFallo->estado === 'EJECUTANDO') {
                        break;
                    }
                    usleep(100000);
                } while (microtime(true) < $limite && $workerFallo->isRunning());
                self::assertNotNull($intentoFallo?->python_finalizado_en);
                self::assertNotNull($intentoFallo->evidencia);
                $pidFallo = $workerFallo->getPid();
                $matarFallo = '$p=@(Get-CimInstance Win32_Process -Filter "ProcessId = '.$pidFallo.' OR ParentProcessId = '.$pidFallo.'" | Where-Object { $_.Name -eq "php.exe" -and $_.CommandLine -like "*gh-27b5-prueba*" }); if ($p.Count -ne 1) { exit 2 }; Stop-Process -Id $p[0].ProcessId -Force';
                (new Process(['powershell.exe', '-NoProfile', '-Command', $matarFallo], base_path()))->mustRun();
                $workerFallo->wait();
                self::assertFalse($workerFallo->isRunning());
            } finally {
                if ($workerFallo->isRunning()) {
                    $workerFallo->stop(0);
                }
            }
            $db->table('prueba.solicitud_cola')->where('id', $repetidaId)->update(['lease_hasta' => DB::raw("clock_timestamp()-interval '1 second'")]);
            self::assertTrue($reconciliador->cercarExpirado($repetidaId, $intentoFallo->id, (int) $intentoFallo->generacion));
            self::assertFalse($reconciliador->renovar($repetidaId, $intentoFallo->id, (int) $intentoFallo->generacion));
            $decision = $recuperar->recuperar($repetidaId, new EjecutarPreparacionColaFicticia($repetidaId), fn ($historial) => $historial->python_finalizado_en !== null && ! $workerFallo->isRunning(), 3);
            self::assertSame($numero < 3 ? 'REENCOLADA' : 'LIMITE_INTENTOS', $decision);
        }
        $intentosRepetidos = $db->table('prueba.intento_cola')->where('solicitud_id', $repetidaId)->orderBy('generacion')->get();
        self::assertCount(3, $intentosRepetidos);
        self::assertSame(['CERCADO', 'CERCADO', 'CERCADO'], $intentosRepetidos->pluck('estado')->all());
        self::assertCount(3, array_unique($intentosRepetidos->pluck('id')->all()));
        self::assertSame(0, $db->table('prueba.propuesta_cpsat')->where('solicitud_sha256', $repetida['huella_sha256'])->count());

        // Datos académicos ficticios persistidos, sin pausas en Python ni cambios del solver.
        $db->unprepared("INSERT INTO core.curso SELECT 10000+n, 'C'||n, 'Curso ampliado '||n, 2, 0, 0 FROM generate_series(1,36) n;
            INSERT INTO core.curso_plan SELECT 11000+n,10000+n,CASE WHEN n%2=0 THEN 10 ELSE 20 END,CASE n%3 WHEN 0 THEN 1 WHEN 1 THEN 3 ELSE 5 END FROM generate_series(1,36) n;
            INSERT INTO core.seccion SELECT 13000+n,1,11000+n,'A',30,NULL,'ACTIVA' FROM generate_series(1,36) n;
            INSERT INTO gestion_horarios.oferta_curso SELECT 12000+n,1,11000+n,'REGULAR',1,'OFERTADA' FROM generate_series(1,36) n;
            INSERT INTO gestion_horarios.oferta_seccion SELECT 12000+n,12000+n,13000+n,'VIGENTE' FROM generate_series(1,36) n;
            INSERT INTO core.docente SELECT 15000+n,'ACTIVO',20 FROM generate_series(1,36) n;
            INSERT INTO core.ambiente SELECT 19000+n,'A'||n,'Aula ampliada '||n,'AULA',30,'ACTIVO' FROM generate_series(1,6) n;
            INSERT INTO gestion_horarios.actividad_academica SELECT 14000+n,13000+n,'TEORIA','LUNES','09:00','11:00',120 FROM generate_series(1,36) n;
            INSERT INTO gestion_horarios.asignacion_docente SELECT 16000+n,14000+n,15000+n,'CONFIRMADA',NULL FROM generate_series(1,36) n;
            INSERT INTO gestion_horarios.declaracion_disponibilidad SELECT 17000+n,1,15000+n,'PRESENTADA','ESTRICTA' FROM generate_series(1,36) n;
            INSERT INTO gestion_horarios.disponibilidad_docente SELECT 180000+n*100+d.orden*10+b.ordinal,1,15000+n,17000+n,d.dia,b.id FROM generate_series(1,36) n CROSS JOIN prueba.dia d CROSS JOIN (VALUES(1,91),(2,93),(3,95)) b(ordinal,id);
            INSERT INTO prueba.padron SELECT 13000+n,'FX'||n,'SIN_PREFERENCIA' FROM generate_series(1,36) n;
            INSERT INTO prueba.participante_actividad SELECT 14000+n,'FX'||n FROM generate_series(1,36) n;
            INSERT INTO prueba.patron SELECT 14000+n,1,120 FROM generate_series(1,36) n;
            INSERT INTO prueba.docente_nombre SELECT 15000+n,'Docente ampliado '||n FROM generate_series(1,36) n;
            UPDATE prueba.meta SET tiempo_limite=10 WHERE periodo='1'");
        $ampliada = (new EnsambladorAcademicoPrueba($db, $proveedor))->construir((new InstantaneaGeneracion($db))->construir(1), '1');
        self::assertTrue($ampliada['ejecutable'], json_encode($ampliada['diagnosticos'], JSON_UNESCAPED_UNICODE));
        $ampliadaId = (string) Str::uuid();
        $db->table('prueba.solicitud_cola')->insert(['id' => $ampliadaId, 'estado' => 'EN_COLA', 'preparada' => json_encode($ampliada, JSON_THROW_ON_ERROR), 'entrada_sha256' => $ampliada['huella_sha256'], 'academica_sha256' => $ampliada['huella_academica_sha256']]);
        Queue::connection('database')->pushOn('gh-27b5-prueba', new EjecutarPreparacionColaFicticia($ampliadaId));
        $workerAmpliado = new Process($cmd, base_path(), $env, null, 220);
        $workerAmpliado->mustRun();
        $solicitudAmpliada = $db->table('prueba.solicitud_cola')->where('id', $ampliadaId)->first();
        $historiaAmpliada = $db->table('prueba.intento_cola')->where('solicitud_id', $ampliadaId)->first();
        self::assertCount(40, $ampliada['entrada']['actividades']);
        self::assertContains($solicitudAmpliada->solver, ['OPTIMAL', 'FEASIBLE']);
        self::assertSame('FINALIZADO', $solicitudAmpliada->estado, (string) $solicitudAmpliada->diagnostico);
        self::assertSame('VALIDADA_PHP_PARCIAL', $historiaAmpliada->validacion);
        self::assertGreaterThanOrEqual(2, $solicitudAmpliada->tiempo_busqueda, 'La búsqueda natural debe durar lo suficiente para observar renovaciones.');
        self::assertGreaterThanOrEqual(3, $historiaAmpliada->renovaciones);
        self::assertNotNull($historiaAmpliada->renovado_en);
        self::assertSame(1, $db->table('prueba.propuesta_cpsat')->where('solicitud_sha256', $ampliada['huella_sha256'])->count());
        fwrite(STDERR, "\nEscenario ampliado: solver={$solicitudAmpliada->solver}, renovaciones={$historiaAmpliada->renovaciones}, búsqueda={$solicitudAmpliada->tiempo_busqueda}\n");
    }
}
