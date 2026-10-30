<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS gestion_horarios');
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.declaracion_disponibilidad (
    id_declaracion BIGINT GENERATED ALWAYS AS IDENTITY,
    id_periodo BIGINT NOT NULL,
    id_docente BIGINT NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE',
    tratamiento_regular VARCHAR(20) NOT NULL DEFAULT 'ESTRICTA',
    presentado_en TIMESTAMPTZ NULL,
    id_registrado_por BIGINT NULL,
    observacion TEXT NULL,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_declaracion_disponibilidad PRIMARY KEY (id_declaracion),
    CONSTRAINT fk_declaracion_periodo FOREIGN KEY (id_periodo)
        REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
    CONSTRAINT fk_declaracion_docente FOREIGN KEY (id_docente)
        REFERENCES core.docente (id_docente) ON DELETE RESTRICT,
    CONSTRAINT fk_declaracion_registrado_por FOREIGN KEY (id_registrado_por)
        REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT uq_declaracion_periodo_docente UNIQUE (id_periodo, id_docente),
    -- Habilita la clave foránea compuesta desde disponibilidad_docente
    -- para exigir que los bloques correspondan a la declaración del
    -- MISMO docente y período.
    CONSTRAINT uq_declaracion_compuesta UNIQUE (id_declaracion, id_periodo, id_docente),
    CONSTRAINT ck_declaracion_estado
        CHECK (estado IN ('PENDIENTE', 'PRESENTADA', 'NO_PRESENTADA')),
    CONSTRAINT ck_declaracion_tratamiento
        CHECK (tratamiento_regular IN ('ESTRICTA', 'FLEXIBLE')),
    CONSTRAINT ck_declaracion_presentada CHECK (
        (estado = 'PRESENTADA' AND presentado_en IS NOT NULL)
        OR (estado <> 'PRESENTADA')
    )
);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS gestion_horarios.declaracion_disponibilidad');
    }
};