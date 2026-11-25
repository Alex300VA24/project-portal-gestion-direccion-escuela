<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Script CLI de QA. No registrar como migración institucional.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'pgsql' || getenv('DB_DATABASE') !== 'horarios_demo_2026_ii_demanda'
    || getenv('HORARIOS_DEMO_PLAN') !== 'QA-HORARIOS' || getenv('HORARIOS_QA_CONSULTAS') !== '1') {
    throw new RuntimeException('Se requiere el entorno aislado horarios_demo_2026_ii_demanda con QA-HORARIOS.');
}
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
$db->transaction(function () use ($db) {
    $db->statement('LOCK TABLE gestion_horarios.qa_generacion IN SHARE ROW EXCLUSIVE MODE');
    if ($db->table('gestion_horarios.qa_generacion')->whereIn('estado',['PENDIENTE','EJECUTANDO'])->exists()) throw new RuntimeException('Hay un cálculo activo.');
    $db->statement('LOCK TABLE core.plan_estudios, core.periodo_academico, core.docente, core.persona IN SHARE MODE');
    $planes = $db->table('core.plan_estudios')->pluck('codigo')->all();
    $periodos = $db->table('core.periodo_academico')->pluck('codigo')->all();
    $noQa = $db->table('core.docente as d')->leftJoin('core.persona as p', 'p.id_persona', '=', 'd.id_persona')
        ->where(fn ($q) => $q->whereNull('p.id_persona')->orWhereNull('p.tipo_documento')->orWhere('p.tipo_documento', '!=', 'QA')->orWhere('d.codigo_docente', 'not like', 'QA-%'))->exists();
    if ($planes !== ['QA-HORARIOS'] || $periodos !== ['2026-II'] || $noQa) {
        throw new RuntimeException('El catálogo no es exclusivamente de demostración.');
    }
    $db->unprepared(<<<'SQL'
ALTER TABLE gestion_horarios.qa_generacion DROP CONSTRAINT IF EXISTS qa_generacion_modo_check;
ALTER TABLE gestion_horarios.qa_generacion ADD CONSTRAINT qa_generacion_modo_check CHECK (modo IN ('prevalidar','generar','reorganizar'));
CREATE TABLE IF NOT EXISTS gestion_horarios.qa_calendario_revision (
 version BIGINT PRIMARY KEY CHECK(version>0), datos JSONB NOT NULL CHECK(jsonb_typeof(datos)='object'),
 actor VARCHAR(190) NOT NULL CHECK(actor='director.qa@horarios.test'), creado_en TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE IF NOT EXISTS gestion_horarios.qa_publicacion_evento (
 id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
 propuesta UUID NOT NULL, version BIGINT NOT NULL,
 evento VARCHAR(12) NOT NULL CHECK(evento IN ('APROBADO','PUBLICADO','RETIRADO')),
 actor VARCHAR(190) NOT NULL CHECK(actor='director.qa@horarios.test'), motivo VARCHAR(300) NOT NULL,
 instantanea JSONB NULL, creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
 FOREIGN KEY(propuesta,version) REFERENCES gestion_horarios.qa_horario_revision(propuesta,version) ON DELETE RESTRICT,
 CHECK ((evento='PUBLICADO' AND instantanea IS NOT NULL AND jsonb_typeof(instantanea)='object') OR (evento<>'PUBLICADO' AND instantanea IS NULL))
);
SQL);
});
echo "Calendario y publicación QA preparados. No se publicaron horarios.\n";
