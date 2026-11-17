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
            ALTER TABLE investigacion_formativa.evaluacion
                ADD COLUMN version INTEGER NOT NULL DEFAULT 1,
                ADD COLUMN sin_observaciones BOOLEAN,
                ADD CONSTRAINT if_evaluacion_version_ck CHECK (version > 0);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE investigacion_formativa.evaluacion
                DROP CONSTRAINT IF EXISTS if_evaluacion_version_ck,
                DROP COLUMN IF EXISTS sin_observaciones,
                DROP COLUMN IF EXISTS version;
            SQL);
    }
};
