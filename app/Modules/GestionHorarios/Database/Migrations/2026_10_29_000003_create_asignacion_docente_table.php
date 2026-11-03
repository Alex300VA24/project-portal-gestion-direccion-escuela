<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS gestion_horarios');
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.asignacion_docente (
    id_asignacion BIGINT GENERATED ALWAYS AS IDENTITY,
    id_actividad BIGINT NOT NULL,
    id_docente BIGINT NOT NULL,
    id_apertura BIGINT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'PROPUESTA',
    observacion TEXT NULL,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_asignacion_docente PRIMARY KEY (id_asignacion),
    CONSTRAINT fk_asignacion_actividad FOREIGN KEY (id_actividad)
        REFERENCES gestion_horarios.actividad_academica (id_actividad) ON DELETE RESTRICT,
    CONSTRAINT fk_asignacion_docente FOREIGN KEY (id_docente)
        REFERENCES core.docente (id_docente) ON DELETE RESTRICT,
    CONSTRAINT ck_asignacion_estado
        CHECK (estado IN ('PROPUESTA', 'CONFIRMADA', 'RECHAZADA', 'REASIGNADA'))
);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS gestion_horarios.asignacion_docente');
    }
};