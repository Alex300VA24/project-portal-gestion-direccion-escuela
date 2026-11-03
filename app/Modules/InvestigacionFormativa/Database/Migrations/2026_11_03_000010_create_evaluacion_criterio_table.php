<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE investigacion_formativa.evaluacion_criterio (
                id_evaluacion BIGINT NOT NULL,
                id_criterio BIGINT NOT NULL,
                puntaje NUMERIC(5,2) NOT NULL,
                PRIMARY KEY (id_evaluacion, id_criterio),
                CONSTRAINT evaluacion_criterio_evaluacion_fk FOREIGN KEY (id_evaluacion)
                    REFERENCES investigacion_formativa.evaluacion (id_evaluacion) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT evaluacion_criterio_criterio_fk FOREIGN KEY (id_criterio)
                    REFERENCES investigacion_formativa.criterio (id_criterio) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT evaluacion_criterio_puntaje_check CHECK (puntaje >= 0 AND puntaje <> 'NaN'::NUMERIC)
            );
            CREATE INDEX evaluacion_criterio_criterio_idx ON investigacion_formativa.evaluacion_criterio (id_criterio);
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.evaluacion_criterio');
    }
};
