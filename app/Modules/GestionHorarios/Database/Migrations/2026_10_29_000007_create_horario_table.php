<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS gestion_horarios');
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.horario (
    id_horario BIGINT GENERATED ALWAYS AS IDENTITY,
    id_periodo BIGINT NOT NULL,
    ambito VARCHAR(40) NOT NULL DEFAULT 'GENERAL',
    version SMALLINT NOT NULL DEFAULT 1,
    estado VARCHAR(20) NOT NULL DEFAULT 'BORRADOR',
    id_creado_por BIGINT NOT NULL,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    id_aprobado_por BIGINT NULL,
    aprobado_en TIMESTAMPTZ NULL,
    publicado_en TIMESTAMPTZ NULL,
    CONSTRAINT pk_horario PRIMARY KEY (id_horario),
    CONSTRAINT fk_horario_periodo FOREIGN KEY (id_periodo)
        REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
    CONSTRAINT fk_horario_creado_por FOREIGN KEY (id_creado_por)
        REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT fk_horario_aprobado_por FOREIGN KEY (id_aprobado_por)
        REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT uq_horario_version UNIQUE (id_periodo, ambito, version),
    CONSTRAINT ck_horario_version CHECK (version > 0),
    CONSTRAINT ck_horario_estado CHECK (estado IN (
        'BORRADOR', 'GENERADO', 'VALIDADO', 'EN_REVISION',
        'APROBADO', 'PUBLICADO', 'ARCHIVADO'
    )),
    CONSTRAINT ck_horario_aprobacion CHECK (
        (estado IN ('APROBADO', 'PUBLICADO')
            AND id_aprobado_por IS NOT NULL AND aprobado_en IS NOT NULL)
        OR (estado NOT IN ('APROBADO', 'PUBLICADO'))
    ),
    CONSTRAINT ck_horario_publicacion CHECK (
        publicado_en IS NULL OR estado IN ('PUBLICADO', 'ARCHIVADO')
    )
);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS gestion_horarios.horario');
    }
};