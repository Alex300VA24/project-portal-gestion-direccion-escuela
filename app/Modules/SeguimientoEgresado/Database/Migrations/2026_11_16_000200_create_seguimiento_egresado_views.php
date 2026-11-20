<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vistas de indicadores I1 a I6, cobertura e historial por egresado (Database/Sql/02_vistas_indicadores.sql).
 * Se calculan sobre las tablas del modelo final; no guardan datos.
 */
return new class extends Migration
{
    private const VISTAS = [
        'vw_historial_seguimiento', 'vw_i2_i4_cohorte_horizonte', 'vw_ultima_medicion_actual', 'vw_i6_anual',
        'vw_i3_oe', 'vw_i5_campania', 'vw_i4_campania', 'vw_i2_campania', 'vw_cobertura_campania',
        'vw_i1_cohorte_actual',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('SET LOCAL search_path TO seguimiento_egresado, public');
        DB::unprepared(file_get_contents(dirname(__DIR__).'/Sql/02_vistas_indicadores.sql'));
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::VISTAS as $vista) {
            DB::statement("DROP VIEW IF EXISTS seguimiento_egresado.{$vista}");
        }
    }
};
