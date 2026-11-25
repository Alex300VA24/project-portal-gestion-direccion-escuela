<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE seguimiento_estudiante.programa_nivelacion ADD COLUMN publicado_en TIMESTAMPTZ;
                ALTER TABLE seguimiento_estudiante.nivelacion_calificacion ADD COLUMN publicada_en TIMESTAMPTZ;
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE seguimiento_estudiante.nivelacion_calificacion DROP COLUMN publicada_en;
                ALTER TABLE seguimiento_estudiante.programa_nivelacion DROP COLUMN publicado_en;
                SQL);
        }
    }
};
