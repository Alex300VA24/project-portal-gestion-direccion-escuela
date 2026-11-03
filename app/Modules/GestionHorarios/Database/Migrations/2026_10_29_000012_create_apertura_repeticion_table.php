<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS gestion_horarios');
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.apertura_repeticion (
    id_apertura BIGINT GENERATED ALWAYS AS IDENTITY,
    id_periodo BIGINT NOT NULL,
    id_curso_plan BIGINT NOT NULL,
    id_seccion BIGINT NULL,
    id_docente_propuesto BIGINT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'SOLICITADA',
    solicitado_por BIGINT NOT NULL,
    autorizado_por BIGINT NULL,
    solicitado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    resuelto_en TIMESTAMPTZ NULL,
    observacion TEXT NULL,
    CONSTRAINT pk_apertura_repeticion PRIMARY KEY (id_apertura),
    CONSTRAINT fk_apertura_periodo FOREIGN KEY (id_periodo)
        REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
    CONSTRAINT fk_apertura_curso_plan FOREIGN KEY (id_curso_plan)
        REFERENCES core.curso_plan (id_curso_plan) ON DELETE RESTRICT,
    CONSTRAINT fk_apertura_seccion_periodo_curso FOREIGN KEY (id_seccion, id_periodo, id_curso_plan)
        REFERENCES core.seccion (id_seccion, id_periodo, id_curso_plan) ON DELETE RESTRICT,
    CONSTRAINT fk_apertura_docente_propuesto FOREIGN KEY (id_docente_propuesto)
        REFERENCES core.docente (id_docente) ON DELETE RESTRICT,
    CONSTRAINT fk_apertura_solicitado_por FOREIGN KEY (solicitado_por)
        REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT fk_apertura_autorizado_por FOREIGN KEY (autorizado_por)
        REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT ck_apertura_estado CHECK (estado IN (
        'SOLICITADA', 'AUTORIZADA', 'DENEGADA', 'INCORPORADA'
    )),
    CONSTRAINT ck_apertura_solicitada CHECK (
        estado <> 'SOLICITADA'
        OR (autorizado_por IS NULL AND resuelto_en IS NULL)
    ),
    CONSTRAINT ck_apertura_resuelta CHECK (
        estado NOT IN ('AUTORIZADA', 'DENEGADA', 'INCORPORADA')
        OR (autorizado_por IS NOT NULL AND resuelto_en IS NOT NULL)
    ),
    CONSTRAINT ck_apertura_incorporada CHECK (
        estado <> 'INCORPORADA'
        OR (id_seccion IS NOT NULL AND id_docente_propuesto IS NOT NULL)
    ),
    CONSTRAINT ck_apertura_fechas CHECK (resuelto_en IS NULL OR resuelto_en >= solicitado_en)
);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS gestion_horarios.apertura_repeticion');
    }
};