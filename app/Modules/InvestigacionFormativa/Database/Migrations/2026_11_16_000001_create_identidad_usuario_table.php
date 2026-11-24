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
            CREATE TABLE investigacion_formativa.identidad_usuario (
                user_id              BIGINT NOT NULL,
                id_usuario_seguridad BIGINT NOT NULL,
                activo               BOOLEAN NOT NULL DEFAULT TRUE,
                creado_en            TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT if_identidad_usuario_pk PRIMARY KEY (user_id),
                CONSTRAINT if_identidad_usuario_seguridad_uq UNIQUE (id_usuario_seguridad),
                CONSTRAINT if_identidad_usuario_user_fk FOREIGN KEY (user_id)
                    REFERENCES public.users (id) ON DELETE RESTRICT,
                CONSTRAINT if_identidad_usuario_seguridad_fk FOREIGN KEY (id_usuario_seguridad)
                    REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT
            );
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.identidad_usuario');
    }
};
