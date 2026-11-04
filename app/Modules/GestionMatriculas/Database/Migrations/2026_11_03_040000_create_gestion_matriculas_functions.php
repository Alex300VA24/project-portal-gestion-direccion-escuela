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
            CREATE OR REPLACE FUNCTION gestion_matriculas.calcular_creditos_matriculados(
                p_id_estudiante BIGINT,
                p_id_periodo BIGINT
            )
            RETURNS NUMERIC
            LANGUAGE sql
            STABLE
            AS $$
                SELECT COALESCE(SUM(c.creditos), 0)
                FROM gestion_matriculas.matricula m
                JOIN gestion_matriculas.matricula_detalle d ON d.id_matricula = m.id_matricula
                JOIN core.seccion s ON s.id_seccion = d.id_seccion
                JOIN core.curso_plan cp ON cp.id_curso_plan = s.id_curso_plan
                JOIN core.curso c ON c.id_curso = cp.id_curso
                WHERE m.id_estudiante = p_id_estudiante
                  AND m.id_periodo = p_id_periodo
                  AND m.estado <> 'Anulada'
                  AND d.estado <> 'Retirado'
            $$
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS gestion_matriculas.calcular_creditos_matriculados(BIGINT, BIGINT)');
        }
    }
};
