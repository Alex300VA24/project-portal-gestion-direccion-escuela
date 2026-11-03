<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE investigacion_formativa.criterio (
                id_criterio BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_rubrica BIGINT NOT NULL,
                descripcion TEXT NOT NULL,
                puntaje_maximo NUMERIC(5,2) NOT NULL,
                orden SMALLINT NOT NULL,
                CONSTRAINT criterio_rubrica_fk FOREIGN KEY (id_rubrica)
                    REFERENCES investigacion_formativa.rubrica (id_rubrica) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT criterio_rubrica_orden_unique UNIQUE (id_rubrica, orden),
                CONSTRAINT criterio_descripcion_check CHECK (btrim(descripcion) <> ''),
                CONSTRAINT criterio_puntaje_maximo_check CHECK (puntaje_maximo > 0 AND puntaje_maximo <> 'NaN'::NUMERIC),
                CONSTRAINT criterio_orden_check CHECK (orden > 0)
            );
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.criterio');
    }
};
