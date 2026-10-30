<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS gestion_horarios');
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.horario_revision (
    id_revision BIGINT GENERATED ALWAYS AS IDENTITY,
    id_horario BIGINT NOT NULL,
    numero_revision SMALLINT NOT NULL DEFAULT 1,
    estado VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE',
    id_revisor BIGINT NULL,
    revisado_en TIMESTAMPTZ NULL,
    observacion TEXT NULL,
    CONSTRAINT pk_horario_revision PRIMARY KEY (id_revision),
    CONSTRAINT fk_horario_revision_horario FOREIGN KEY (id_horario)
        REFERENCES gestion_horarios.horario (id_horario) ON DELETE CASCADE,
    CONSTRAINT fk_horario_revision_revisor FOREIGN KEY (id_revisor)
        REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT uq_horario_revision_numero UNIQUE (id_horario, numero_revision),
    CONSTRAINT ck_horario_revision_numero CHECK (numero_revision > 0),
    CONSTRAINT ck_horario_revision_estado
        CHECK (estado IN ('PENDIENTE', 'APROBADA', 'OBSERVADA', 'RECHAZADA')),
    CONSTRAINT ck_horario_revision_fechas CHECK (
        (estado = 'PENDIENTE' AND revisado_en IS NULL)
        OR (estado <> 'PENDIENTE' AND revisado_en IS NOT NULL)
    )
);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS gestion_horarios.horario_revision');
    }
};