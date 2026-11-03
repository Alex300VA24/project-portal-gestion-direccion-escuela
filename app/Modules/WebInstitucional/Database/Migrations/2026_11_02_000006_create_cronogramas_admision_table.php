<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_institucional.cronograma_admision', function (Blueprint $table) {
            $table->bigIncrements('id_actividad');
            $table->bigInteger('id_campana')->index('wi_cronograma_admision_campana_index');
            $table->string('actividad');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->integer('orden');

            $table->foreign('id_campana', 'wi_cronograma_admision_campana_fk')
                ->references('id_campana')->on('web_institucional.campana_admision');
        });

        DB::statement('ALTER TABLE web_institucional.cronograma_admision ADD CONSTRAINT wi_cronograma_admision_fechas_check CHECK (fecha_fin >= fecha_inicio)');
        DB::statement('ALTER TABLE web_institucional.cronograma_admision ADD CONSTRAINT wi_cronograma_admision_orden_check CHECK (orden >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('web_institucional.cronograma_admision');
    }
};
