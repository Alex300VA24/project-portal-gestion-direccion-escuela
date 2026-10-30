<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS gestion_horarios');
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.actividad_academica (
    id_actividad BIGINT GENERATED ALWAYS AS IDENTITY,
    id_seccion BIGINT NOT NULL,
    tipo_actividad VARCHAR(20) NOT NULL DEFAULT 'TEORIA',
    dia_semana VARCHAR(10) NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    minutos_sesion SMALLINT NULL,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_actividad_academica PRIMARY KEY (id_actividad),
    CONSTRAINT fk_actividad_seccion FOREIGN KEY (id_seccion)
        REFERENCES core.seccion (id_seccion) ON DELETE RESTRICT,
    CONSTRAINT ck_actividad_tipo
        CHECK (tipo_actividad IN ('TEORIA', 'PRACTICA', 'LABORATORIO', 'TALLER')),
    CONSTRAINT ck_actividad_dia
        CHECK (dia_semana IN ('LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO')),
    CONSTRAINT ck_actividad_horas CHECK (hora_inicio < hora_fin),
    CONSTRAINT ck_actividad_minutos CHECK (minutos_sesion IS NULL OR minutos_sesion > 0)
);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS gestion_horarios.actividad_academica');
    }
};