<?php
/** Operador CLI local. Mantener fuera de public/. Solo QA aislado. */
declare(strict_types=1);

use App\Modules\GestionHorarios\Services\ColaGeneracionDemo as Cola;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'pgsql'
    || getenv('DB_DATABASE') !== 'horarios_demo_2026_ii_demanda' || getenv('HORARIOS_DEMO_PLAN') !== 'QA-HORARIOS'
    || getenv('HORARIOS_QA_CONSULTAS') !== '1') {
    throw new RuntimeException('Se requiere la base aislada QA con demanda.');
}
$modo = $argv[1] ?? '';
if (! in_array($modo, ['init', 'worker'], true)) { throw new RuntimeException('Uso: php operar_cola_cpsat_qa.php init|worker'); }
$root = dirname(__DIR__, 5);
chdir($root);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection();
if (! $app->environment('testing') || $db->getDriverName() !== 'pgsql' || $db->getConfig('host') !== '127.0.0.1'
    || $db->selectOne('SELECT current_database() AS nombre')->nombre !== 'horarios_demo_2026_ii_demanda') {
    throw new RuntimeException('Conexión no autorizada.');
}
$comprobar = static function () use ($db): void {
    if ($db->table('core.plan_estudios')->pluck('codigo')->all() !== ['QA-HORARIOS']
        || $db->table('core.periodo_academico')->pluck('codigo')->all() !== ['2026-II']
        || $db->table('core.docente as d')->leftJoin('core.persona as p','p.id_persona','=','d.id_persona')
            ->where(fn ($q) => $q->whereNull('p.id_persona')->orWhereNull('p.tipo_documento')->orWhere('p.tipo_documento','!=','QA')->orWhere('d.codigo_docente','not like','QA-%'))->exists()
        || ! $db->table('public.users')->where('email','director.qa@horarios.test')->exists()) {
        throw new RuntimeException('Catálogo QA o director no reconocido.');
    }
};
$comprobar();
if ($modo === 'init') {
    $db->transaction(function () use ($db, $comprobar) {
        $db->statement('LOCK TABLE core.plan_estudios, core.periodo_academico, core.docente, core.persona, public.users IN SHARE MODE');
        $comprobar();
        $db->statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS gestion_horarios.qa_generacion (
    id UUID PRIMARY KEY,
    modo VARCHAR(12) NOT NULL CHECK (modo IN ('prevalidar','generar')),
    segundos INTEGER NOT NULL CHECK (segundos BETWEEN 1 AND 300),
    estado VARCHAR(16) NOT NULL CHECK (estado IN ('PENDIENTE','EJECUTANDO','FINALIZADA','ERROR')),
    fase VARCHAR(24) NOT NULL,
    resultado JSONB NULL CHECK (resultado IS NULL OR jsonb_typeof(resultado) = 'object'),
    propuesta UUID NULL,
    creada_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    iniciada_en TIMESTAMPTZ NULL,
    actualizada_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    finalizada_en TIMESTAMPTZ NULL
)
SQL);
        $db->statement("CREATE UNIQUE INDEX IF NOT EXISTS qa_generacion_unica_activa ON gestion_horarios.qa_generacion ((1)) WHERE estado IN ('PENDIENTE','EJECUTANDO')");
    });
    echo "Cola QA preparada. No se generaron horarios.\n"; exit;
}
$script = __DIR__.'/generar_horario_cpsat_qa.php';
$python = getenv('HORARIOS_QA_PYTHON'); $fuente = getenv('HORARIOS_DEMO_ARCHIVO');
if (! is_file($script) || ! is_file(__DIR__.'/generar_cpsat_qa.py') || ! $python || ! is_file($python) || ! $fuente || ! is_file($fuente)
    || ! $db->getSchemaBuilder()->hasTable(Cola::TABLA) || ! $db->getSchemaBuilder()->hasTable('gestion_horarios.qa_horario_revision')) {
    throw new RuntimeException('Falta conector, Python, JSON o almacenamiento QA.');
}
// Un único operador; la restricción parcial impide solicitudes web simultáneas.
if ((int) $db->selectOne('SELECT CASE WHEN pg_try_advisory_lock(913271, 20261124) THEN 1 ELSE 0 END AS adquirido')->adquirido !== 1) {
    throw new RuntimeException('Ya existe un operador QA activo.');
}
try {
    if ($db->table(Cola::TABLA)->where('estado','EJECUTANDO')->exists()) {
        throw new RuntimeException('Ejecución previa sin cierre. Revise el proceso y las propuestas antes de intervenir; no se reintenta automáticamente.');
    }
    echo "Operador CP-SAT QA activo. Ctrl+C solo cuando no haya cálculos.\n";
    while (true) {
        $fila = $db->transaction(function () use ($db) {
            $r = $db->table(Cola::TABLA)->where('estado','PENDIENTE')->orderBy('creada_en')->lockForUpdate()->first();
            if ($r) { $db->table(Cola::TABLA)->where('id',$r->id)->update(['estado'=>'EJECUTANDO','fase'=>'PREPARANDO','iniciada_en'=>DB::raw('CURRENT_TIMESTAMP'),'actualizada_en'=>DB::raw('CURRENT_TIMESTAMP')]); }
            return $r;
        });
        if (! $fila) { sleep(2); continue; }
        $proc = null;
        $fases = [];
        $registrarFase = static function (string $fase) use (&$fases): void {
            if (($fases[array_key_last($fases)]['fase'] ?? null) === $fase) { return; }
            $fases[] = ['fase'=>$fase,'en'=>(new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM)];
        };
        $registrarFase('PREPARANDO');
        $actualizar = static function (array $cambios) use ($db,$fila): void {
            $n = $db->table(Cola::TABLA)->where('id',$fila->id)->where('estado','EJECUTANDO')->update($cambios + ['actualizada_en'=>DB::raw('CURRENT_TIMESTAMP')]);
            if ($n !== 1) { throw new RuntimeException('La ejecución perdió su estado activo.'); }
        };
        try {
            $comprobar();
            echo "Solicitud {$fila->id} · {$fila->modo}\n";
            $proc = new Process([PHP_BINARY,$script,$fila->modo,(string)$fila->segundos],$root);
            // El conector controla el presupuesto del solver y su propio timeout.
            $proc->setTimeout((int)$fila->segundos+300);
            $actualizar(['resultado'=>json_encode(['fases_observadas'=>$fases], JSON_THROW_ON_ERROR)]);
            $buffer = '';
            $proc->start(static function (string $tipo,string $texto) use (&$buffer,$actualizar,&$fases,$registrarFase): void {
                if ($tipo !== Process::ERR) { return; }
                fwrite(STDERR,$texto); $buffer .= $texto;
                while (($pos = strpos($buffer,"\n")) !== false) {
                    $linea = substr($buffer,0,$pos); $buffer = substr($buffer,$pos+1);
                    if ($fase = Cola::fase($linea)) {
                        $registrarFase($fase);
                        $actualizar(['fase'=>$fase,'resultado'=>json_encode(['fases_observadas'=>$fases],JSON_THROW_ON_ERROR)]);
                    }
                }
                if (strlen($buffer)>8192) { $buffer=''; }
            });
            $latido = microtime(true);
            while ($proc->isRunning()) {
                $proc->checkTimeout();
                if (microtime(true)-$latido >= 5) { $actualizar([]); $latido=microtime(true); }
                usleep(200000);
            }
            $proc->wait();
            if (! $proc->isSuccessful()) { fwrite(STDERR,$proc->getOutput()); throw new RuntimeException('El conector terminó con código '.$proc->getExitCode().'.'); }
            [$resultado,$propuesta] = Cola::interpretar($proc->getOutput(),$fila->modo);
            if ($propuesta && ! $db->table('gestion_horarios.qa_horario_revision')->where('propuesta',$propuesta)->where('version',1)->exists()) {
                throw new RuntimeException('No se encontró la propuesta anunciada.');
            }
            $registrarFase('FINALIZADA');
            $resultado['fases_observadas']=$fases;
            $actualizar(['estado'=>'FINALIZADA','fase'=>'FINALIZADA','resultado'=>json_encode($resultado,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'propuesta'=>$propuesta,'finalizada_en'=>DB::raw('CURRENT_TIMESTAMP')]);
            echo "Resultado: {$resultado['estado']}\n";
        } catch (Throwable $e) {
            if ($proc && $proc->isRunning()) {
                // Si se desconoce el destino del proceso hijo, conservar el bloqueo lógico.
                fwrite(STDERR,"Error durante ejecución: revise los procesos y la propuesta antes de reintentar.\n");
                throw $e;
            }
            fwrite(STDERR,$e->getMessage().PHP_EOL);
            $registrarFase('ERROR');
            $actualizar(['estado'=>'ERROR','fase'=>'ERROR','resultado'=>json_encode(['fases_observadas'=>$fases],JSON_THROW_ON_ERROR),'finalizada_en'=>DB::raw('CURRENT_TIMESTAMP')]);
        }
    }
} finally {
    $db->selectOne('SELECT pg_advisory_unlock(913271, 20261124)');
}
