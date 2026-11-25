<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Los montos varían en cada convocatoria: el portal ya no los registra ni los muestra. La columna costo_referencial queda sin uso. */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Quita los montos escritos entre paréntesis dentro de los textos, por ejemplo "(S/ 6.00)"
        foreach (['tipo_solicitud' => ['nombre', 'descripcion', 'base_normativa_fuente'], 'tipo_solicitud_requisito' => ['nombre', 'descripcion']] as $tabla => $columnas) {
            foreach ($columnas as $columna) {
                DB::statement("UPDATE bienestar.{$tabla} SET {$columna} = trim(regexp_replace({$columna}, '\\s*\\([^)]*S/[^)]*\\)', '', 'g')) WHERE {$columna} LIKE '%S/%'");
            }
        }
    }

    public function down(): void
    {
        // Los textos limpiados no se restauran
    }
};
