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
            ALTER TABLE seguimiento_estudiante.atencion
                ADD COLUMN actividad TEXT,
                ADD COLUMN aspectos_tratados TEXT,
                ADD COLUMN observaciones TEXT,
                ADD COLUMN resultado TEXT;
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE seguimiento_estudiante.atencion
                DROP COLUMN actividad,
                DROP COLUMN aspectos_tratados,
                DROP COLUMN observaciones,
                DROP COLUMN resultado;
            SQL);
    }
};
