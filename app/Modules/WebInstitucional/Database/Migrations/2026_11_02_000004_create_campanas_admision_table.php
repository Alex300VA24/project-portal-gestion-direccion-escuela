<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_institucional.campana_admision', function (Blueprint $table) {
            $table->bigIncrements('id_campana');
            $table->bigInteger('id_periodo')->index('wi_campana_admision_periodo_index');
            $table->string('nombre');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('estado', 50);
            // P8: aprobación, publicación y cierre de una campaña.
            $table->string('url_postulacion', 2048)->nullable();
            $table->bigInteger('revisado_por')->nullable()->index('wi_campana_admision_revisor_index');
            $table->timestampTz('revisado_en')->nullable();
            $table->text('observaciones_revision')->nullable();
            $table->timestampTz('publicada_en')->nullable();
            $table->timestampTz('cerrada_en')->nullable();
        });

        DB::statement('ALTER TABLE web_institucional.campana_admision ADD CONSTRAINT wi_campana_admision_fechas_check CHECK (fecha_fin >= fecha_inicio)');
    }

    public function down(): void
    {
        Schema::dropIfExists('web_institucional.campana_admision');
    }
};
