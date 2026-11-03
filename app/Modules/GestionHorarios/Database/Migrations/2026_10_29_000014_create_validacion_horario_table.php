<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS gestion_horarios');
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.validacion_horario (
    id_validacion BIGINT GENERATED ALWAYS AS IDENTITY,
    id_horario BIGINT NOT NULL,
    resultado VARCHAR(10) NOT NULL,
    validado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    id_validado_por BIGINT NULL,
    version_reglas VARCHAR(40) NOT NULL,
    huella_propuesta VARCHAR(64) NOT NULL,
    observacion TEXT NULL,
    CONSTRAINT pk_validacion_horario PRIMARY KEY (id_validacion),
    CONSTRAINT fk_validacion_horario FOREIGN KEY (id_horario)
        REFERENCES gestion_horarios.horario (id_horario) ON DELETE CASCADE,
    CONSTRAINT fk_validacion_validado_por FOREIGN KEY (id_validado_por)
        REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT ck_validacion_resultado CHECK (resultado IN ('VALIDA', 'INVALIDA')),
    CONSTRAINT ck_validacion_huella CHECK (huella_propuesta ~ '^[0-9a-f]{32}$'),
    CONSTRAINT ck_validacion_version_reglas CHECK (length(trim(version_reglas)) > 0)
);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS gestion_horarios.validacion_horario');
    }
};