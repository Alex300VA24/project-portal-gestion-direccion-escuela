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
            CREATE TABLE investigacion_formativa.criterio_nivel (
                id_criterio BIGINT NOT NULL,
                nivel       SMALLINT NOT NULL,
                descripcion TEXT NOT NULL,
                puntaje     NUMERIC(6,2) NOT NULL,
                CONSTRAINT if_criterio_nivel_pk PRIMARY KEY (id_criterio, nivel),
                CONSTRAINT if_criterio_nivel_nivel_ck CHECK (nivel BETWEEN 1 AND 4),
                CONSTRAINT if_criterio_nivel_puntaje_ck CHECK (puntaje >= 0),
                CONSTRAINT if_criterio_nivel_criterio_fk FOREIGN KEY (id_criterio)
                    REFERENCES investigacion_formativa.criterio (id_criterio) ON DELETE CASCADE
            );
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.criterio_nivel');
    }
};
