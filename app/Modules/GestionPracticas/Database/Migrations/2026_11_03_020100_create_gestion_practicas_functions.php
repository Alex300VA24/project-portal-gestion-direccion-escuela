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
            CREATE OR REPLACE FUNCTION gestion_practicas.calcular_nota_final(p_id_practica BIGINT)
            RETURNS NUMERIC
            LANGUAGE sql
            STABLE
            AS $$
                SELECT ROUND(
                    SUM(ec.nota * c.peso) / NULLIF(SUM(c.peso), 0),
                    2
                )
                FROM gestion_practicas.evaluacion e
                JOIN gestion_practicas.evaluacion_criterio ec
                  ON ec.id_evaluacion = e.id_evaluacion
                JOIN gestion_practicas.criterio_evaluacion c
                  ON c.id_criterio = ec.id_criterio
                 AND c.evaluador = e.evaluador
                WHERE e.id_practica = p_id_practica
            $$
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS gestion_practicas.calcular_nota_final(BIGINT)');
        }
    }
};
