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
            ALTER TABLE investigacion_formativa.observacion
                ADD COLUMN id_evaluacion BIGINT,
                ADD COLUMN version_evaluacion INTEGER,
                ADD CONSTRAINT if_observacion_evaluacion_fk
                    FOREIGN KEY (id_evaluacion)
                    REFERENCES investigacion_formativa.evaluacion (id_evaluacion)
                    ON DELETE CASCADE,
                ADD CONSTRAINT if_observacion_version_evaluacion_ck
                    CHECK (version_evaluacion IS NULL OR version_evaluacion > 0);

            CREATE INDEX if_observacion_evaluacion_version_idx
                ON investigacion_formativa.observacion (id_evaluacion, version_evaluacion);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS investigacion_formativa.if_observacion_evaluacion_version_idx;

            ALTER TABLE investigacion_formativa.observacion
                DROP CONSTRAINT IF EXISTS if_observacion_version_evaluacion_ck,
                DROP CONSTRAINT IF EXISTS if_observacion_evaluacion_fk,
                DROP COLUMN IF EXISTS version_evaluacion,
                DROP COLUMN IF EXISTS id_evaluacion;
            SQL);
    }
};
