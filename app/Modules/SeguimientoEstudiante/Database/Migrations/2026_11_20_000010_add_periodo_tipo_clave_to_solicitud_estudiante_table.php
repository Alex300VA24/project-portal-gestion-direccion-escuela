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
            ALTER TABLE seguimiento_estudiante.solicitud_estudiante
                ADD COLUMN id_periodo BIGINT REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
                ADD COLUMN tipo VARCHAR(30),
                ADD COLUMN clave_envio UUID,
                ADD CONSTRAINT se_solicitud_envio_uq UNIQUE (id_estudiante, clave_envio);
            CREATE INDEX se_solicitud_periodo_idx ON seguimiento_estudiante.solicitud_estudiante (id_periodo);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE seguimiento_estudiante.solicitud_estudiante
                    DROP COLUMN clave_envio,
                    DROP COLUMN tipo,
                    DROP COLUMN id_periodo
                SQL);
        }
    }
};
