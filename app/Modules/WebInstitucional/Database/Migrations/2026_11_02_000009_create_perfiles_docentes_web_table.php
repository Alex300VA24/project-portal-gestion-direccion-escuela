<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_institucional.perfil_docente_web', function (Blueprint $table) {
            $table->bigInteger('id_docente')->primary();
            $table->bigInteger('id_archivo_foto')->nullable()->index('wi_perfil_docente_foto_index');
            $table->text('resena')->nullable();
            $table->text('enlace_cti_vitae')->nullable();
            $table->string('estado_revision', 50);
            $table->bigInteger('revisado_por')->nullable()->index('wi_perfil_docente_revisor_index');
            $table->timestampTz('revisado_en')->nullable();
            $table->text('observaciones_revision')->nullable();
            $table->timestampTz('retirado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_institucional.perfil_docente_web');
    }
};
