<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mapeos explícitos entre criterios y competencias para el indicador 3.3.
 *
 * La tabla pertenece al módulo. Cada fila conserva el peso y la fuente del
 * mapeo. Las competencias y los criterios ya tienen ownership local y se
 * relacionan mediante claves foráneas restrictivas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TABLE investigacion_formativa.indicador_competencia_criterio (
                id_mapeo BIGSERIAL PRIMARY KEY,
                id_competencia BIGINT NOT NULL REFERENCES investigacion_formativa.competencia_seccion (id_competencia) ON DELETE RESTRICT,
                id_criterio BIGINT NOT NULL REFERENCES investigacion_formativa.criterio (id_criterio) ON DELETE RESTRICT,
                peso NUMERIC(8,6) NOT NULL,
                fuente VARCHAR(250) NOT NULL,
                provisional BOOLEAN NOT NULL DEFAULT TRUE,
                CONSTRAINT if_indicador_competencia_criterio_uq
                    UNIQUE (id_competencia, id_criterio),
                CONSTRAINT if_indicador_competencia_criterio_peso_ck
                    CHECK (peso > 0 AND peso <= 1)
            );
            CREATE INDEX if_indicador_competencia_criterio_criterio_idx
                ON investigacion_formativa.indicador_competencia_criterio (id_criterio);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.indicador_competencia_criterio');
    }
};
