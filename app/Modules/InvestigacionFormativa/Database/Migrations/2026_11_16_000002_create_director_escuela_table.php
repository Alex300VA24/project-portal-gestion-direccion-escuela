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
            CREATE TABLE investigacion_formativa.director_escuela (
                user_id    BIGINT NOT NULL,
                id_escuela BIGINT NOT NULL,
                activo     BOOLEAN NOT NULL DEFAULT TRUE,
                CONSTRAINT if_director_escuela_pk PRIMARY KEY (user_id, id_escuela),
                CONSTRAINT if_director_escuela_user_fk FOREIGN KEY (user_id)
                    REFERENCES public.users (id) ON DELETE RESTRICT,
                CONSTRAINT if_director_escuela_escuela_fk FOREIGN KEY (id_escuela)
                    REFERENCES core.escuela_profesional (id_escuela) ON DELETE RESTRICT
            );
            CREATE INDEX if_director_escuela_escuela_idx
                ON investigacion_formativa.director_escuela (id_escuela);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.director_escuela');
    }
};
