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
            CREATE TABLE investigacion_formativa.encuesta_respuesta_item (
                id_respuesta BIGINT NOT NULL,
                id_pregunta BIGINT NOT NULL,
                id_encuesta  BIGINT NOT NULL,
                valor        SMALLINT NOT NULL,
                CONSTRAINT if_encuesta_respuesta_item_pk PRIMARY KEY (id_respuesta, id_pregunta),
                CONSTRAINT if_encuesta_respuesta_item_respuesta_fk FOREIGN KEY (id_respuesta, id_encuesta)
                    REFERENCES investigacion_formativa.encuesta_respuesta (id_respuesta, id_encuesta) ON DELETE RESTRICT,
                CONSTRAINT if_encuesta_respuesta_item_pregunta_fk FOREIGN KEY (id_pregunta, id_encuesta)
                    REFERENCES investigacion_formativa.encuesta_pregunta (id_pregunta, id_encuesta) ON DELETE RESTRICT,
                CONSTRAINT if_encuesta_respuesta_item_valor_ck CHECK (valor BETWEEN 1 AND 5)
            );

            CREATE INDEX if_encuesta_respuesta_item_encuesta_idx
                ON investigacion_formativa.encuesta_respuesta_item (id_encuesta);
            CREATE INDEX if_encuesta_respuesta_item_respuesta_idx
                ON investigacion_formativa.encuesta_respuesta_item (id_respuesta, id_encuesta);
            CREATE INDEX if_encuesta_respuesta_item_pregunta_idx
                ON investigacion_formativa.encuesta_respuesta_item (id_pregunta, id_encuesta);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP TABLE IF EXISTS investigacion_formativa.encuesta_respuesta_item');
    }
};
