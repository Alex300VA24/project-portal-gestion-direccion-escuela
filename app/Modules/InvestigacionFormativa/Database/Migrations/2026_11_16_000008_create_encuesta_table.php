<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TABLE investigacion_formativa.encuesta (
                id_encuesta   BIGSERIAL PRIMARY KEY,
                id_seccion    BIGINT NOT NULL,
                estado        VARCHAR(20) NOT NULL DEFAULT 'borrador',
                creada_por    BIGINT NOT NULL,
                publicada_en  TIMESTAMPTZ,
                cerrada_en    TIMESTAMPTZ,
                version       INTEGER NOT NULL DEFAULT 1,
                CONSTRAINT if_encuesta_seccion_fk FOREIGN KEY (id_seccion)
                    REFERENCES core.seccion (id_seccion) ON DELETE RESTRICT,
                CONSTRAINT if_encuesta_creada_por_fk FOREIGN KEY (creada_por)
                    REFERENCES public.users (id) ON DELETE RESTRICT,
                CONSTRAINT if_encuesta_seccion_uq UNIQUE (id_seccion),
                CONSTRAINT if_encuesta_estado_ck CHECK (estado IN ('borrador', 'publicada', 'cerrada')),
                CONSTRAINT if_encuesta_version_ck CHECK (version > 0)
            );

            CREATE INDEX if_encuesta_creada_por_idx
                ON investigacion_formativa.encuesta (creada_por);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP TABLE IF EXISTS investigacion_formativa.encuesta');
    }
};
