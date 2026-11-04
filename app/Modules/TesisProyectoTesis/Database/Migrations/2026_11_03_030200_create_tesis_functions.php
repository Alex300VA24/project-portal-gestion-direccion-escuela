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
            CREATE OR REPLACE FUNCTION tesis.calcular_nota_sustentacion(p_id_sustentacion BIGINT)
            RETURNS NUMERIC
            LANGUAGE sql
            STABLE
            AS $$
                SELECT ROUND(AVG(r.nota), 2)
                FROM tesis.resultado_jurado r
                WHERE r.id_sustentacion = p_id_sustentacion
            $$
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS tesis.calcular_nota_sustentacion(BIGINT)');
        }
    }
};
