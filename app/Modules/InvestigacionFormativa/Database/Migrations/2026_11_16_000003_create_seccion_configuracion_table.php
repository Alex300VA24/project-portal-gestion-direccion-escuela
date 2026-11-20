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
            CREATE TABLE investigacion_formativa.seccion_configuracion (
                id_configuracion BIGSERIAL PRIMARY KEY,
                id_seccion       BIGINT NOT NULL,
                habilitada       BOOLEAN NOT NULL DEFAULT FALSE,
                id_rubrica       BIGINT NULL,
                configurado_en   TIMESTAMPTZ NULL,
                version          INTEGER NOT NULL DEFAULT 1,
                actualizado_por  BIGINT NULL,
                actualizado_en   TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT if_seccion_configuracion_seccion_uq UNIQUE (id_seccion),
                CONSTRAINT if_seccion_configuracion_rubrica_uq UNIQUE (id_rubrica),
                CONSTRAINT if_seccion_configuracion_version_ck CHECK (version > 0),
                CONSTRAINT if_seccion_configuracion_seccion_fk FOREIGN KEY (id_seccion)
                    REFERENCES core.seccion (id_seccion) ON DELETE RESTRICT,
                CONSTRAINT if_seccion_configuracion_rubrica_fk FOREIGN KEY (id_rubrica)
                    REFERENCES investigacion_formativa.rubrica (id_rubrica) ON DELETE RESTRICT,
                CONSTRAINT if_seccion_configuracion_usuario_fk FOREIGN KEY (actualizado_por)
                    REFERENCES public.users (id) ON DELETE RESTRICT
            );
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.seccion_configuracion');
    }
};
