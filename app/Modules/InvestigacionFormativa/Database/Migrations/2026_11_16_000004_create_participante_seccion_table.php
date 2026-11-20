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
            CREATE TABLE investigacion_formativa.participante_seccion (
                id_seccion         BIGINT NOT NULL,
                id_estudiante      BIGINT NOT NULL,
                estado             VARCHAR(20) NOT NULL DEFAULT 'activo',
                fuente             VARCHAR(120) NOT NULL,
                referencia_origen  VARCHAR(250) NOT NULL,
                sincronizado_en    TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT if_participante_seccion_pk PRIMARY KEY (id_seccion, id_estudiante),
                CONSTRAINT if_participante_seccion_estado_ck CHECK (estado IN ('activo', 'retirado')),
                CONSTRAINT if_participante_seccion_seccion_fk FOREIGN KEY (id_seccion)
                    REFERENCES core.seccion (id_seccion) ON DELETE RESTRICT,
                CONSTRAINT if_participante_seccion_estudiante_fk FOREIGN KEY (id_estudiante)
                    REFERENCES core.estudiante (id_estudiante) ON DELETE RESTRICT
            );
            CREATE INDEX if_participante_seccion_estudiante_idx
                ON investigacion_formativa.participante_seccion (id_estudiante);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.participante_seccion');
    }
};
