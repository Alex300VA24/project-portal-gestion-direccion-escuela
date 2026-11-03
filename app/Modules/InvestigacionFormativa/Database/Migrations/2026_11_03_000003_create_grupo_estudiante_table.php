<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE investigacion_formativa.grupo_estudiante (
                id_grupo BIGINT NOT NULL,
                id_estudiante BIGINT NOT NULL,
                es_coordinador BOOLEAN NOT NULL DEFAULT FALSE,
                PRIMARY KEY (id_grupo, id_estudiante),
                CONSTRAINT grupo_estudiante_grupo_fk FOREIGN KEY (id_grupo)
                    REFERENCES investigacion_formativa.grupo (id_grupo) ON UPDATE RESTRICT ON DELETE RESTRICT
            );
            CREATE INDEX grupo_estudiante_estudiante_idx ON investigacion_formativa.grupo_estudiante (id_estudiante);
            COMMENT ON COLUMN investigacion_formativa.grupo_estudiante.id_estudiante IS 'Referencia pendiente a core.estudiante(id_estudiante), sin FK externa';
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.grupo_estudiante');
    }
};
