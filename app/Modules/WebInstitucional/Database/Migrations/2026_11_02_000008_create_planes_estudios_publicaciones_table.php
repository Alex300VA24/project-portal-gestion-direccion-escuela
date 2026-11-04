<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_institucional.plan_estudios_publicacion', function (Blueprint $table) {
            $table->bigInteger('id_plan')->primary();
            $table->bigInteger('id_archivo_pdf')->index('wi_plan_publicacion_archivo_index');
            $table->string('estado_revision', 50);
            $table->text('observaciones')->nullable();
            $table->bigInteger('revisado_por')->nullable()->index('wi_plan_publicacion_revisor_index');
            $table->timestampTz('revisado_en')->nullable();
            $table->timestampTz('publicada_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_institucional.plan_estudios_publicacion');
    }
};
