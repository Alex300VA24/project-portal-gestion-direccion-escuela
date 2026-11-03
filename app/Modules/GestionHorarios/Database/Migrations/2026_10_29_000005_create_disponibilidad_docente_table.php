<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS gestion_horarios');
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.disponibilidad_docente (
    id_disponibilidad BIGINT GENERATED ALWAYS AS IDENTITY,
    id_periodo BIGINT NOT NULL,
    id_docente BIGINT NOT NULL,
    id_declaracion BIGINT NULL,
    dia_semana VARCHAR(10) NOT NULL,
    id_bloque BIGINT NOT NULL,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_disponibilidad_docente PRIMARY KEY (id_disponibilidad),
    CONSTRAINT fk_disponibilidad_periodo FOREIGN KEY (id_periodo)
        REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
    CONSTRAINT fk_disponibilidad_docente FOREIGN KEY (id_docente)
        REFERENCES core.docente (id_docente) ON DELETE RESTRICT,
    CONSTRAINT fk_disponibilidad_bloque FOREIGN KEY (id_bloque)
        REFERENCES gestion_horarios.bloque_horario (id_bloque) ON DELETE RESTRICT,
    CONSTRAINT fk_disponibilidad_bloque_periodo FOREIGN KEY (id_bloque, id_periodo)
        REFERENCES gestion_horarios.bloque_horario (id_bloque, id_periodo) ON DELETE RESTRICT,
    CONSTRAINT uq_disponibilidad UNIQUE (id_periodo, id_docente, dia_semana, id_bloque),
    CONSTRAINT ck_disponibilidad_dia
        CHECK (dia_semana IN ('LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'))
);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS gestion_horarios.disponibilidad_docente');
    }
};