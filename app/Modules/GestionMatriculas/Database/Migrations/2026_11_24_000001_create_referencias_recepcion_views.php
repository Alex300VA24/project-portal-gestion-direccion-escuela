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
            CREATE VIEW gestion_matriculas.v_detalle_matricula_no_anulada AS
            SELECT detalle.id_detalle, matricula.id_estudiante, detalle.id_seccion, matricula.id_periodo,
                   detalle.estado AS estado_detalle, detalle.retirado_en
            FROM gestion_matriculas.matricula_detalle AS detalle
            JOIN gestion_matriculas.matricula AS matricula ON matricula.id_matricula = detalle.id_matricula
            WHERE matricula.estado <> 'Anulada'
            SQL);

        DB::statement(<<<'SQL'
            CREATE VIEW gestion_matriculas.v_componente_evaluacion_seccion AS
            SELECT componente.id_componente, componente.id_seccion, componente.nombre, seccion.id_periodo
            FROM gestion_matriculas.componente_evaluacion AS componente
            JOIN core.seccion AS seccion ON seccion.id_seccion = componente.id_seccion
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP VIEW IF EXISTS gestion_matriculas.v_componente_evaluacion_seccion');
        DB::statement('DROP VIEW IF EXISTS gestion_matriculas.v_detalle_matricula_no_anulada');
    }
};
