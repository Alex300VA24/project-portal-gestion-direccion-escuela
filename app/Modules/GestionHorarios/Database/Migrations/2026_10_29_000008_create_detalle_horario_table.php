<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS gestion_horarios');
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.detalle_horario (
    id_detalle BIGINT GENERATED ALWAYS AS IDENTITY,
    id_horario BIGINT NOT NULL,
    id_actividad BIGINT NOT NULL,
    id_ambiente BIGINT NULL,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_detalle_horario PRIMARY KEY (id_detalle),
    CONSTRAINT fk_detalle_horario FOREIGN KEY (id_horario)
        REFERENCES gestion_horarios.horario (id_horario) ON DELETE CASCADE,
    CONSTRAINT fk_detalle_actividad FOREIGN KEY (id_actividad)
        REFERENCES gestion_horarios.actividad_academica (id_actividad) ON DELETE RESTRICT,
    CONSTRAINT fk_detalle_ambiente FOREIGN KEY (id_ambiente)
        REFERENCES core.ambiente (id_ambiente) ON DELETE RESTRICT,
    CONSTRAINT uq_detalle_horario_actividad UNIQUE (id_horario, id_actividad)
);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS gestion_horarios.detalle_horario');
    }
};