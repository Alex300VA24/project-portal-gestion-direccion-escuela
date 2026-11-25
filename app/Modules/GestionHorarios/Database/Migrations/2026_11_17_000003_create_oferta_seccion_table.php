<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.oferta_seccion (
    id_oferta_seccion BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_oferta BIGINT NOT NULL REFERENCES gestion_horarios.oferta_curso(id_oferta) ON DELETE RESTRICT,
    id_seccion BIGINT NOT NULL CHECK (id_seccion > 0),
    estado VARCHAR(10) NOT NULL DEFAULT 'VIGENTE' CHECK (estado IN ('VIGENTE', 'CERRADA')),
    registrada_por BIGINT NOT NULL CHECK (registrada_por > 0),
    registrada_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    cerrada_por BIGINT,
    cerrada_en TIMESTAMPTZ,
    motivo_cierre TEXT,
    CONSTRAINT ck_oferta_seccion_cierre CHECK (
        (estado = 'VIGENTE' AND cerrada_por IS NULL AND cerrada_en IS NULL AND motivo_cierre IS NULL)
        OR (estado = 'CERRADA' AND cerrada_por IS NOT NULL AND cerrada_por > 0 AND cerrada_en IS NOT NULL AND cerrada_en >= registrada_en AND motivo_cierre IS NOT NULL AND length(trim(motivo_cierre)) > 0)
    )
);
CREATE UNIQUE INDEX uq_oferta_seccion_vigente ON gestion_horarios.oferta_seccion(id_seccion) WHERE estado = 'VIGENTE';
CREATE INDEX ix_oferta_seccion_oferta ON gestion_horarios.oferta_seccion(id_oferta);
CREATE INDEX ix_oferta_seccion_historial ON gestion_horarios.oferta_seccion(id_seccion, registrada_en);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE gestion_horarios.oferta_seccion');
    }
};
