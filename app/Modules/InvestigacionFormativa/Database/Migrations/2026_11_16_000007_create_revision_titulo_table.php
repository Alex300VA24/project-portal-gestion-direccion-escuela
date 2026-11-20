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
            CREATE TABLE investigacion_formativa.revision_titulo (
                id_revision   BIGSERIAL PRIMARY KEY,
                id_proyecto   BIGINT NOT NULL,
                titulo        VARCHAR(250) NOT NULL,
                estado        VARCHAR(30) NOT NULL,
                motivo        TEXT,
                user_id       BIGINT NOT NULL,
                registrado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT if_revision_titulo_estado_ck CHECK (estado IN ('Registrado', 'Aceptado', 'Cambio solicitado')),
                CONSTRAINT if_revision_titulo_proyecto_fk FOREIGN KEY (id_proyecto)
                    REFERENCES investigacion_formativa.proyecto (id_proyecto) ON DELETE RESTRICT,
                CONSTRAINT if_revision_titulo_user_fk FOREIGN KEY (user_id)
                    REFERENCES public.users (id) ON DELETE RESTRICT
            );
            CREATE INDEX if_revision_titulo_proyecto_idx
                ON investigacion_formativa.revision_titulo (id_proyecto);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.revision_titulo');
    }
};
