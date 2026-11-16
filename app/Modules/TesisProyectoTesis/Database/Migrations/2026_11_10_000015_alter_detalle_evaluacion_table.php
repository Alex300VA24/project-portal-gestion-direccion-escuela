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
            ALTER TABLE tesis.detalle_evaluacion
                ALTER COLUMN puntaje DROP NOT NULL,
                ADD COLUMN cumple BOOLEAN NOT NULL DEFAULT false,
                ADD COLUMN no_aplica BOOLEAN NOT NULL DEFAULT false;
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE tesis.detalle_evaluacion
                DROP COLUMN IF EXISTS no_aplica,
                DROP COLUMN IF EXISTS cumple;
            UPDATE tesis.detalle_evaluacion SET puntaje = 0 WHERE puntaje IS NULL;
            ALTER TABLE tesis.detalle_evaluacion ALTER COLUMN puntaje SET NOT NULL;
            SQL);
    }
};
