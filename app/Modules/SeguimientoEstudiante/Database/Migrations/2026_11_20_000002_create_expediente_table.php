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
            CREATE TABLE seguimiento_estudiante.expediente (
                id_estudiante BIGINT PRIMARY KEY REFERENCES core.estudiante (id_estudiante) ON DELETE RESTRICT,
                revision BIGINT NOT NULL DEFAULT 0 CHECK (revision >= 0),
                datos JSONB NOT NULL DEFAULT '{}'::jsonb CHECK (jsonb_typeof(datos) = 'object'),
                actualizado_en TIMESTAMPTZ NOT NULL DEFAULT now()
            );
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS seguimiento_estudiante.expediente');
        }
    }
};
