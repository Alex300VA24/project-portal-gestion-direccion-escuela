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

        DB::statement(<<<'SQL'
            CREATE TABLE seguimiento_estudiante.identidad_cuenta (
                id_usuario BIGINT PRIMARY KEY REFERENCES public.users (id) ON DELETE CASCADE,
                id_persona BIGINT NOT NULL UNIQUE REFERENCES core.persona (id_persona) ON DELETE RESTRICT
            )
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS seguimiento_estudiante.identidad_cuenta');
        }
    }
};
