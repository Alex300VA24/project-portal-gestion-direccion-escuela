<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE investigacion_formativa.evaluacion (
                id_evaluacion BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_entrega BIGINT NOT NULL UNIQUE,
                id_docente_evaluador BIGINT NOT NULL,
                comentario TEXT,
                evaluado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT evaluacion_entrega_fk FOREIGN KEY (id_entrega)
                    REFERENCES investigacion_formativa.entrega (id_entrega) ON UPDATE RESTRICT ON DELETE RESTRICT
            );
            CREATE INDEX evaluacion_docente_evaluador_idx ON investigacion_formativa.evaluacion (id_docente_evaluador);
            COMMENT ON COLUMN investigacion_formativa.evaluacion.id_docente_evaluador IS 'Referencia pendiente a core.docente(id_docente), sin FK externa';
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.evaluacion');
    }
};
