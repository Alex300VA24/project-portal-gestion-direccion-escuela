<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @return array<int, array{string, string, string, string, string}> */
    private function links(): array
    {
        return [
            ['cronograma_prematricula', 'id_periodo', 'core.periodo_academico', 'id_periodo', 'pre_cr_per_fk'],
            ['solicitud_prematricula', 'id_estudiante', 'core.estudiante', 'id_estudiante', 'pre_so_est_fk'],
            ['solicitud_prematricula', 'id_periodo', 'core.periodo_academico', 'id_periodo', 'pre_so_per_fk'],
            ['solicitud_prematricula', 'revisado_por', 'seguridad.usuario', 'id_usuario', 'pre_so_rev_fk'],
            ['detalle_solicitud', 'id_curso_plan', 'core.curso_plan', 'id_curso_plan', 'pre_de_cur_fk'],
            ['detalle_solicitud', 'id_seccion_preferida', 'core.seccion', 'id_seccion', 'pre_de_sec_fk'],
        ];
    }

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->links() as [$table, $column, $target, $primaryKey, $name]) {
            Schema::table('prematricula.'.$table, function (Blueprint $blueprint) use (
                $column,
                $target,
                $primaryKey,
                $name,
            ) {
                $blueprint->foreign($column, $name)
                    ->references($primaryKey)
                    ->on($target)
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_reverse($this->links()) as [$table, , , , $name]) {
            Schema::table(
                'prematricula.'.$table,
                fn (Blueprint $blueprint) => $blueprint->dropForeign($name),
            );
        }
    }
};
