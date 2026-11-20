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
            ALTER TABLE investigacion_formativa.grupo_estudiante
                ADD COLUMN id_seccion BIGINT;

            UPDATE investigacion_formativa.grupo_estudiante AS ge
            SET id_seccion = g.id_seccion
            FROM investigacion_formativa.grupo AS g
            WHERE g.id_grupo = ge.id_grupo;

            ALTER TABLE investigacion_formativa.grupo_estudiante
                ALTER COLUMN id_seccion SET NOT NULL;

            ALTER TABLE investigacion_formativa.grupo_estudiante
                ADD CONSTRAINT if_grupo_estudiante_seccion_estudiante_uq
                    UNIQUE (id_seccion, id_estudiante),
                ADD CONSTRAINT if_grupo_estudiante_grupo_seccion_fk
                    FOREIGN KEY (id_grupo, id_seccion)
                    REFERENCES investigacion_formativa.grupo (id_grupo, id_seccion)
                    ON DELETE CASCADE;

            CREATE INDEX if_grupo_estudiante_seccion_idx
                ON investigacion_formativa.grupo_estudiante (id_seccion);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE investigacion_formativa.grupo_estudiante
                DROP CONSTRAINT IF EXISTS if_grupo_estudiante_grupo_seccion_fk,
                DROP CONSTRAINT IF EXISTS if_grupo_estudiante_seccion_estudiante_uq;

            DROP INDEX IF EXISTS investigacion_formativa.if_grupo_estudiante_seccion_idx;

            ALTER TABLE investigacion_formativa.grupo_estudiante
                DROP COLUMN IF EXISTS id_seccion;
            SQL);
    }
};
