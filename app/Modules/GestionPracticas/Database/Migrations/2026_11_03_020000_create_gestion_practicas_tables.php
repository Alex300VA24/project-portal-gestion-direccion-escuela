<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(file_get_contents(
            dirname(__DIR__).'/Sql/gestion_practicas.sql'
        ));
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['evaluacion_criterio', 'evaluacion', 'criterio_evaluacion',
            'observacion_practica', 'documento_practica', 'seguimiento', 'postulacion',
            'practica', 'observacion_convocatoria', 'convocatoria', 'empresa_usuario'] as $table) {
            Schema::dropIfExists("gestion_practicas.{$table}");
        }
    }
};
