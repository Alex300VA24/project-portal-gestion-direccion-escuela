<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS gestion_horarios');
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.bloque_horario (
    id_bloque BIGINT GENERATED ALWAYS AS IDENTITY,
    id_periodo BIGINT NOT NULL,
    nombre VARCHAR(60) NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    orden SMALLINT NULL,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_bloque_horario PRIMARY KEY (id_bloque),
    CONSTRAINT fk_bloque_periodo FOREIGN KEY (id_periodo)
        REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
    CONSTRAINT uq_bloque_periodo_inicio UNIQUE (id_periodo, hora_inicio),
    CONSTRAINT uq_bloque_periodo_compuesto UNIQUE (id_bloque, id_periodo),
    CONSTRAINT ck_bloque_horas CHECK (hora_inicio < hora_fin),
    CONSTRAINT ck_bloque_orden CHECK (orden IS NULL OR orden > 0)
);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS gestion_horarios.bloque_horario');
    }
};