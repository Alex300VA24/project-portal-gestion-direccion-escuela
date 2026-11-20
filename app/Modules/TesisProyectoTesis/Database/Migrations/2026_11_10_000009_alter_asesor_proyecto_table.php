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
            ALTER TABLE tesis.asesor_proyecto
                ADD COLUMN fecha_inicio DATE,
                ADD COLUMN fecha_fin DATE,
                ADD COLUMN motivo_cambio TEXT,
                ADD CONSTRAINT tes_asesor_estado_ck CHECK (estado IN ('Activo', 'Finalizado'));
            CREATE UNIQUE INDEX tes_asesor_activo_uq ON tesis.asesor_proyecto(id_proyecto) WHERE estado = 'Activo';
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS tesis.tes_asesor_activo_uq;
            ALTER TABLE tesis.asesor_proyecto
                DROP CONSTRAINT IF EXISTS tes_asesor_estado_ck,
                DROP COLUMN IF EXISTS motivo_cambio,
                DROP COLUMN IF EXISTS fecha_fin,
                DROP COLUMN IF EXISTS fecha_inicio;
            SQL);
    }
};
