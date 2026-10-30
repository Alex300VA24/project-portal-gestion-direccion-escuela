<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS gestion_horarios');
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.restriccion (
    id_restriccion BIGINT GENERATED ALWAYS AS IDENTITY,
    codigo VARCHAR(40) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    descripcion TEXT NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    severidad VARCHAR(10) NOT NULL,
    activa BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_restriccion PRIMARY KEY (id_restriccion),
    CONSTRAINT uq_restriccion_codigo UNIQUE (codigo),
    CONSTRAINT ck_restriccion_tipo CHECK (tipo IN (
        'CRUCE_DOCENTE', 'CRUCE_AMBIENTE', 'CAPACIDAD_INSUFICIENTE',
        'AMBIENTE_INCOMPATIBLE', 'FUERA_DISPONIBILIDAD', 'AMBIENTE_BLOQUEADO',
        'HORAS_INCOMPLETAS', 'PERIODO_INCOMPATIBLE', 'APERTURA_NO_AUTORIZADA', 'OTRO'
    )),
    CONSTRAINT ck_restriccion_severidad CHECK (severidad IN ('DURA', 'BLANDA')),
    CONSTRAINT ck_restriccion_descripcion CHECK (length(trim(descripcion)) > 0)
);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS gestion_horarios.restriccion');
    }
};