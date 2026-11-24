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
            CREATE TABLE investigacion_formativa.encuesta_respuesta (
                id_respuesta   BIGSERIAL PRIMARY KEY,
                id_encuesta    BIGINT NOT NULL,
                id_estudiante  BIGINT NOT NULL,
                comentario     VARCHAR(2000),
                respondida_en  TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT if_encuesta_respuesta_encuesta_fk FOREIGN KEY (id_encuesta)
                    REFERENCES investigacion_formativa.encuesta (id_encuesta) ON DELETE RESTRICT,
                CONSTRAINT if_encuesta_respuesta_estudiante_fk FOREIGN KEY (id_estudiante)
                    REFERENCES core.estudiante (id_estudiante) ON DELETE RESTRICT,
                CONSTRAINT if_encuesta_respuesta_estudiante_uq UNIQUE (id_encuesta, id_estudiante),
                CONSTRAINT if_encuesta_respuesta_id_uq UNIQUE (id_respuesta, id_encuesta)
            );

            CREATE INDEX if_encuesta_respuesta_estudiante_idx
                ON investigacion_formativa.encuesta_respuesta (id_estudiante);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP TABLE IF EXISTS investigacion_formativa.encuesta_respuesta');
    }
};
