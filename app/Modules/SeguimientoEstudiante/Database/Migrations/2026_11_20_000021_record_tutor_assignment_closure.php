<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE seguimiento_estudiante.tutor_asignacion
                    ADD COLUMN id_usuario_finalizador BIGINT REFERENCES public.users (id) ON DELETE SET NULL,
                    ADD COLUMN version_causa_fin UUID
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE seguimiento_estudiante.tutor_asignacion
                    DROP COLUMN id_usuario_finalizador,
                    DROP COLUMN version_causa_fin
                SQL);
        }
    }
};
