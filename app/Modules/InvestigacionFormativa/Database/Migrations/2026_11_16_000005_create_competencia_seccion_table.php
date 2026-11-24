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
            CREATE TABLE investigacion_formativa.competencia_seccion (
                id_competencia BIGSERIAL PRIMARY KEY,
                id_seccion     BIGINT NOT NULL,
                codigo         VARCHAR(60) NOT NULL,
                descripcion    TEXT NOT NULL,
                fuente         VARCHAR(250) NOT NULL,
                seleccionada   BOOLEAN NOT NULL DEFAULT FALSE,
                CONSTRAINT if_competencia_seccion_seccion_codigo_uq UNIQUE (id_seccion, codigo),
                CONSTRAINT if_competencia_seccion_seccion_fk FOREIGN KEY (id_seccion)
                    REFERENCES core.seccion (id_seccion) ON DELETE RESTRICT
            );
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.competencia_seccion');
    }
};
