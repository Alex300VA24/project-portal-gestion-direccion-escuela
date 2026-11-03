<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS gestion_horarios');
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.oferta_curso (
    id_oferta BIGINT GENERATED ALWAYS AS IDENTITY,
    id_periodo BIGINT NOT NULL,
    id_curso_plan BIGINT NOT NULL,
    tipo_oferta VARCHAR(20) NOT NULL DEFAULT 'REGULAR',
    docentes_minimos SMALLINT NOT NULL DEFAULT 1,
    estado VARCHAR(20) NOT NULL DEFAULT 'PLANIFICADA',
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_oferta_curso PRIMARY KEY (id_oferta),
    CONSTRAINT fk_oferta_periodo FOREIGN KEY (id_periodo)
        REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
    CONSTRAINT fk_oferta_curso_plan FOREIGN KEY (id_curso_plan)
        REFERENCES core.curso_plan (id_curso_plan) ON DELETE RESTRICT,
    CONSTRAINT uq_oferta_curso UNIQUE (id_periodo, id_curso_plan, tipo_oferta),
    CONSTRAINT ck_oferta_tipo CHECK (tipo_oferta IN ('REGULAR', 'REPETICION')),
    CONSTRAINT ck_oferta_estado CHECK (estado IN ('PLANIFICADA', 'OFERTADA', 'CERRADA')),
    CONSTRAINT ck_oferta_docentes_minimos CHECK (docentes_minimos > 0)
);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS gestion_horarios.oferta_curso');
    }
};