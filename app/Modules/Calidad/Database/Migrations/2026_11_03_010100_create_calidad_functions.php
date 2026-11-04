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
            CREATE OR REPLACE FUNCTION calidad.calcular_valor_indicador(p_id_medicion BIGINT)
            RETURNS NUMERIC
            LANGUAGE sql
            STABLE
            AS $$
                SELECT CASE
                    WHEN m.denominador = 0 THEN NULL
                    ELSE ROUND(m.numerador / m.denominador, 4)
                END
                FROM calidad.medicion_indicador m
                WHERE m.id_medicion = p_id_medicion
            $$
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS calidad.calcular_valor_indicador(BIGINT)');
        }
    }
};
