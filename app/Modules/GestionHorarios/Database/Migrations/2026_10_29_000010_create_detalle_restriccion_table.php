<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS gestion_horarios');
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.detalle_restriccion (
    id_detalle_restriccion BIGINT GENERATED ALWAYS AS IDENTITY,
    id_restriccion BIGINT NOT NULL,
    ambito VARCHAR(20) NOT NULL DEFAULT 'GLOBAL',
    id_periodo BIGINT NULL,
    id_docente BIGINT NULL,
    id_ambiente BIGINT NULL,
    parametros JSONB NOT NULL DEFAULT '{}'::jsonb,
    prioridad SMALLINT NOT NULL DEFAULT 100,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_detalle_restriccion PRIMARY KEY (id_detalle_restriccion),
    CONSTRAINT fk_detalle_restriccion_restriccion FOREIGN KEY (id_restriccion)
        REFERENCES gestion_horarios.restriccion (id_restriccion) ON DELETE RESTRICT,
    CONSTRAINT fk_detalle_restriccion_periodo FOREIGN KEY (id_periodo)
        REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
    CONSTRAINT fk_detalle_restriccion_docente FOREIGN KEY (id_docente)
        REFERENCES core.docente (id_docente) ON DELETE RESTRICT,
    CONSTRAINT fk_detalle_restriccion_ambiente FOREIGN KEY (id_ambiente)
        REFERENCES core.ambiente (id_ambiente) ON DELETE RESTRICT,
    CONSTRAINT ck_detalle_restriccion_ambito CHECK (
        (ambito = 'GLOBAL'
            AND id_periodo IS NULL AND id_docente IS NULL AND id_ambiente IS NULL)
        OR (ambito = 'PERIODO'
            AND id_periodo IS NOT NULL AND id_docente IS NULL AND id_ambiente IS NULL)
        OR (ambito = 'DOCENTE'
            AND id_docente IS NOT NULL AND id_periodo IS NULL AND id_ambiente IS NULL)
        OR (ambito = 'AMBIENTE'
            AND id_ambiente IS NOT NULL AND id_periodo IS NULL AND id_docente IS NULL)
    ),
    CONSTRAINT ck_detalle_restriccion_prioridad CHECK (prioridad > 0)
);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS gestion_horarios.detalle_restriccion');
    }
};