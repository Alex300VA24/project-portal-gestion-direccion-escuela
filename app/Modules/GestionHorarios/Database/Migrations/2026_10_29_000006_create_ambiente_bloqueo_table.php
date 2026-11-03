<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS gestion_horarios');
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.ambiente_bloqueo (
    id_bloqueo BIGINT GENERATED ALWAYS AS IDENTITY,
    id_ambiente BIGINT NOT NULL,
    id_periodo BIGINT NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    dia_semana VARCHAR(10) NULL,
    hora_inicio TIME NULL,
    hora_fin TIME NULL,
    motivo TEXT NOT NULL,
    id_registrado_por BIGINT NULL,
    registrado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_ambiente_bloqueo PRIMARY KEY (id_bloqueo),
    CONSTRAINT fk_bloqueo_ambiente FOREIGN KEY (id_ambiente)
        REFERENCES core.ambiente (id_ambiente) ON DELETE RESTRICT,
    CONSTRAINT fk_bloqueo_periodo FOREIGN KEY (id_periodo)
        REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
    CONSTRAINT fk_bloqueo_usuario FOREIGN KEY (id_registrado_por)
        REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT ck_bloqueo_fechas CHECK (fecha_inicio <= fecha_fin),
    CONSTRAINT ck_bloqueo_horas CHECK (
        (hora_inicio IS NULL AND hora_fin IS NULL)
        OR (hora_inicio IS NOT NULL AND hora_fin IS NOT NULL AND hora_inicio < hora_fin)
    ),
    CONSTRAINT ck_bloqueo_dia
        CHECK (dia_semana IS NULL
            OR dia_semana IN ('LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO')),
    CONSTRAINT ck_bloqueo_motivo CHECK (length(trim(motivo)) > 0)
);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS gestion_horarios.ambiente_bloqueo');
    }
};