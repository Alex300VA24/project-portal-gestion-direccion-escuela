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
            ALTER TABLE investigacion_formativa.evaluacion_criterio
                ADD COLUMN nivel SMALLINT,
                ADD CONSTRAINT if_evaluacion_criterio_nivel_ck
                    CHECK (nivel IS NULL OR nivel BETWEEN 1 AND 4),
                ADD CONSTRAINT if_evaluacion_criterio_nivel_fk
                    FOREIGN KEY (id_criterio, nivel)
                    REFERENCES investigacion_formativa.criterio_nivel (id_criterio, nivel)
                    ON DELETE RESTRICT;
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE investigacion_formativa.evaluacion_criterio
                DROP CONSTRAINT IF EXISTS if_evaluacion_criterio_nivel_fk,
                DROP CONSTRAINT IF EXISTS if_evaluacion_criterio_nivel_ck,
                DROP COLUMN IF EXISTS nivel;
            SQL);
    }
};
