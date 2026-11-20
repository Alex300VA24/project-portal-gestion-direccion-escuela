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
            ALTER TABLE investigacion_formativa.grupo
                ADD CONSTRAINT if_grupo_grupo_seccion_uq UNIQUE (id_grupo, id_seccion)
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE investigacion_formativa.grupo
                DROP CONSTRAINT IF EXISTS if_grupo_grupo_seccion_uq
            SQL);
    }
};
