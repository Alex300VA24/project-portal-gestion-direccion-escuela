<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_institucional.publicacion', function (Blueprint $table) {
            $table->bigIncrements('id_publicacion');
            $table->bigInteger('id_categoria')->index('wi_publicacion_categoria_index');
            $table->string('titulo');
            $table->string('slug')->unique('wi_publicacion_slug_unique');
            $table->text('resumen')->nullable();
            $table->text('contenido');
            $table->bigInteger('id_archivo_portada')->nullable()->index('wi_publicacion_portada_index');
            $table->string('estado', 50);
            $table->timestampTz('publicada_en')->nullable();
            $table->bigInteger('autor')->index('wi_publicacion_autor_index');
            // P1/P2: revisión editorial, programación y retirada del portal.
            $table->bigInteger('revisado_por')->nullable()->index('wi_publicacion_revisor_index');
            $table->timestampTz('revisado_en')->nullable();
            $table->text('observaciones_revision')->nullable();
            $table->timestampTz('programada_para')->nullable();
            $table->timestampTz('despublicada_en')->nullable();

            $table->foreign('id_categoria', 'wi_publicacion_categoria_fk')
                ->references('id_categoria')->on('web_institucional.categoria_publicacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_institucional.publicacion');
    }
};
