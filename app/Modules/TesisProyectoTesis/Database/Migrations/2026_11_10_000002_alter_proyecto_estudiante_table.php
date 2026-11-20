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
            ALTER TABLE tesis.proyecto_estudiante
                ADD COLUMN orden_autor SMALLINT NOT NULL DEFAULT 1,
                ADD COLUMN estado VARCHAR(20) NOT NULL DEFAULT 'Activo',
                ADD COLUMN fecha_incorporacion DATE NOT NULL DEFAULT CURRENT_DATE,
                ADD COLUMN fecha_retiro DATE,
                ADD COLUMN motivo_retiro TEXT,
                ADD CONSTRAINT tes_pe_orden_ck CHECK (orden_autor IN (1, 2)),
                ADD CONSTRAINT tes_pe_estado_ck CHECK (estado IN ('Activo', 'Retirado'));
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE tesis.proyecto_estudiante
                DROP CONSTRAINT IF EXISTS tes_pe_estado_ck,
                DROP CONSTRAINT IF EXISTS tes_pe_orden_ck,
                DROP COLUMN IF EXISTS motivo_retiro,
                DROP COLUMN IF EXISTS fecha_retiro,
                DROP COLUMN IF EXISTS fecha_incorporacion,
                DROP COLUMN IF EXISTS estado,
                DROP COLUMN IF EXISTS orden_autor;
            SQL);
    }
};
