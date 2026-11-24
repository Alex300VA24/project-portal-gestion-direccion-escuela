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

        DB::statement('LOCK TABLE seguimiento_estudiante.tutor_asignacion IN SHARE MODE');
        $conflictos = DB::table('seguimiento_estudiante.tutor_asignacion')
            ->selectRaw('1')
            ->where('tipo', 'Tutoria')
            ->where('estado', 'Activa')
            ->groupBy('id_estudiante', 'id_periodo')
            ->havingRaw('count(*) > 1')
            ->exists();

        if ($conflictos) {
            throw new RuntimeException('Existen tutorías activas duplicadas por estudiante y período. Coordinación debe resolverlas antes de volver a migrar.');
        }

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX se_tutoria_estudiante_periodo_activa_uq
                ON seguimiento_estudiante.tutor_asignacion (id_estudiante, id_periodo)
                WHERE tipo = 'Tutoria' AND estado = 'Activa'
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS seguimiento_estudiante.se_tutoria_estudiante_periodo_activa_uq');
        }
    }
};
