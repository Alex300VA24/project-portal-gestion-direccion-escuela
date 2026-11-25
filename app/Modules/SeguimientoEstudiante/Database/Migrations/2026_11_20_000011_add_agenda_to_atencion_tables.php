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
                ADD COLUMN duracion_minutos SMALLINT CHECK (duracion_minutos BETWEEN 10 AND 120),
                ADD COLUMN lugar VARCHAR(250);
            ALTER TABLE seguimiento_estudiante.atencion_evento
                ADD COLUMN duracion_minutos SMALLINT CHECK (duracion_minutos BETWEEN 10 AND 120),
                ADD COLUMN lugar VARCHAR(250);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE seguimiento_estudiante.atencion_evento DROP COLUMN lugar, DROP COLUMN duracion_minutos;
            ALTER TABLE seguimiento_estudiante.atencion DROP COLUMN lugar, DROP COLUMN duracion_minutos;
            SQL);
    }
};
