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
            CREATE TABLE investigacion_formativa.encuesta_pregunta (
                id_pregunta BIGSERIAL PRIMARY KEY,
                id_encuesta BIGINT NOT NULL,
                texto        VARCHAR(1000) NOT NULL,
                orden        SMALLINT NOT NULL,
                CONSTRAINT if_encuesta_pregunta_encuesta_fk FOREIGN KEY (id_encuesta)
                    REFERENCES investigacion_formativa.encuesta (id_encuesta) ON DELETE RESTRICT,
                CONSTRAINT if_encuesta_pregunta_orden_uq UNIQUE (id_encuesta, orden),
                CONSTRAINT if_encuesta_pregunta_id_uq UNIQUE (id_pregunta, id_encuesta),
                CONSTRAINT if_encuesta_pregunta_texto_ck CHECK (char_length(btrim(texto)) > 0),
                CONSTRAINT if_encuesta_pregunta_orden_ck CHECK (orden > 0)
            );
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP TABLE IF EXISTS investigacion_formativa.encuesta_pregunta');
    }
};
