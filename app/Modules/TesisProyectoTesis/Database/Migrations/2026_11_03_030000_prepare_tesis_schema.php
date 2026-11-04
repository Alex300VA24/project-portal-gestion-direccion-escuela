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
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_namespace WHERE nspname = 'tesis') THEN
                    IF EXISTS (SELECT 1 FROM pg_namespace WHERE nspname = 'tesis_proyecto_tesis') THEN
                        ALTER SCHEMA tesis_proyecto_tesis RENAME TO tesis;
                    ELSE
                        CREATE SCHEMA tesis;
                    END IF;
                END IF;
            END
            $$
            SQL);
    }

    public function down(): void
    {
        // No se revierte el nombre: el esquema podría contener datos preexistentes.
    }
};
