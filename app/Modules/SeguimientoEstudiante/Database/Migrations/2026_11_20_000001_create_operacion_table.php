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
            CREATE TABLE seguimiento_estudiante.operacion (
                id_escuela BIGINT NOT NULL REFERENCES core.escuela_profesional (id_escuela) ON DELETE RESTRICT,
                clave VARCHAR(255) NOT NULL CHECK (btrim(clave) <> ''),
                datos JSONB NOT NULL DEFAULT '{}'::jsonb CHECK (jsonb_typeof(datos) = 'object'),
                actualizado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
                PRIMARY KEY (id_escuela, clave)
            );
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS seguimiento_estudiante.operacion');
        }
    }
};
