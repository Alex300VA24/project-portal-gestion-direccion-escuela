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
            ALTER TABLE tesis.actividad_cronograma
                ADD COLUMN horas_semanales SMALLINT,
                ADD CONSTRAINT tes_actividad_horas_ck CHECK (horas_semanales IS NULL OR horas_semanales BETWEEN 1 AND 60);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE tesis.actividad_cronograma
                DROP CONSTRAINT IF EXISTS tes_actividad_horas_ck,
                DROP COLUMN IF EXISTS horas_semanales;
            SQL);
    }
};
