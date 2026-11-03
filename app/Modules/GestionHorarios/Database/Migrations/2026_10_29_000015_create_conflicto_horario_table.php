<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS gestion_horarios');
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.conflicto_horario (
    id_conflicto BIGINT GENERATED ALWAYS AS IDENTITY,
    id_validacion BIGINT NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    severidad VARCHAR(10) NOT NULL,
    id_detalle_a BIGINT NULL,
    id_detalle_b BIGINT NULL,
    id_docente BIGINT NULL,
    id_ambiente BIGINT NULL,
    descripcion TEXT NOT NULL,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_conflicto_horario PRIMARY KEY (id_conflicto),
    CONSTRAINT fk_conflicto_validacion FOREIGN KEY (id_validacion)
        REFERENCES gestion_horarios.validacion_horario (id_validacion) ON DELETE RESTRICT,
    CONSTRAINT fk_conflicto_detalle_a FOREIGN KEY (id_detalle_a)
        REFERENCES gestion_horarios.detalle_horario (id_detalle) ON DELETE RESTRICT,
    CONSTRAINT fk_conflicto_detalle_b FOREIGN KEY (id_detalle_b)
        REFERENCES gestion_horarios.detalle_horario (id_detalle) ON DELETE RESTRICT,
    CONSTRAINT fk_conflicto_docente FOREIGN KEY (id_docente)
        REFERENCES core.docente (id_docente) ON DELETE RESTRICT,
    CONSTRAINT fk_conflicto_ambiente FOREIGN KEY (id_ambiente)
        REFERENCES core.ambiente (id_ambiente) ON DELETE RESTRICT,
    CONSTRAINT ck_conflicto_tipo CHECK (tipo IN (
        'CRUCE_DOCENTE', 'CRUCE_AMBIENTE', 'CAPACIDAD_INSUFICIENTE',
        'AMBIENTE_INCOMPATIBLE', 'FUERA_DISPONIBILIDAD', 'AMBIENTE_BLOQUEADO',
        'HORAS_INCOMPLETAS', 'PERIODO_INCOMPATIBLE', 'APERTURA_NO_AUTORIZADA', 'OTRO'
    )),
    CONSTRAINT ck_conflicto_severidad CHECK (severidad IN ('DURA', 'BLANDA')),
    CONSTRAINT ck_conflicto_descripcion CHECK (length(trim(descripcion)) > 0),
    CONSTRAINT ck_conflicto_detalles_distintos CHECK (
        id_detalle_a IS NULL OR id_detalle_b IS NULL OR id_detalle_a <> id_detalle_b
    )
);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS gestion_horarios.conflicto_horario');
    }
};